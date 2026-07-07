<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Song;
use App\Services\BaleService;
use App\Services\BotUserService;
use App\Services\NotificationService;
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
        public readonly string $quality,
        public readonly int|string $chatId,
        public readonly int|null $messageId,
        public readonly ?string $lockKey = null, // ✅ کلید قفل برای آزادسازی بعد از اتمام
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("bale-song-{$this->songId}-{$this->quality}"))
                ->dontRelease()
                ->expireAfter(600),
        ];
    }

    /**
     * @param BaleService         $bale
     * @param NotificationService $notifier
     * @param BotUserService      $botUserService
     */
    public function handle(
        BaleService $bale,
        NotificationService $notifier,
        BotUserService $botUserService,
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

        $fileIdColumn = "bale_file_id_{$this->quality}";
        $cachedFileId = $song->{$fileIdColumn};

        // ─────────────────────────────────────────────────
        // تلاش ۱: استفاده از file_id کش شده
        // ─────────────────────────────────────────────────
        if ($cachedFileId) {
            $response = $bale->sendAudioByFileId(
                $this->chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                $song->increment('downloads_count');
                $this->onSendSuccess($bale, $notifier, $botUserService, $song, $userId);
                $this->releaseLock();
                return;
            }

            // ❌ file_id نامعتبر
            $this->onSendFailure(
                $response,
                $notifier,
                $botUserService,
                $song,
                $userId,
                'file_id کش‌شده نامعتبر'
            );

            // اگر کاربر بلاک کرده → ادامه‌ی تلاش بی‌فایده است
            if (BotUserService::isBlockedError($response)) {
                $this->releaseLock();
                return;
            }

            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // تلاش ۲: دانلود + آپلود + ارسال
        // ─────────────────────────────────────────────────
        $tempDirectory = 'temp/' . Str::uuid();
        Storage::disk('local')->makeDirectory($tempDirectory);

        try {
            $downloadUrl = $this->resolveDownloadUrl($song);
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
                $this->onSendSuccess($bale, $notifier, $botUserService, $song, $userId);
            } else {
                $this->onSendFailure(
                    $response,
                    $notifier,
                    $botUserService,
                    $song,
                    $userId,
                    $response->json('description', 'خطای ناشناخته')
                );
            }
        } finally {
            Storage::disk('local')->deleteDirectory($tempDirectory);
            $this->releaseLock();
        }
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
    ): void {
        if ($song->lyrics) {
            $bale->sendMessage($this->chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
        }
        if ($this->messageId) {
            $bale->deleteMessage($this->chatId, $this->messageId);
        }

        // ✅ نوتیفیکیشن + ریست شمارش خطا
        $notifier->songSent($song, $this->chatId, $this->quality, 'bale', $userId);
        $botUserService->recordSuccessfulSend($userId, 'bale');
    }

    // ─────────────────────────────────────────────────
    // Helper: ارسال ناموفق
    // ─────────────────────────────────────────────────

    private function onSendFailure(
        $response,
        NotificationService $notifier,
        BotUserService $botUserService,
        Song $song,
        int $userId,
        string $error,
    ): void {
        // ❌ نوتیفیکیشن خطا + ثبت خطای ارسال
        $notifier->songSendFailed($song, $this->chatId, $this->quality, $error, 'bale', $userId);
        $botUserService->recordFailedSend($userId, 'bale');

        // به کاربر اطلاع بده
        if ($this->messageId) {
            $bale = app(BaleService::class);
            try {
                $bale->editMessageText(
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
            'quality'    => $this->quality,
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
                    'quality'    => $this->quality,
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

    private function resolveDownloadUrl(Song $song): string
    {
        return $this->quality === '128'
            ? $song->download_link_128
            : $song->download_link_320;
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

    private function removeMetadata(string $input, string $dir, string $fileName): string
    {
        $output = storage_path("app/private/{$dir}/{$fileName}");

        exec(
            "ffmpeg -i " . escapeshellarg($input)
                . " -map 0:a -c copy -map_metadata -1 "
                . escapeshellarg($output)
                . " -y 2>&1",
            $lines,
            $exit
        );

        if ($exit !== 0) {
            throw new \RuntimeException("ffmpeg failed: " . implode("\n", $lines));
        }

        return $output;
    }
}
