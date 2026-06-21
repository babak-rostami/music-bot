<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\TelegramService;
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

    /**
     * حداکثر زمان اجرای Job (ثانیه)
     * چون دانلود آهنگ ممکنه طول بکشه
     */
    public int $timeout = 300; // ۵ دقیقه

    /**
     * تعداد تلاش مجدد در صورت خطا
     */
    public int $tries = 3;

    /**
     * تأخیر بین هر تلاش (ثانیه)
     */
    public int $backoff = 10;

    public function __construct(
        public readonly int $songId,
        public readonly string $quality,
        public readonly int|string $chatId,
        public readonly int $messageId,
    ) {}

    /**
     * 🛡 Middleware: جلوگیری از اجرای همزمان چند جاب برای یک آهنگ
     * (مثلاً اگه ۱۰ کاربر همزمان درخواست بدن، یکی دانلود کنه، بقیه صبر کنن)
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("song-{$this->songId}-{$this->quality}"))
                ->dontRelease()        // ← جاب منتظر میمونه تا لاک آزاد بشه
                ->expireAfter(600)     // ← اگه جاب ۱۰ دقیقه طول کشید، lock expire میشه
        ];
    }

    public function handle(TelegramService $telegram): void
    {
        $song = Song::find($this->songId);

        if (!$song) {
            Log::warning('Song not found in download job', [
                'song_id' => $this->songId,
            ]);
            return;
        }


        // ✅✅✅ اگه دو نفر همزمان بخوان دانلود کنن این آهنگ رو
        // وقتی نفر دوم بعد گذشتن از میدلور این جاب، وارد هندل شد
        //  اینجا از فوروارد استفاده میکنیم براش ✅✅✅
        $fileIdColumn = "telegram_file_id_{$this->quality}";
        $cachedFileId = $song->{$fileIdColumn};
        if ($cachedFileId) {
            // یکی دیگه زحمتش رو کشیده، فقط فوروارد کن
            $telegram->sendAudioByFileId(
                $this->chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($song->lyrics) {
                $telegram->sendMessage(
                    $this->chatId,
                    "🎤 متن آهنگ:\n\n" . $song->lyrics
                );
            }

            $telegram->deleteMessage($this->chatId, $this->messageId);
            return;
        }

        // اگه دونفر همزمان روی دانلود یک آهنگ بزنن
        // برای نفر اول فقط کدای پایین اجرا میشه
        // برای نفر دوم فقط کدای بالا

        $tempDirectory = 'temp/' . Str::uuid();
        Storage::disk('local')->makeDirectory($tempDirectory);

        try {
            // ۱. دانلود
            $downloadUrl  = $this->resolveDownloadUrl($song);
            $fileName     = $this->generateFileName($song);
            $originalFile = $this->downloadSong($downloadUrl, $tempDirectory);

            // ۲. ffmpeg
            $cleanFile = $this->removeMetadata($originalFile, $tempDirectory, $fileName);

            // ۳. آپلود به کانال Cache
            $fileIdColumn = "telegram_file_id_{$this->quality}";
            $cacheResult  = $telegram->uploadAudioToCacheChannel($cleanFile, $song);

            if ($cacheResult && !empty($cacheResult['file_id'])) {
                // کش موفق: file_id ذخیره + ارسال با file_id
                $song->update([
                    $fileIdColumn => $cacheResult['file_id'],
                ]);

                $telegram->sendAudioByFileId(
                    $this->chatId,
                    $cacheResult['file_id'],
                    $song->title,
                    $song->artist_name
                );
            } else {
                // Fallback: کش نشد → مثل قبل مستقیم بفرست
                $telegram->sendAudio(
                    $this->chatId,
                    $cleanFile,
                    $fileName,
                    $song->title,
                    $song->artist_name
                );
            }

            // ۴. متن آهنگ
            if ($song->lyrics) {
                $telegram->sendMessage(
                    $this->chatId,
                    "🎤 متن آهنگ:\n\n" . $song->lyrics
                );
            }

            // ۵. حذف پیام "در حال آماده‌سازی"
            $telegram->deleteMessage($this->chatId, $this->messageId);
        } finally {
            // ۶. همیشه temp رو پاک کن (حتی اگه exception خورد)
            Storage::disk('local')->deleteDirectory($tempDirectory);
        }
    }

    /**
     * 🛡 وقتی همه retryها شکست خوردن، این متد اجرا میشه
     * کاربر رو از خطا مطلع می‌کنیم
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Music download job failed permanently', [
            'song_id'    => $this->songId,
            'quality'    => $this->quality,
            'chat_id'    => $this->chatId,
            'message_id' => $this->messageId,
            'error'      => $exception->getMessage(),
        ]);

        try {
            $telegram = app(TelegramService::class);
            $telegram->editMessageText(
                $this->chatId,
                $this->messageId,
                '❌ خطا در دانلود یا پردازش فایل. لطفاً دوباره تلاش کنید.',
                []
            );
        } catch (Throwable $e) {
            // اگه حتی ارسال پیام خطا هم شکست خورد، فقط log کن
            Log::error('Failed to update error message', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────
    // Helper methods
    // ─────────────────────────────────────────────────

    private function resolveDownloadUrl(Song $song): string
    {
        return $this->quality === '128'
            ? $song->download_link_128
            : $song->download_link_320;
    }

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
            throw new \RuntimeException(
                "ffmpeg failed: " . implode("\n", $lines)
            );
        }

        return $output;
    }
}
