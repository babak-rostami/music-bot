<?php

namespace App\Jobs;

use App\Bot\NotificationService;
use App\Bot\BotUserService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\SongReactionPresenter;
use App\Models\ActivityLog;
use App\Models\Song;
use App\Services\MusicFinder\MusicFinderUsageService;
use App\Services\SongFileService;
use App\Services\SongReactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

abstract class BaseMusicDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $songId,
        public int|string $chatId,
        public int|null $messageId,
        public ?string $lockKey = null,
    ) {}

    abstract protected function bot(): BotApiInterface;
    abstract protected function queueLockPrefix(): string;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("{$this->queueLockPrefix()}-{$this->songId}-{$this->chatId}"))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    public function handle(
        NotificationService $notifier,
        BotUserService $botUserService,
        SongFileService $songFileService,
        SongReactionService $reactionService
    ): void {
        $bot = $this->bot();
        $platform = $bot->platform();

        $song = Song::find($this->songId);

        if (!$song) {
            $notifier->error('جاب دانلود آهنگ', 'آهنگ پیدا نشد', $platform, ['song_id' => $this->songId]);
            Log::warning('Song not found in download job', ['song_id' => $this->songId]);
            $this->releaseLock();
            return;
        }

        $userId = (int) $this->chatId;

        // ─────────────────────────────────────────────────
        // ۱. تلاش با file_id کش‌شده
        // ─────────────────────────────────────────────────
        $cachedData = $songFileService->getCachedFileId($song, $platform);
        if ($cachedData) {
            $cachedFileId = $cachedData['file_id'];
            $fileIdColumn = $cachedData['fileIdColumn'];
            $quality      = $cachedData['quality'];

            $response = $bot->sendAudioByFileId($userId, $cachedFileId, $song->title, $song->artist_name);

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($bot, $notifier, $botUserService, $reactionService, $song, $userId, $quality);
                $this->releaseLock();
                return;
            }

            $error = $response->json('description') ?: "شناسه فایل کش‌شده در {$platform} نامعتبر است";

            if (BotUserService::isBlockedError($response)) {
                $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality, $platform);
                $this->releaseLock();
                return;
            }

            Log::info('Cached file_id invalid, will retry with fresh download', ['platform' => $platform, 'song_id' => $song->id, 'error' => $error]);
            $song->update([$fileIdColumn => null]);
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
                $notifier->songSendFailed($song, $this->chatId, '320', 'خطا در دانلود فایل از سرور مبدا', $platform, $userId);
                throw new RuntimeException('خطا در دانلود فایل از سرور مبدا: ' . $e->getMessage(), previous: $e);
            }

            if (! $result) {
                $notifier->songSendFailed($song, $this->chatId, '320', 'هیچ لینک دانلود سالمی برای این آهنگ پیدا نشد', $platform, $userId);
                throw new RuntimeException('هیچ لینک دانلود سالمی برای این آهنگ پیدا نشد');
            }

            $originalFile = $result['path'];
            $quality      = $result['quality'];

            $fileIdColumn = "{$platform}_file_id_{$quality}";
            $fileName = $this->generateFileName($song);
            $cleanFile = $this->addMetadata($originalFile, $tempDirectory, $fileName, $song->title);

            $cacheResult = $bot->uploadAudioToCacheChannel($cleanFile, $song);

            if ($cacheResult && !empty($cacheResult['file_id'])) {
                $song->update([$fileIdColumn => $cacheResult['file_id']]);
                $response = $bot->sendAudioByFileId($this->chatId, $cacheResult['file_id'], $song->title, $song->artist_name);
            } else {
                $notifier->log(
                    ActivityLog::TYPE_DOWNLOAD_STARTED,
                    "آپلود به کانال کش ناموفق، ارسال مستقیم",
                    ['song_id' => $this->songId],
                    ActivityLog::LEVEL_WARNING,
                    $platform
                );
                Log::warning('Cache upload failed, sending directly', ['platform' => $platform, 'song_id' => $this->songId]);
                $response = $bot->sendAudio($this->chatId, $cleanFile, $fileName, $song->title, $song->artist_name);
            }

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($bot, $notifier, $botUserService, $reactionService, $song, $userId, $quality);
                $this->releaseLock();
                return;
            }

            $error = $response->json('description') ?: "ارسال فایل به {$platform} با خطا مواجه شد";

            if (BotUserService::isBlockedError($response)) {
                $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality, $platform);
                $this->releaseLock();
                return;
            }

            $this->recordFailure($notifier, $botUserService, $song, $userId, $error, $quality, $platform);
            throw new RuntimeException($error);
        } finally {
            Storage::disk('local')->deleteDirectory($tempDirectory);
        }
    }

    private function sendLyrics(BotApiInterface $bot, Song $song): void
    {
        if (!$song->lyrics) {
            return;
        }

        $lines = explode("\n", $song->lyrics);
        $inlineKeyboard = null;
        $displayText = $song->lyrics;

        if (count($lines) > 10) {
            $displayText = implode("\n", array_slice($lines, 0, 10)) . "\n...";

            $baseUrl = "https://saz.becharkh.com/songs/";
            $url = $baseUrl . $song->slug;

            $inlineKeyboard = [[['text' => 'مشاهده متن کامل آهنگ', 'url' => $url]]];
        }

        $bot->sendMessage($this->chatId, "🎤 متن آهنگ:\n\n" . $displayText, $inlineKeyboard);
    }

    private function onSendSuccess(
        BotApiInterface $bot,
        NotificationService $notifier,
        BotUserService $botUserService,
        SongReactionService $reactionService,
        Song $song,
        int $userId,
        $quality
    ): void {
        $userReaction = $reactionService->getUserReaction($song, $bot->platform(), (string) $userId);
        $bot->sendMessage(
            $this->chatId,
            SongReactionPresenter::messageText(),
            SongReactionPresenter::keyboard($song->id, $song->likes_count, $song->dislikes_count, $userReaction)
        );

        $this->sendLyrics($bot, $song);

        if ($this->messageId) {
            $bot->deleteMessage($this->chatId, $this->messageId);
        }

        $notifier->songSent($song, $this->chatId, $quality, $bot->platform(), $userId);
        $botUserService->recordSuccessfulSend($userId, $bot->platform());

        MusicFinderUsageService::recordDatabaseDelivery($bot->platform(), $this->chatId, $song->id);
    }

    private function recordFailure(
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        string $error,
        string $quality,
        string $platform
    ): void {
        $notifier->songSendFailed($song, $this->chatId, $quality, $error, $platform, $userId);
        $botUserService->recordFailedSend($userId, $platform);
    }

    private function releaseLock(): void
    {
        if ($this->lockKey) {
            Cache::forget($this->lockKey);
        }
    }

    public function failed(Throwable $exception): void
    {
        $platform = $this->queueLockPrefix() === 'bale-song' ? 'bale' : 'telegram';

        Log::error('Music download job failed permanently', [
            'platform'   => $platform,
            'song_id'    => $this->songId,
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId ?? null,
            'attempts'   => $this->attempts(),
            'error'      => $exception->getMessage(),
        ]);

        $this->releaseLock();

        try {
            app(NotificationService::class)->error(
                "جاب دانلود آهنگ (شکست دائمی بعد از {$this->attempts()} تلاش)",
                $exception->getMessage(),
                $platform,
                ['song_id' => $this->songId, 'chat_id' => $this->chatId, 'message_id' => $this->messageId ?? null]
            );
        } catch (Throwable $e) {
            // نادیده
        }

        try {
            if ($this->messageId) {
                $this->bot()->editMessageText(
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

    private function generateFileName(Song $song): string
    {
        return preg_replace('/[\/\\\\:*?"<>|]/u', '', "{$song->title} - {$song->artist_name}") . '.mp3';
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

        $command = sprintf(
            'ffmpeg -i %s -map 0:a -c copy -map_metadata -1 -map_metadata:s:a -1 %s -id3v2_version 3 %s -y 2>&1',
            escapeshellarg($input),
            implode(' ', $metadataFlags),
            escapeshellarg($output)
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            throw new RuntimeException("ffmpeg failed: " . implode("\n", $lines));
        }

        return $output;
    }
}
