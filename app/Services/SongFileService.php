<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongDownloadLink;
use App\Services\Downloaders\SoundcloudDownloadService;
// use App\Services\Downloaders\SpotifyDownloadService;
use App\Services\Downloaders\YoutubeDownloadService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SongFileService
{
    protected array $qualityPriority = ['320', '128'];

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
     * ترتیب: لینک‌های دانلود ذخیره‌شده → یوتیوب → ساندکلاد → اسپاتیفای
     *
     * @return array{path: string, quality: string, link_id: ?int}|null
     */
    public function downloadBestSong(Song $song, string $tempDirectory): ?array
    {
        return $this->downloadFromStoredLinks($song, $tempDirectory)
            ?? $this->downloadFromExternalSources($song, $tempDirectory);
    }

    /**
     * لینک‌های ثبت‌شده توی جدول song_download_links (همون منطق قبلی)
     */
    protected function downloadFromStoredLinks(Song $song, string $tempDirectory): ?array
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

            $updated = SongDownloadLink::where('id', $link->id)->update(['priority' => 0]);

            Log::warning('دانلود شکست خورد، priority ریست شد', [
                'song_id'       => $song->id,
                'link_id'       => $link->id,
                'quality'       => $link->quality,
                'rows_affected' => $updated,
            ]);
        }

        return null;
    }

    /**
     * وقتی هیچ‌کدام از لینک‌های ذخیره‌شده جواب نداد، آخرین راه‌حل: یوتیوب → ساندکلاد → اسپاتیفای
     */
    protected function downloadFromExternalSources(Song $song, string $tempDirectory): ?array
    {
        $sources = [
            ['column' => 'youtube_link',    'service' => YoutubeDownloadService::class],
            ['column' => 'soundcloud_link', 'service' => SoundcloudDownloadService::class],
            // ['column' => 'spotify_link',    'service' => SpotifyDownloadService::class],
        ];

        $fullPath = storage_path("app/private/{$tempDirectory}");
        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        foreach ($sources as $source) {
            $url = $song->{$source['column']};

            if (empty($url)) {
                continue;
            }

            try {
                $downloader = app($source['service']);
                $path = $downloader->download($url, $fullPath);
            } catch (\Throwable $e) {
                Log::error('External source download exception', [
                    'song_id' => $song->id,
                    'source'  => $source['column'],
                    'error'   => $e->getMessage(),
                ]);
                $path = null;
            }

            if ($path !== null) {
                return [
                    'path'    => $path,
                    'quality' => '320',
                    'link_id' => null,
                ];
            }

            Log::warning('دانلود از منبع خارجی شکست خورد', [
                'song_id' => $song->id,
                'source'  => $source['column'],
            ]);
        }

        return null;
    }

    protected function downloadSong(string $url, string $dir): ?string
    {
        $path = storage_path("app/private/{$dir}/original.mp3");

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path)) {
            @unlink($path);
        }

        $stream = @fopen($path, 'w');

        if ($stream === false) {
            Log::error('نتوانست فایل را برای نوشتن باز کند', ['path' => $path]);
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
                ->connectTimeout(15)
                ->withOptions(['sink' => $stream])
                ->get($url);
        } catch (\Throwable $e) {
            if (is_resource($stream)) {
                @fclose($stream);
            }

            $this->cleanupFailedDownload($path);

            Log::error('خطا در دانلود آهنگ (exception)', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (is_resource($stream)) {
            @fclose($stream);
        }

        if (! isset($response) || ! $response->successful()) {
            $this->cleanupFailedDownload($path);
            Log::warning('دانلود ناموفق - وضعیت نامعتبر', [
                'url'    => $url,
                'status' => isset($response) ? $response->status() : 'no response',
            ]);
            return null;
        }

        if (! file_exists($path) || filesize($path) < 1024) {
            $this->cleanupFailedDownload($path);
            Log::warning('فایل دانلود شده خالی یا نامعتبر است', [
                'url'    => $url,
                'size'   => file_exists($path) ? filesize($path) : 0,
                'exists' => file_exists($path),
            ]);
            return null;
        }

        return $path;
    }

    protected function cleanupFailedDownload(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
