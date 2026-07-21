<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongDownloadLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SongFileService
{
    /**
     * لیست کیفیت‌ها به ترتیب اولویت (از بالا به پایین)
     */
    protected array $qualityPriority = ['320', '128'];

    /**
     * گرفتن فایل آیدی کش شده برای پلتفرم مشخص
     * اول کیفیت 320 رو چک می‌کنه، بعد 128
     *
     * @param  string  $platform  مثلا telegram یا bale
     * @return array{file_id: string, fileIdColumn: string, quality: string}|null
     */
    public function getCachedFileId(Song $song, string $platform): ?array
    {
        foreach ($this->qualityPriority as $quality) {
            $column = "{$platform}_file_id_{$quality}";

            if (! array_key_exists($column, $song->getAttributes())) {
                continue;
            }

            $fileId = $song->{$column};

            if (! empty($fileId)) {
                return [
                    'file_id'      => $fileId,
                    'fileIdColumn' => $column,
                    'quality'      => $quality,
                ];
            }
        }

        return null;
    }

    /**
     * دانلود آهنگ از بهترین لینک موجود
     * اول کیفیت 320 رو امتحان می‌کنه بعد 128
     * هر بار که یه لینک موفق دانلود بشه، priority اش 1 دونه زیاد میشه
     * اگه دانلود شکست بخوره، priority اون لینک 0 میشه (ریست)
     *
     * @param  string  $tempDirectory  مثلا "temp/abc-123"
     * @return array{path: string, quality: string, link_id: int}|null
     */
    public function downloadBestSong(Song $song, string $tempDirectory): ?array
    {
        $links = $song->downloadLinks()
            ->get(['id', 'quality', 'download_url', 'priority'])
            ->sortBy([
                ['quality', 'desc'],
                ['priority', 'desc'],
            ])
            ->values();

        if ($links->isEmpty()) {
            return null;
        }

        $fullPath = storage_path("app/private/{$tempDirectory}");
        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        foreach ($links as $link) {
            try {
                $result = $this->downloadSong($link->download_url, $tempDirectory);
            } catch (\Throwable $e) {
                Log::error('downloadSong exception', [
                    'link_id' => $link->id,
                    'error'   => $e->getMessage(),
                ]);
                $result = null;
            }

            if ($result !== null) {
                $link->increment('priority');

                return [
                    'path'    => $result,
                    'quality' => $link->quality,
                    'link_id' => $link->id,
                ];
            }

            // شکست خورد → priority رو ریست کن به 0
            // مستقیم از query builder استفاده می‌کنیم تا مطمئن بشیم آپدیت میشه
            $updated = SongDownloadLink::where('id', $link->id)
                ->update(['priority' => 0]);

            Log::warning('دانلود شکست خورد، priority ریست شد', [
                'song_id'         => $song->id,
                'link_id'         => $link->id,
                'quality'         => $link->quality,
                'rows_affected'   => $updated
            ]);
        }

        return null;
    }

    /**
     * دانلود واقعی فایل از یه URL
     * اگه موفق بود مسیر فایل رو برمی‌گردونه
     * اگه شکست خورد null برمی‌گردونه
     */
    protected function downloadSong(string $url, string $dir): ?string
    {
        $path = storage_path("app/private/{$dir}/original.mp3");

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // فایل قدیمی رو پاک کن اگه هست
        if (file_exists($path)) {
            @unlink($path);
        }

        $stream = @fopen($path, 'w');

        if ($stream === false) {
            Log::error('نتوانست فایل را برای نوشتن باز کند', [
                'path' => $path,
            ]);
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                ])
                ->timeout(300)
                ->connectTimeout(15)  // اتصال اولیه 15 ثانیه
                ->withOptions(['sink' => $stream])
                ->get($url);
        } catch (\Throwable $e) {
            // stream رو اگه هنوز بازه ببند
            if (is_resource($stream)) {
                @fclose($stream);
            }

            $this->cleanupFailedDownload($path);

            Log::error('خطا در دانلود آهنگ (exception)', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // stream رو بعد از request ببند (حتی اگه Guzzle خودش بسته باشه)
        if (is_resource($stream)) {
            @fclose($stream);
        }

        // چک وضعیت response
        if (! isset($response) || ! $response->successful()) {
            $this->cleanupFailedDownload($path);
            Log::warning('دانلود ناموفق - وضعیت نامعتبر', [
                'url'    => $url,
                'status' => isset($response) ? $response->status() : 'no response',
            ]);
            return null;
        }

        // چک کن فایل واقعا دانلود شده و خالی نیست
        if (! file_exists($path) || filesize($path) < 1024) {
            $this->cleanupFailedDownload($path);
            Log::warning('فایل دانلود شده خالی یا نامعتبر است', [
                'url'      => $url,
                'size'     => file_exists($path) ? filesize($path) : 0,
                'exists'   => file_exists($path),
            ]);
            return null;
        }

        return $path;
    }

    /**
     * پاک کردن فایل دانلود نشده
     */
    protected function cleanupFailedDownload(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
