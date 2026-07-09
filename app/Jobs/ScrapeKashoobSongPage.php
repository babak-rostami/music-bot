<?php

namespace App\Jobs;

use App\Helpers\SongSlugHelper;
use App\Models\Song;
use App\Services\ArtistResolver;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeKashoobSongPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(
        public string $sourceUrl
    ) {}

    public function handle(): void
    {
        // جلوگیری از ذخیره تکراری
        if (Song::where('source_url', $this->sourceUrl)->exists()) {
            Log::info("Song already exists, skipping: {$this->sourceUrl}");
            return;
        }

        Log::info("Start ScrapeKashoobSongPage: {$this->sourceUrl}");

        try {
            $html = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                ])
                ->timeout(60)
                ->get($this->sourceUrl)
                ->body();

            $crawler = new Crawler($html);

            // ---- 1. Title از h1 ----
            $title = '';
            try {
                $title = trim($crawler->filter('h1')->text());
            } catch (\Exception $e) {
                Log::warning("No h1 found in: {$this->sourceUrl}");
                return;
            }

            // حذف پیشوند "نماهنگ " از عنوان
            $cleanTitle = $title;
            if (mb_strpos($cleanTitle, 'نماهنگ ') === 0) {
                $cleanTitle = mb_substr($cleanTitle, mb_strlen('نماهنگ '));
            }
            $cleanTitle = trim($cleanTitle);

            // ---- 2. Artist Name ----
            $artistName = '';
            try {
                $artistName = trim($crawler->filter('.subtitle')->attr('data-person'));
            } catch (\Exception $e) {
                Log::warning("No artist found in: {$this->sourceUrl}");
            }
            if (!$artistName) {
                return;
            }

            // ---- 3. Slug ----
            $slug = SongSlugHelper::make($cleanTitle . ' ' . $artistName);

            // ---- 4. Download Links ----
            $downloadLink128 = null;
            $downloadLink320 = null;

            preg_match_all(
                '/<div\s+class="download-option"[^>]*\s+@click="downloadByUrl\(\'([^\']+)\'[^"]*"[^>]*>(.*?)<\/div>/si',
                $html,
                $matches
            );

            if (!empty($matches[0])) {
                foreach ($matches[0] as $idx => $fullMatch) {
                    $rawUrl = $matches[1][$idx] ?? '';
                    $innerHtml = $matches[2][$idx] ?? '';

                    if (empty($rawUrl)) {
                        continue;
                    }

                    // حذف query string (?name=...)
                    $cleanUrl = strtok($rawUrl, '?');

                    if (
                        empty($downloadLink128) &&
                        (mb_strpos($innerHtml, '128') !== false || mb_strpos($innerHtml, '۱۲۸') !== false)
                    ) {
                        $downloadLink128 = $cleanUrl;
                    }

                    if (
                        empty($downloadLink320) &&
                        (mb_strpos($innerHtml, '320') !== false || mb_strpos($innerHtml, '۳۲۰') !== false)
                    ) {
                        $downloadLink320 = $cleanUrl;
                    }
                }
            }

            // ---- 5. Lyrics ----
            $lyrics = $this->extractLyrics($crawler);

            // ---- 6. Save ----
            $song = Song::create([
                'title'            => $cleanTitle,
                'slug'             => $slug,
                'artist_name'      => $artistName,
                'source_url'       => $this->sourceUrl,
                'lyrics'           => $lyrics,
                'download_link_128' => $downloadLink128,
                'download_link_320' => $downloadLink320,
            ]);

            Elasticsearch::sync($song);
            ArtistResolver::attachSingleArtist($song);

            Log::info("Saved song: {$cleanTitle} - {$artistName}");
        } catch (\Exception $e) {
            Log::error("Error ScrapeKashoobSongPage: " . $e->getMessage(), [
                'url' => $this->sourceUrl
            ]);
            $this->release(60);
        }
    }

    private function extractLyrics(Crawler $crawler): ?string
    {
        try {
            $paragraphs = $crawler->filter('div.body-content > p');
        } catch (\Exception $e) {
            return null;
        }

        $lyricLines = [];
        $isFirstP = true;

        $paragraphs->each(function (Crawler $node) use (&$lyricLines, &$isFirstP) {
            // پاراگراف اول که معرفی هست رو حذف کن
            if ($isFirstP) {
                $isFirstP = false;
                return;
            }

            // استفاده از html() برای حفظ <br> ها
            $innerHtml = $node->html();
            // تبدیل <br> به خط جدید
            $innerHtml = str_ireplace(['<br>', '<br/>', '<br />'], "\n", $innerHtml);
            // حذف بقیه تگ‌ها
            $innerHtml = strip_tags($innerHtml);
            // جدا کردن خطوط
            $lines = preg_split('/\r\n|\r|\n/', $innerHtml);

            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $lyricLines[] = $line;
                }
            }
        });

        return empty($lyricLines) ? null : implode("\n", $lyricLines);
    }
}
