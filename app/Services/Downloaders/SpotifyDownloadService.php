<?php

namespace App\Services\Downloaders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * اسپاتیفای راه رسمی برای دانلود مستقیم فایل صوتی نمی‌ده (استریم‌ها رمزنگاری‌شده‌ست).
 * این سرویس فقط «عنوان + خواننده» رو از صفحه‌ی عمومی اسپاتیفای استخراج می‌کنه،
 * و همون آهنگ رو از یوتیوب جستجو و دانلود می‌کنه — دقیقاً روشی که ابزارهایی مثل spotdl هم استفاده می‌کنن.
 * چون بر پایه‌ی اسکرپ‌کردن صفحه‌ست، اگه اسپاتیفای ساختار صفحه رو عوض کنه ممکنه نیاز به تنظیم مجدد داشته باشه.
 */
class SpotifyDownloadService
{
    public function __construct(private readonly YoutubeDownloadService $youtube) {}

    public function download(string $url, string $tempDir): ?string
    {
        $metadata = $this->fetchMetadata($url);

        if (!$metadata) {
            Log::warning('Spotify metadata extraction failed', ['url' => $url]);
            return null;
        }

        $query = trim($metadata['title'] . ' ' . ($metadata['artist'] ?? ''));

        return $this->downloadFromYoutubeSearch($query, $tempDir);
    }

    /**
     * @return array{title: string, artist: ?string}|null
     */
    protected function fetchMetadata(string $url): ?array
    {
        try {
            $oembedResponse = Http::timeout(15)->get('https://open.spotify.com/oembed', ['url' => $url]);
            $title = $oembedResponse->successful() ? $oembedResponse->json('title') : null;

            if (!$title) {
                return null;
            }

            return ['title' => $title, 'artist' => $this->extractArtistFromPage($url)];
        } catch (\Throwable $e) {
            Log::warning('Spotify oEmbed request failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function extractArtistFromPage(string $url): ?string
    {
        try {
            $html = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url)
                ->body();

            if (preg_match('/<meta property="og:description" content="([^"]*)"/i', $html, $matches)) {
                $description = html_entity_decode($matches[1]);
                $parts = array_map('trim', explode('·', $description));
                return $parts[0] ?? null;
            }
        } catch (\Throwable $e) {
            Log::info('Spotify page artist extraction failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return null;
    }

    protected function downloadFromYoutubeSearch(string $query, string $tempDir): ?string
    {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputTemplate = $tempDir . '/' . Str::uuid() . '.%(ext)s';

        $command = sprintf(
            'yt-dlp --cookies youtube_cookies.txt -x --audio-format mp3 --audio-quality 0 --no-playlist -o %s %s 2>&1',
            escapeshellarg($outputTemplate),
            escapeshellarg('ytsearch1:' . $query . ' audio')
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            Log::warning('yt-dlp (Spotify→YouTube search) download failed', [
                'query'  => $query,
                'output' => $lines,
            ]);
            return null;
        }

        $files = glob("{$tempDir}/*.mp3");
        return $files[0] ?? null;
    }
}
