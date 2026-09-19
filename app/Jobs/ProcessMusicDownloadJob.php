<?php

namespace App\Jobs;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Bot\Telegram\Services\TelegramService;
use App\Bot\Telegram\Support\SongReactionPresenter;
use App\Models\ActivityLog;
use App\Models\Song;
use App\Services\SongFileService;
use App\Services\SongReactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessMusicDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // ۵ دقیقه در هر تلاش
    public int $tries = 3;     // حداکثر ۳ تلاش
    public int $backoff = 10;  // ۱۰ ثانیه فاصله بین تلاش‌ها

    public function __construct(
        public readonly int $songId,
        public readonly int|string $chatId,
        public readonly int|null $messageId,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("song-{$this->songId}-{$this->chatId}"))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    public function handle(
        TelegramService $telegram,
        NotificationService $notifier,
        BotUserService $botUserService,
        SongFileService $songFileService,
        SongReactionService $reactionService
    ): void {
        $song = Song::find($this->songId);

        if (!$song) {
            $notifier->error('جاب دانلود آهنگ', 'آهنگ پیدا نشد', 'telegram', [
                'song_id' => $this->songId,
            ]);
            Log::warning('Song not found in download job', ['song_id' => $this->songId]);
            return; // خطای دائمی — آهنگ اصلاً وجود نداره، تلاش مجدد فایده‌ای ندارد
        }

        $userId = (int) $this->chatId;

        // ─────────────────────────────────────────────────
        // ۱. تلاش با file_id کش‌شده
        // ─────────────────────────────────────────────────
        $cachedData = $songFileService->getCachedFileId($song, 'telegram');
        if ($cachedData) {
            $cachedFileId = $cachedData['file_id'];
            $fileIdColumn = $cachedData['fileIdColumn'];
            $quality      = $cachedData['quality'];

            $response = $telegram->sendAudioByFileId($userId, $cachedFileId, $song->title, $song->artist_name);

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($telegram, $notifier, $botUserService, $reactionService, $song, $userId, $quality);
                return;
            }

            $error = $response->json('description') ?: 'شناسه فایل کش‌شده در تلگرام نامعتبر است';

            if (BotUserService::isBlockedError($response)) {
                $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality);
                return; // کاربر بات رو بلاک کرده — تلاش مجدد بی‌فایده است
            }

            Log::info('Cached telegram file_id invalid, will retry with fresh download', [
                'song_id' => $song->id,
                'error'   => $error,
            ]);

            // $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // ۲. دانلود تازه + آپلود به کش + ارسال
        // ─────────────────────────────────────────────────
        $tempDirectory = 'temp/' . Str::uuid();
        try {
            Storage::disk('local')->makeDirectory($tempDirectory);

            try {
                $result = $songFileService->downloadBestSong($song, $tempDirectory);
            } catch (\Throwable $e) {
                Log::error('خطا در دانلود آهنگ از سرویس', ['song_id' => $song->id, 'error' => $e->getMessage()]);
                $notifier->songSendFailed($song, $this->chatId, '320', 'خطا در دانلود فایل از سرور مبدا', 'telegram', $userId);
                throw new RuntimeException('خطا در دانلود فایل از سرور مبدا: ' . $e->getMessage(), previous: $e);
            }

            if (! $result) {
                $notifier->songSendFailed($song, $this->chatId, '320', 'هیچ لینک دانلود سالمی برای این آهنگ پیدا نشد', 'telegram', $userId);
                throw new RuntimeException('هیچ لینک دانلود سالمی برای این آهنگ پیدا نشد');
            }

            $originalFile = $result['path'];
            $quality      = $result['quality'];

            $fileIdColumn = "telegram_file_id_{$quality}";
            $fileName = $this->generateFileName($song);
            $cleanFile = $this->addMetadata($originalFile, $tempDirectory, $fileName, $song->title);

            $cacheResult = $telegram->uploadAudioToCacheChannel($cleanFile, $song);

            if ($cacheResult && !empty($cacheResult['file_id'])) {
                $song->update([$fileIdColumn => $cacheResult['file_id']]);
                $response = $telegram->sendAudioByFileId($this->chatId, $cacheResult['file_id'], $song->title, $song->artist_name);
            } else {
                $notifier->log(
                    ActivityLog::TYPE_DOWNLOAD_STARTED,
                    "آپلود به کانال کش ناموفق، ارسال مستقیم",
                    ['song_id' => $this->songId],
                    ActivityLog::LEVEL_WARNING,
                    'telegram'
                );
                Log::warning('Cache upload failed, sending directly', ['song_id' => $this->songId]);
                $response = $telegram->sendAudio($this->chatId, $cleanFile, $fileName, $song->title, $song->artist_name);
            }

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($telegram, $notifier, $botUserService, $reactionService, $song, $userId, $quality);
                return;
            }

            $error = $response->json('description') ?: 'ارسال فایل به تلگرام با خطا مواجه شد';

            if (BotUserService::isBlockedError($response)) {
                $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality);
                return; // کاربر بات رو بلاک کرده
            }

            $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality);
            throw new RuntimeException($error);
        } finally {
            Storage::disk('local')->deleteDirectory($tempDirectory);
        }
    }

    private function sendLyrics(TelegramService $telegram, Song $song)
    {
        if ($song->lyrics) {
            $lines = explode("\n", $song->lyrics);
            $inlineKeyboard = null;
            $displayText = $song->lyrics;

            if (count($lines) > 3) {
                $displayText = implode("\n", array_slice($lines, 0, 10)) . "\n...";

                $baseUrl = $song->show_in_saz_site == 1 ? "https://saz.becharkh.com/songs/" : "https://music.becharkh.com/songs/";
                $url = $baseUrl . $song->slug;

                $inlineKeyboard = [
                    [
                        [
                            'text' => 'مشاهده متن کامل آهنگ',
                            'url'  => $url
                        ]
                    ]
                ];
            }

            $telegram->sendMessage($this->chatId, "🎤 متن آهنگ:\n\n" . $displayText, $inlineKeyboard);
        }
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال موفق
    // ─────────────────────────────────────────────────

    private function onSendSuccess(
        TelegramService $telegram,
        NotificationService $notifier,
        BotUserService $botUserService,
        SongReactionService $reactionService,
        Song $song,
        int $userId,
        $quality
    ): void {
        $this->sendReactionPrompt($telegram, $reactionService, $song, $userId);
        $this->sendLyrics($telegram, $song);

        if ($this->messageId) {
            $telegram->deleteMessage($this->chatId, $this->messageId);
        }

        $notifier->songSent($song, $this->chatId, $quality, 'telegram', $userId);
        $botUserService->recordSuccessfulSend($userId, 'telegram');
    }

    private function sendReactionPrompt(TelegramService $telegram, SongReactionService $reactionService, Song $song, int $userId): void
    {
        $userReaction = $reactionService->getUserReaction($song, 'telegram', (string) $userId);

        $telegram->sendMessage(
            $this->chatId,
            SongReactionPresenter::messageText(),
            SongReactionPresenter::keyboard($song->id, $song->likes_count, $song->dislikes_count, $userReaction)
        );
    }

    // ─────────────────────────────────────────────────
    // Helper: ثبت خطا برای ادمین/تشخیص بلاک (بدون پیام به کاربر — پیام نهایی فقط در failed() ارسال می‌شود)
    // ─────────────────────────────────────────────────

    private function recordFailure(
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        string $error,
        string $quality
    ): void {
        $notifier->songSendFailed($song, $this->chatId, $quality, $error, 'telegram', $userId);
        $botUserService->recordFailedSend($userId, 'telegram');
    }

    // ─────────────────────────────────────────────────
    // وقتی همه‌ی ۳ تلاش شکست بخورد، فقط اینجا صدا زده می‌شود
    // ─────────────────────────────────────────────────

    public function failed(Throwable $exception): void
    {
        Log::error('Music download job failed permanently', [
            'song_id'    => $this->songId,
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId ?? null,
            'attempts'   => $this->attempts(),
            'error'      => $exception->getMessage(),
        ]);

        try {
            app(NotificationService::class)->error(
                "جاب دانلود آهنگ (شکست دائمی بعد از {$this->attempts()} تلاش)",
                $exception->getMessage(),
                'telegram',
                [
                    'song_id'    => $this->songId,
                    'chat_id'    => $this->chatId,
                    'message_id' => $this->messageId ?? null,
                ]
            );
        } catch (Throwable $e) {
            // نادیده
        }

        try {
            $telegram = app(TelegramService::class);
            if ($this->messageId) {
                $telegram->editMessageText(
                    $this->chatId,
                    $this->messageId,
                    '❌ متأسفانه بعد از چند بار تلاش، ارسال این آهنگ ناموفق بود. لطفاً کمی بعد دوباره امتحان کن.',
                    []
                );
            }
        } catch (Throwable $e) {
            Log::error('Failed to update error message', ['error' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────

    private function generateFileName(Song $song): string
    {
        return preg_replace(
            '/[\/\\\\:*?"<>|]/u',
            '',
            "{$song->title} - {$song->artist_name}"
        ) . '.mp3';
    }

    private function downloadSong(string $url, string $dir): string
    {
        $path   = storage_path("app/private/{$dir}/original.mp3");
        $stream = fopen($path, 'w');

        Http::timeout(300)->withOptions(['sink' => $stream])->get($url);

        fclose($stream);

        return $path;
    }

    private function addMetadata(string $input, string $dir, string $fileName, ?string $customTitle = null): string
    {
        $channel = '@music_jangi';
        $outputDir = storage_path("app/private/{$dir}");
        $output = "{$outputDir}/{$fileName}";

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $title = $customTitle ?? $channel;
        $comment = "Telegram & Bale: {$channel}";

        $metadataFlags = [
            "-metadata title=" . escapeshellarg($title),
            "-metadata artist=" . escapeshellarg($channel),
            "-metadata album=" . escapeshellarg($channel),
            "-metadata album_artist=" . escapeshellarg($channel),
            "-metadata composer=" . escapeshellarg($channel),
            "-metadata publisher=" . escapeshellarg($channel),
            "-metadata copyright=" . escapeshellarg($channel),
            "-metadata encoded_by=" . escapeshellarg($channel),
            "-metadata comment=" . escapeshellarg($comment),
        ];

        $metadataStr = implode(' ', $metadataFlags);

        $command = sprintf(
            'ffmpeg -i %s -map 0:a -c copy -map_metadata -1 -map_metadata:s:a -1 %s -id3v2_version 3 %s -y 2>&1',
            escapeshellarg($input),
            $metadataStr,
            escapeshellarg($output)
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            throw new RuntimeException("ffmpeg failed: " . implode("\n", $lines));
        }

        return $output;
    }
}
