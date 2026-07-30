<?php

namespace App\Services\Crawlers\Bia2Music;

use App\Helpers\SongSlugHelper;
use App\Models\Song;
use App\Models\SongDownloadLink;
use App\Services\ArtistResolver;
use App\Services\Crawlers\Bia2Music\Data\Bia2MusicSongData;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;
use App\Jobs\Crawlers\Bia2Music\CrawlBia2MusicSongJob;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class Bia2MusicCrawlerService
{
    public const PROVIDER = 'mybia2music.com';

    public const BASE_URL = 'https://www.mybia2music.com';

    public const FIRST_PAGE_URL = 'https://www.mybia2music.com/category/mp3';

    public const LAST_PAGE = 2697;

    private const BIA2MUSIC_COOLDOWN_CACHE_KEY = 'bia2music:crawler:cooldown_until';

    /**
     * اگر داخل صفحه آهنگ یکی از این genre ها وجود داشت، آهنگ ذخیره نشود.
     */
    private const IGNORED_GENRE_URLS = [
        'https://www.mybia2music.com/genre/ai',
        'https://www.mybia2music.com/genre/psycho',
        'https://www.mybia2music.com/genre/gym-musics',
        'https://www.mybia2music.com/genre/hang-drum-musics',
        'https://www.mybia2music.com/genre/spanish-music',
        'https://www.mybia2music.com/genre/meditation-musics',
        'https://www.mybia2music.com/genre/tango-dance-musics',
        'https://www.mybia2music.com/genre/yuga-musics',
        'https://www.mybia2music.com/genre/instagram-turkey-music-trend',
        'https://www.mybia2music.com/genre/korean-musics',
        'https://www.mybia2music.com/genre/ahang-lalaei',
        'https://www.mybia2music.com/genre/ahange-raghse-chagho',
        'https://www.mybia2music.com/genre/ahang-aroosi-shad-ghadimi',
        'https://www.mybia2music.com/genre/babakaram-dance-musics',
        'https://www.mybia2music.com/genre/arabian-long-remix',
        'https://www.mybia2music.com/genre/japanese-music',
        'https://www.mybia2music.com/genre/nostalgia-musics',
        'https://www.mybia2music.com/genre/ahange-kooche-bazari',
        'https://www.mybia2music.com/genre/ahang-ambulancei',
        'https://www.mybia2music.com/genre/tribal-musics',
        'https://www.mybia2music.com/genre/chapi-sarakhsi-musics',
        'https://www.mybia2music.com/genre/music-lati',
        'https://www.mybia2music.com/genre/download-bass-music',
        'https://www.mybia2music.com/genre/death-musics',
        'https://www.mybia2music.com/genre/arabian-musics',
        'https://www.mybia2music.com/genre/russian-remix',
        'https://www.mybia2music.com/genre/french-music',
        'https://www.mybia2music.com/genre/turkish-dance-music',
        'https://www.mybia2music.com/genre/special-songs-not-heard',
    ];

    public function crawlPage(int $page): int
    {
        $pageUrl = $this->makePageUrl($page);

        $crawler = $this->makeCrawlerFromUrl($pageUrl);

        $articles = $crawler->filter('.latest_holder article.item');

        Log::info('Bia2Music page articles parsed.', [
            'page' => $page,
            'url' => $pageUrl,
            'articles_count' => $articles->count(),
        ]);

        if ($articles->count() === 0) {
            Log::warning('Bia2Music page has no articles.', [
                'page' => $page,
                'url' => $pageUrl,
                'title' => $crawler->filter('title')->count() ? $crawler->filter('title')->text('') : null,
                'body_sample' => $crawler->filter('body')->count()
                    ? mb_substr($crawler->filter('body')->text(''), 0, 500)
                    : null,
            ]);

            throw new RuntimeException("Bia2Music page has no articles. Page: {$page}");
        }

        $dispatchedCount = 0;
        $delaySeconds = 0;

        $articles->each(function (Crawler $article) use (&$dispatchedCount, &$delaySeconds, $page) {
            try {
                $listItemData = $this->parseSongFromListArticle($article);

                if (!$listItemData) {
                    Log::warning('Bia2Music list article parse failed.', [
                        'page' => $page,
                    ]);

                    return;
                }

                CrawlBia2MusicSongJob::dispatch(
                    sourceUrl: $listItemData['source_url'],
                    title: $listItemData['title'],
                    artistName: $listItemData['artist_name'],
                )
                    ->onQueue('bia2music-crawler')
                    ->delay(now()->addSeconds($delaySeconds));

                $delaySeconds += random_int(5, 10);

                $dispatchedCount++;
            } catch (Throwable $exception) {
                Log::warning('Bia2Music dispatch song job failed.', [
                    'page' => $page,
                    'message' => $exception->getMessage(),
                ]);
            }
        });

        Log::info('Bia2Music page song jobs dispatched.', [
            'page' => $page,
            'dispatched_count' => $dispatchedCount,
        ]);

        return $dispatchedCount;
    }

    public function crawlAndStoreSong(string $sourceUrl, string $title, string $artistName): ?Song
    {
        $songData = $this->crawlSongPage(
            sourceUrl: $sourceUrl,
            title: $title,
            artistName: $artistName,
        );

        if (!$songData) {
            return null;
        }

        return $this->storeSongData($songData);
    }

    public function crawlOnlyLatestPage(): int
    {
        return $this->crawlPage(1);
    }

    public function makePageUrl(int $page): string
    {
        if ($page <= 1) {
            return self::FIRST_PAGE_URL;
        }

        return self::FIRST_PAGE_URL . '/page/' . $page;
    }

    private function makeCrawlerFromUrl(string $musicUrl): Crawler
    {
        sleep(random_int(5, 10));

        $response = Http::withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
            ])
            ->withOptions([
                'allow_redirects' => true,
            ])
            ->connectTimeout(20)
            ->timeout(60)
            ->retry(2, 3000)
            ->get($musicUrl);

        $html = $response->body();

        Log::info('Bia2Music HTTP response.', [
            'url' => $musicUrl,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_length' => strlen($html),
            'title' => $this->extractHtmlTitleForLog($html),
            'is_bot_verification' => $this->isBotVerificationHtml($html),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException("Bia2Music request failed. URL: {$musicUrl}, Status: {$response->status()}");
        }

        if (trim($html) === '') {
            throw new RuntimeException("Bia2Music empty response. URL: {$musicUrl}");
        }

        if ($this->isBotVerificationHtml($html)) {
            throw new RuntimeException("Bia2Music bot verification page returned. URL: {$musicUrl}");
        }

        return new Crawler($html, $musicUrl);
    }

    private function extractHtmlTitleForLog(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return null;
    }

    private function isBotVerificationHtml(string $html): bool
    {
        return str_contains($html, '<title>Bot Verification</title>')
            || str_contains($html, 'Verifying that you are not a robot')
            || str_contains($html, 'not a robot');
    }

    private function parseSongFromListArticle(Crawler $article): ?array
    {
        $linkNode = $article->filter('header.title h2 a.singlepost');

        if ($linkNode->count() === 0) {
            return null;
        }

        $sourceUrl = $this->cleanText($linkNode->attr('href') ?? '');

        $artistName = $this->nodeText($linkNode, 'span.txt');
        $title = $this->nodeText($linkNode, 'span.songname');

        if (!$sourceUrl || !$artistName || !$title) {
            return null;
        }

        return [
            'source_url' => $sourceUrl,
            'artist_name' => $artistName,
            'title' => $title,
        ];
    }

    private function crawlSongPage(string $sourceUrl, string $title, string $artistName): ?Bia2MusicSongData
    {
        $crawler = $this->makeCrawlerFromUrl($sourceUrl);

        if ($this->hasIgnoredGenre($crawler)) {
            Log::info('Bia2Music song ignored by genre.', [
                'source_url' => $sourceUrl,
                'title' => $title,
                'artist_name' => $artistName,
            ]);

            return null;
        }

        $downloadLink128 = $this->extractDownloadLinkByQuality($crawler, '128');
        $downloadLink320 = $this->extractDownloadLinkByQuality($crawler, '320');

        if (!$downloadLink128 && !$downloadLink320) {
            Log::warning('Bia2Music song page has no download links.', [
                'source_url' => $sourceUrl,
                'title' => $title,
                'artist_name' => $artistName,
                'page_title' => $crawler->filter('title')->count() ? $crawler->filter('title')->text('') : null,
                'body_sample' => $crawler->filter('body')->count()
                    ? mb_substr($crawler->filter('body')->text(''), 0, 500)
                    : null,
            ]);

            throw new RuntimeException("Bia2Music song page has no download links. URL: {$sourceUrl}");
        }

        $lyrics = $this->extractLyrics($crawler);

        if ($this->hasLyricsBox($crawler) && !$lyrics) {
            Log::warning('Bia2Music lyrics box exists but lyrics is empty.', [
                'source_url' => $sourceUrl,
                'title' => $title,
                'artist_name' => $artistName,
            ]);

            throw new RuntimeException("Bia2Music lyrics box exists but lyrics is empty. URL: {$sourceUrl}");
        }

        $coverPath = $this->extractCoverPath($crawler);

        if (!$coverPath && !$crawler->filter('div.downloadbox')->count()) {
            throw new RuntimeException("Bia2Music invalid or incomplete song page. URL: {$sourceUrl}");
        }

        return new Bia2MusicSongData(
            title: $title,
            artistName: $artistName,
            sourceUrl: $sourceUrl,
            lyrics: $lyrics,
            coverPath: $coverPath,
            downloadLink128: $downloadLink128,
            downloadLink320: $downloadLink320,
        );
    }

    private function hasLyricsBox(Crawler $crawler): bool
    {
        return $crawler->filter('div.lyrics')->count() > 0;
    }

    private function hasIgnoredGenre(Crawler $crawler): bool
    {
        foreach (self::IGNORED_GENRE_URLS as $ignoredGenreUrl) {
            $matched = $crawler->filter('a[href="' . $ignoredGenreUrl . '"]')->count() > 0;

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private function extractLyrics(Crawler $crawler): ?string
    {
        $lyricsBox = $crawler->filter('div.lyrics');

        if ($lyricsBox->count() === 0) {
            return null;
        }

        $lines = [];

        $lyricsBox->filter('p')->each(function (Crawler $paragraph) use (&$lines) {
            $html = $paragraph->html('');

            // تبدیل br به خط جدید
            $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

            $text = strip_tags($html);

            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);

            // فاصله‌های اضافه هر خط را تمیز کن، ولی newline را حفظ کن
            $paragraphLines = preg_split('/\r\n|\r|\n/', $text);

            foreach ($paragraphLines as $line) {
                $line = trim(preg_replace('/[ \t]+/u', ' ', $line));

                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        });

        if (empty($lines)) {
            return null;
        }

        return implode("\n", $lines);
    }

    private function extractCoverPath(Crawler $crawler): ?string
    {
        $coverLink = $crawler->filter('div.cover > a');

        if ($coverLink->count() > 0) {
            $href = $this->cleanText($coverLink->attr('href') ?? '');

            if ($href !== '') {
                return $href;
            }
        }

        $coverImage = $crawler->filter('div.cover > a > img');

        if ($coverImage->count() > 0) {
            $src = $this->cleanText($coverImage->attr('src') ?? '');

            if ($src !== '') {
                return $src;
            }
        }

        return null;
    }

    private function extractDownloadLinkByQuality(Crawler $crawler, string $quality): ?string
    {
        $downloadBox = $crawler->filter('div.downloadbox');

        if ($downloadBox->count() === 0) {
            return null;
        }

        $foundUrl = null;

        $downloadBox->filter('a')->each(function (Crawler $link) use ($quality, &$foundUrl) {
            if ($foundUrl !== null) {
                return;
            }

            $label = $this->cleanText($link->filter('i')->count() ? $link->filter('i')->text('') : $link->text(''));
            $href = $this->cleanText($link->attr('href') ?? '');

            if ($href === '') {
                return;
            }

            if (str_contains($label, $quality)) {
                $foundUrl = $href;
            }
        });

        return $foundUrl;
    }

    private function storeSongData(Bia2MusicSongData $data): Song
    {
        $song = Song::query()
            ->where('title', $data->title)
            ->where('artist_name', $data->artistName)
            ->first();
        if (!$song) {
            $song = Song::where('source_url', $data->sourceUrl)->first();
        }

        if (!$song) {
            $song = Song::query()->create([
                'title' => $data->title,
                'artist_name' => $data->artistName,
                'source_url' => $data->sourceUrl,
                'lyrics' => $data->lyrics,
                'cover_path' => $data->coverPath,
                'download_link_128' => $data->downloadLink128,
                'download_link_320' => $data->downloadLink320,
                'slug' => SongSlugHelper::make($data->title . ' ' . $data->artistName)
            ]);
            Elasticsearch::sync($song);
            ArtistResolver::attachSingleArtist($song);
        } else {
            $this->fillMissingSongFields($song, $data);
        }

        $this->storeDownloadLink($song, '320', $data->downloadLink320);
        $this->storeDownloadLink($song, '128', $data->downloadLink128);

        return $song;
    }

    public function getBia2MusicCooldownSeconds(): ?int
    {
        $cooldownUntil = Cache::get(self::BIA2MUSIC_COOLDOWN_CACHE_KEY);

        if (!$cooldownUntil) {
            return null;
        }

        $seconds = ((int) $cooldownUntil) - time();

        if ($seconds <= 0) {
            Cache::forget(self::BIA2MUSIC_COOLDOWN_CACHE_KEY);

            return null;
        }

        return $seconds;
    }

    public function activateBia2MusicCooldown(int $seconds): void
    {
        Cache::put(
            self::BIA2MUSIC_COOLDOWN_CACHE_KEY,
            time() + $seconds,
            $seconds + 60
        );
    }

    private function fillMissingSongFields(Song $song, Bia2MusicSongData $data): void
    {
        $updates = [];
        $update_song_lyrics = false;

        if (!$song->source_url || ($song->source_url && $song->source_url !== $data->sourceUrl)) {
            if ($song->download_link_128) {
                SongDownloadLink::query()->firstOrCreate(
                    [
                        'provider' => 'rozmusic.com',
                        'download_url' => $song->download_link_128,
                    ],
                    [
                        'song_id' => $song->id,
                        'quality' => '128',
                    ]
                );
            }
            if ($song->download_link_320) {
                SongDownloadLink::query()->firstOrCreate(
                    [
                        'provider' => 'rozmusic.com',
                        'download_url' => $song->download_link_320,
                    ],
                    [
                        'song_id' => $song->id,
                        'quality' => '320',
                    ]
                );
            }

            $updates['download_link_128'] = $data->downloadLink128;
            $updates['download_link_320'] = $data->downloadLink320;
            $updates['source_url'] = $data->sourceUrl;
        }

        if (!$song->lyrics && $data->lyrics) {
            $update_song_lyrics = true;
            $updates['lyrics'] = $data->lyrics;
        }

        if (!$song->cover_path && $data->coverPath) {
            $updates['cover_path'] = $data->coverPath;
        }

        if (!empty($updates)) {
            $song->update($updates);
            Log::info('song updated.', [
                'title' => $song->title . ' ' . $song->artist_name,
            ]);
            if ($update_song_lyrics) {
                Elasticsearch::update($song, [
                    'lyrics' => $data->lyrics
                ]);
            }
        }
    }

    private function storeDownloadLink(Song $song, string $quality, ?string $downloadUrl): void
    {
        if (!$downloadUrl) {
            return;
        }

        SongDownloadLink::query()->firstOrCreate(
            [
                'provider' => self::PROVIDER,
                'download_url' => $downloadUrl,
            ],
            [
                'song_id' => $song->id,
                'quality' => $quality,
            ]
        );
    }

    private function nodeText(Crawler $crawler, string $selector): string
    {
        $node = $crawler->filter($selector);

        if ($node->count() === 0) {
            return '';
        }

        return $this->cleanText($node->first()->text(''));
    }

    private function cleanText(?string $text): string
    {
        $text = $text ?? '';

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);

        $text = preg_replace('/[ \t]+/u', ' ', $text);

        $text = preg_replace("/\r\n|\r/u", "\n", $text);

        return trim($text);
    }
}
