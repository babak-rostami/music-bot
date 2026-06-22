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

    public int $timeout = 300; // ۵ دقیقه
    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $songId,
        public readonly string $quality,
        public readonly int|string $chatId,
        public readonly int $messageId,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("song-{$this->songId}-{$this->quality}"))
                ->dontRelease()
                ->expireAfter(600)
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

        $fileIdColumn = "telegram_file_id_{$this->quality}";

        // ─────────────────────────────────────────────────
        // تلاش ۱: استفاده از file_id کش شده
        // ─────────────────────────────────────────────────
        $cachedFileId = $song->{$fileIdColumn};

        if ($cachedFileId) {
            $response = $telegram->sendAudioByFileId(
                $this->chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                // ✅ کش موفق
                $this->sendLyricsAndCleanup($telegram, $song);
                return;
            }

            // ❌ file_id کش شده کار نکرد (شاید پیام از کانال کش پاک شده یا file_id عوض شده)
            Log::warning('Cached file_id invalid, will re-upload', [
                'song_id'    => $this->songId,
                'quality'    => $this->quality,
                'error'      => $response->json('description'),
            ]);

            // file_id بی‌اعتبار رو پاک کن
            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // تلاش ۲: دانلود + آپلود به کش + ارسال
        // ─────────────────────────────────────────────────
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
            $cacheResult = $telegram->uploadAudioToCacheChannel($cleanFile, $song);

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
                // Fallback: کش نشد → مستقیم بفرست
                Log::warning('Cache upload failed, sending directly', [
                    'song_id' => $this->songId,
                ]);

                $telegram->sendAudio(
                    $this->chatId,
                    $cleanFile,
                    $fileName,
                    $song->title,
                    $song->artist_name
                );
            }

            // ۴. متن آهنگ + حذف پیام "در حال آماده‌سازی"
            $this->sendLyricsAndCleanup($telegram, $song);
        } finally {
            // ۵. همیشه temp رو پاک کن
            Storage::disk('local')->deleteDirectory($tempDirectory);
        }
    }

    /**
     * ارسال متن آهنگ (اگه داشته باشه) + حذف پیام "در حال آماده‌سازی"
     */
    private function sendLyricsAndCleanup(TelegramService $telegram, Song $song): void
    {
        if ($song->lyrics) {
            $telegram->sendMessage(
                $this->chatId,
                "🎤 متن آهنگ:\n\n" . $song->lyrics
            );
        }

        $telegram->deleteMessage($this->chatId, $this->messageId);
    }

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
