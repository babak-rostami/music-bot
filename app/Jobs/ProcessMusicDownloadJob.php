<?php

namespace App\Jobs;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Bot\Telegram\Services\TelegramService;
use App\Models\ActivityLog;
use App\Models\Song;
use App\Services\SongFileService;
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
use Throwable;

class ProcessMusicDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // ۵ دقیقه
    public int $tries = 3;
    public int $backoff = 10;

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

    /**
     * @param TelegramService    $telegram
     * @param NotificationService $notifier
     * @param BotUserService     $botUserService
     */
    public function handle(
        TelegramService $telegram,
        NotificationService $notifier,
        BotUserService $botUserService,
        SongFileService $songFileService
    ): void {
        $song = Song::find($this->songId);

        if (!$song) {
            $notifier->error('جاب دانلود آهنگ', 'آهنگ پیدا نشد', 'telegram', [
                'song_id' => $this->songId,
            ]);
            Log::warning('Song not found in download job', ['song_id' => $this->songId]);
            return;
        }

        // در چت خصوصی: chatId == userId
        $userId = (int) $this->chatId;

        // 1 . چک میکنم ببینم قبلا اگه کش شده بود همون رو ارسال کن
        $cachedData = $songFileService->getCachedFileId($song, 'telegram');
        if ($cachedData) {
            $cachedFileId = $cachedData['file_id'];        // مثلاً "BAADBAADrwADBREAAYag"
            $fileIdColumn = $cachedData['fileIdColumn'];   // مثلاً "telegram_file_id_320"
            $quality      = $cachedData['quality'];        // "320" یا "128"

            $response = $telegram->sendAudioByFileId(
                $userId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                $song->increment('downloads_count');

                $this->sendLyrics($telegram, $song);

                if ($this->messageId) {
                    $telegram->deleteMessage($userId, $this->messageId);
                }

                // ✅ نوتیفیکیشن ارسال موفق + ریست خطا
                $notifier->songSent($song, $userId, $quality, 'telegram', $userId);
                $botUserService->recordSuccessfulSend($userId, 'telegram');

                return;
            }

            // ❌ ارسال ناموفق
            $error = $response->json('description', 'خطای ناشناخته');
            $notifier->songSendFailed($song, $this->chatId, $quality, $error, 'telegram', $userId);
            $botUserService->recordFailedSend($userId, 'telegram');

            // اگر خطا مربوط به file_id نبود (یعنی کاربر بلاک کرده) → ادامه نده
            if (BotUserService::isBlockedError($response)) {
                return;
            }

            // file_id نامعتبر → پاک کن و از مسیر job برو
            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // 2. کش نشده بود پس بهترین لینک دانلود رو باید پیدا کنیم
        // دانلود + آپلود به کش + ارسال
        // ─────────────────────────────────────────────────
        $tempDirectory = 'temp/' . Str::uuid();
        try {
            Storage::disk('local')->makeDirectory($tempDirectory);

            // دانلود آهنگ از سرویس
            try {
                $result = $songFileService->downloadBestSong($song, $tempDirectory);
            } catch (\Throwable $e) {
                Log::error('خطا در دانلود آهنگ از سرویس', [
                    'song_id' => $song->id,
                    'error'   => $e->getMessage(),
                ]);
                $this->downloadLinkNotFound($telegram, $notifier, $song, $userId, 'خطا در دانلود');
                return;
            }
            if (! $result) {
                $this->downloadLinkNotFound($telegram, $notifier, $song, $userId, 'لینک دانلود خراب');
                return;
            }

            $originalFile = $result['path'];
            $quality      = $result['quality'];

            $fileIdColumn = "telegram_file_id_{$quality}";
            $fileName = $this->generateFileName($song);
            $cleanFile = $this->addMetadata($originalFile, $tempDirectory, $fileName, $song->title);

            // آپلود به کانال Cache telegram
            $cacheResult = $telegram->uploadAudioToCacheChannel($cleanFile, $song);

            if ($cacheResult && !empty($cacheResult['file_id'])) {
                // کش موفق: file_id ذخیره + ارسال با file_id
                $song->update([$fileIdColumn => $cacheResult['file_id']]);

                $response = $telegram->sendAudioByFileId(
                    $this->chatId,
                    $cacheResult['file_id'],
                    $song->title,
                    $song->artist_name
                );
            } else {
                // Fallback: کش نشد → مستقیم بفرست
                $notifier->log(
                    ActivityLog::TYPE_DOWNLOAD_STARTED,
                    "آپلود به کانال کش ناموفق، ارسال مستقیم",
                    ['song_id' => $this->songId],
                    ActivityLog::LEVEL_WARNING,
                    'telegram'
                );
                Log::warning('Cache upload failed, sending directly', ['song_id' => $this->songId]);

                $response = $telegram->sendAudio(
                    $this->chatId,
                    $cleanFile,
                    $fileName,
                    $song->title,
                    $song->artist_name
                );
            }

            // ── بررسی نتیجه نهایی ارسال ──
            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($telegram, $notifier, $botUserService, $song, $userId, $quality);
            } else {
                $this->onSendFailure(
                    $telegram,
                    $notifier,
                    $botUserService,
                    $song,
                    $userId,
                    $response->json('description', 'خطای ناشناخته'),
                    $quality
                );
            }
        } finally {
            // همیشه temp رو پاک کن
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
                $displayText = implode("\n", array_slice($lines, 0, 3)) . "\n...";

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


    private function downloadLinkNotFound(
        TelegramService $telegram,
        NotificationService $notifier,
        Song $song,
        int $userId,
        string $error,
    ) {
        if ($this->messageId) {
            $telegram->editMessageText(
                $this->chatId,
                $this->messageId,
                "آهنگ با موفقیت در صف دانلود قرار گرفت 🎤✅ \n\n" . $song->title . " - " . $song->artist_name,
                []
            );
        }
        $notifier->songSendFailed($song, $this->chatId, '320', $error, 'telegram', $userId);
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال موفق
    // ─────────────────────────────────────────────────

    private function onSendSuccess(
        TelegramService $telegram,
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        $quality
    ): void {
        $this->sendLyrics($telegram, $song);
        if ($this->messageId) {
            $telegram->deleteMessage($this->chatId, $this->messageId);
        }

        // ✅ نوتیفیکیشن + ریست شمارش خطا
        $notifier->songSent($song, $this->chatId, $quality, 'telegram', $userId);
        $botUserService->recordSuccessfulSend($userId, 'telegram');
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال ناموفق
    // ─────────────────────────────────────────────────

    private function onSendFailure(
        TelegramService $telegram,
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        string $error,
        $quality
    ): void {
        // ❌ نوتیفیکیشن خطا + ثبت خطای ارسال (برای تشخیص بلاک‌شدن)
        $notifier->songSendFailed($song, $this->chatId, $quality, $error, 'telegram', $userId);
        $botUserService->recordFailedSend($userId, 'telegram');

        // به کاربر اطلاع بده
        if ($this->messageId) {
            try {
                $telegram->editMessageText(
                    $this->chatId,
                    $this->messageId,
                    '❌ خطا در ارسال. لطفاً دوباره تلاش کنید.',
                    []
                );
            } catch (Throwable $e) {
                Log::warning('Could not edit error message', ['error' => $e->getMessage()]);
            }
        }
    }

    // ─────────────────────────────────────────────────
    // وقتی جاب کاملاً شکست می‌خورد (تمام تلاش‌ها تمام شد)
    // ─────────────────────────────────────────────────

    public function failed(Throwable $exception): void
    {
        Log::error('Music download job failed permanently', [
            'song_id'    => $this->songId,
            'quality'    => '320',
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId ?? null,
            'error'      => $exception->getMessage(),
        ]);

        // ✅ نوتیفیکیشن خطای دائمی
        try {
            app(NotificationService::class)->error(
                'جاب دانلود آهنگ (شکست دائمی)',
                $exception->getMessage(),
                'telegram',
                [
                    'song_id'    => $this->songId,
                    'quality'    => '320',
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
                    '❌ خطا در دانلود یا پردازش فایل. لطفاً دوباره تلاش کنید.',
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

        // ساخت پوشه مقصد در صورت عدم وجود
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // عنوان فایل (در صورت نرفتن عنوان دلخواه، آدرس کانال قرار می‌گیرد)
        $title = $customTitle ?? $channel;
        $comment = "Telegram & Bale: {$channel}";

        // لیست متادیتای جدید جهت قرارگیری در بخش‌های مختلف
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

        // اجرا و پاکسازی متادیتای قبلی و اعمال متادیتای جدید
        $command = sprintf(
            'ffmpeg -i %s -map 0:a -c copy -map_metadata -1 -map_metadata:s:a -1 %s -id3v2_version 3 %s -y 2>&1',
            escapeshellarg($input),
            $metadataStr,
            escapeshellarg($output)
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            throw new \RuntimeException("ffmpeg failed: " . implode("\n", $lines));
        }

        return $output;
    }
}
