<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Models\ActivityLog;
use App\Models\Song;
use App\Services\SongFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessBaleMusicDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $songId,
        public readonly int|string $chatId,
        public readonly int|null $messageId,
        public readonly ?string $lockKey = null, // ✅ کلید قفل برای آزادسازی بعد از اتمام
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("bale-song-{$this->songId}-{$this->chatId}"))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    /**
     * @param BaleService         $bale
     * @param NotificationService $notifier
     * @param BotUserService      $botUserService
     * @param SongFileService      $songFileService
     */
    public function handle(
        BaleService $bale,
        NotificationService $notifier,
        BotUserService $botUserService,
        SongFileService $songFileService
    ): void {
        $song = Song::find($this->songId);

        if (!$song) {
            $notifier->error('جاب دانلود آهنگ', 'آهنگ پیدا نشد', 'bale', [
                'song_id' => $this->songId,
            ]);
            Log::warning('Song not found in Bale download job', ['song_id' => $this->songId]);
            $this->releaseLock();
            return;
        }

        // در چت خصوصی: chatId == userId
        $userId = (int) $this->chatId;

        // 1 . چک میکنم ببینم قبلا اگه کش شده بود همون رو ارسال کن
        $cachedData = $songFileService->getCachedFileId($song, 'bale');
        if ($cachedData) {
            $cachedFileId = $cachedData['file_id'];        // مثلاً "BAADBAADrwADBREAAYag"
            $fileIdColumn = $cachedData['fileIdColumn'];   // مثلاً "bale_file_id_320"
            $quality      = $cachedData['quality'];        // "320" یا "128"

            $response = $bale->sendAudioByFileId(
                $userId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($bale, $notifier, $botUserService, $song, $userId, $quality);
                $this->releaseLock();
                return;
            }

            // ❌ file_id نامعتبر
            $this->onSendFailure(
                $bale,
                $notifier,
                $botUserService,
                $song,
                $userId,
                'file_id کش‌شده نامعتبر',
                $quality
            );

            // اگر کاربر بلاک کرده → ادامه‌ی تلاش بی‌فایده است
            if (BotUserService::isBlockedError($response)) {
                $this->releaseLock();
                return;
            }

            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // 2. کش نشده بود پس بهترین لینک دانلود رو باید پیدا کنیم
        // دانلود + آپلود به کش + ارسال
        // ─────────────────────────────────────────────────
        $bestLink = $songFileService->getBestDownloadLink($song);
        if (!$bestLink) {
            $this->downloadLinkNotFound($bale, $notifier, $song, $userId, 'لینک دانلود خراب');
            return;
        }

        $fileIdColumn = "bale_file_id_{$bestLink->quality}";
        $tempDirectory = 'temp/' . Str::uuid();
        Storage::disk('local')->makeDirectory($tempDirectory);

        try {
            $downloadUrl = $bestLink->download_url;
            $fileName = $this->generateFileName($song);
            $originalFile = $this->downloadSong($downloadUrl, $tempDirectory);
            $cleanFile = $this->removeMetadata($originalFile, $tempDirectory, $fileName);

            // آپلود به کانال Cache بله
            $cacheResult = $bale->uploadAudioToCacheChannel($cleanFile, $song);

            if ($cacheResult && !empty($cacheResult['file_id'])) {
                $song->update([$fileIdColumn => $cacheResult['file_id']]);

                $response = $bale->sendAudioByFileId(
                    $this->chatId,
                    $cacheResult['file_id'],
                    $song->title,
                    $song->artist_name
                );
            } else {
                $notifier->log(
                    ActivityLog::TYPE_DOWNLOAD_STARTED,
                    "آپلود به کانال کش بله ناموفق، ارسال مستقیم",
                    ['song_id' => $this->songId],
                    ActivityLog::LEVEL_WARNING,
                    'bale'
                );
                Log::warning('Bale cache upload failed, sending directly', ['song_id' => $this->songId]);

                $response = $bale->sendAudio(
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
                $this->onSendSuccess($bale, $notifier, $botUserService, $song, $userId, $bestLink->quality);
            } else {
                $this->onSendFailure(
                    $bale,
                    $notifier,
                    $botUserService,
                    $song,
                    $userId,
                    $response->json('description', 'خطای ناشناخته'),
                    $bestLink->quality
                );
            }
        } finally {
            Storage::disk('local')->deleteDirectory($tempDirectory);
            $this->releaseLock();
        }
    }

    private function downloadLinkNotFound(
        BaleService $bale,
        NotificationService $notifier,
        Song $song,
        int $userId,
        string $error
    ) {
        $bale->editMessageText(
            $this->chatId,
            $this->messageId,
            "آهنگ با موفقیت در صف دانلود قرار گرفت 🎤✅ \n\n" . $song->title . " - " . $song->artist_name,
            []
        );
        $notifier->songSendFailed($song, $this->chatId, '320', $error, 'bale', $userId);
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال موفق
    // ─────────────────────────────────────────────────

    private function onSendSuccess(
        BaleService $bale,
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        $quality
    ): void {
        if ($song->lyrics) {
            $bale->sendMessage($this->chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
        }
        if ($this->messageId) {
            $bale->deleteMessage($this->chatId, $this->messageId);
        }

        // ✅ نوتیفیکیشن + ریست شمارش خطا
        $notifier->songSent($song, $this->chatId, $quality, 'bale', $userId);
        $botUserService->recordSuccessfulSend($userId, 'bale');
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال ناموفق
    // ─────────────────────────────────────────────────

    private function onSendFailure(
        BaleService $baleService,
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        string $error,
        $quality
    ): void {
        // ❌ نوتیفیکیشن خطا + ثبت خطای ارسال
        $notifier->songSendFailed($song, $this->chatId, $quality, $error, 'bale', $userId);
        $botUserService->recordFailedSend($userId, 'bale');

        // به کاربر اطلاع بده
        if ($this->messageId) {
            try {
                $baleService->editMessageText(
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

    /**
     * آزادسازی قفل بعد از اتمام پردازش
     */
    private function releaseLock(): void
    {
        if ($this->lockKey) {
            Cache::forget($this->lockKey);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Bale music download job failed', [
            'song_id'    => $this->songId,
            'quality'    => '320',
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId,
            'error'      => $exception->getMessage(),
        ]);

        $this->releaseLock();

        // ✅ نوتیفیکیشن خطای دائمی
        try {
            app(NotificationService::class)->error(
                'جاب دانلود آهنگ بله (شکست دائمی)',
                $exception->getMessage(),
                'bale',
                [
                    'song_id'    => $this->songId,
                    'quality'    => '320',
                    'chat_id'    => $this->chatId,
                    'message_id' => $this->messageId,
                ]
            );
        } catch (Throwable $e) {
            // نادیده
        }

        try {
            $bale = app(BaleService::class);
            if ($this->messageId) {
                $bale->editMessageText(
                    $this->chatId,
                    $this->messageId,
                    '❌ خطا در دانلود. لطفاً دوباره تلاش کنید.',
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

    private function downloadSong(string $url, string $dir): string
    {
        $path = storage_path("app/private/{$dir}/original.mp3");
        $stream = fopen($path, 'w');
        Http::timeout(300)->withOptions(['sink' => $stream])->get($url);
        fclose($stream);
        return $path;
    }

    private function removeMetadata(string $input, string $dir, string $fileName, ?string $customTitle = null): string
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
