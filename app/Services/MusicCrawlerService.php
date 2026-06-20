<?php

namespace App\Services;

use App\Jobs\CrawlLastMusicJob;
use App\Jobs\CrawlMusicJob;
use App\Models\Song;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class MusicCrawlerService
{
    public function startAll()
    {
        for ($i = 1; $i <= 1815; $i++) {
            CrawlMusicJob::dispatch($i)->onQueue('crawl-musics-page');
        }
    }
    public function startLast()
    {
        // آهنگ های دو تا صفحه آخر رو چک کن
        for ($i = 1; $i <= 2; $i++) {
            $this->startCrawlLastPages($i);
        }
    }

    public function startCrawlPages($page_number)
    {
        $results = $this->crawlPage($page_number);
        if (empty($results)) {
            return;
        }
        foreach ($results as $music_page) {
            if (!$this->storeSongInDataBase($music_page)) {
                return;
            }
        }
    }

    public function startCrawlLastPages($page_number)
    {
        $results = $this->crawlPage($page_number);
        if (empty($results)) {
            return;
        }
        foreach ($results as $music_page_url) {
            $this->storeLastSongInDataBase($music_page_url);
        }
    }

    private function storeSongInDataBase($music_page): bool
    {
        $music = $this->getMusicInfo($music_page);

        if (!$music || !$music['musicName']) {
            return true;
        }

        Song::firstOrCreate(
            ['source_url' => $music['musicUrl']],
            [
                'title' => $music['musicName'],
                'artist_name' => $music['singer'],
                'lyrics' => $music['lyrics'],
                'download_link_128' => $music['dl_link_128'],
                'download_link_320' => $music['dl_link_320'],
            ]
        );

        return true;
    }

    private function storeLastSongInDataBase($music_page)
    {
        $music = $this->getMusicInfo($music_page);

        if (!$music || !$music['musicName']) {
            return;
        }
        $song = Song::firstOrCreate(
            ['source_url' => $music['musicUrl']],
            [
                'title' => $music['musicName'],
                'artist_name' => $music['singer'],
                'lyrics' => $music['lyrics'],
                'download_link_128' => $music['dl_link_128'],
                'download_link_320' => $music['dl_link_320'],
            ]
        );

        if ($song->wasRecentlyCreated) {
            Elasticsearch::sync($song);
        }

        return true;
    }

    private function getMusicInfo($musicUrl)
    {
        try {
            $html = Http::withoutVerifying()
                ->get($musicUrl)
                ->body();
            usleep(500000);

            $crawler = new Crawler($html);

            $title = $crawler
                ->filter('h1.title a')
                ->text();

            preg_match(
                '/دانلود آهنگ (.*?) به نام (.*)/u',
                $title,
                $matches
            );

            $lyrics = '';

            $node = $crawler->filter('.matnmusic .text p')->first();

            if ($node->count() > 0) {

                $lyricsHtml = $node->html();

                $lyrics = str_replace(
                    ['<br>', '<br/>', '<br />'],
                    "",
                    $lyricsHtml
                );
                $lyrics = str_replace("\u{200C}", ' ', $lyrics);
            }

            $dl_link_320 = null;
            $dl_link_128 = null;

            $crawler
                ->filter('div.dl a')
                ->each(function ($node) use (&$dl_link_320, &$dl_link_128) {

                    $text = trim($node->text());

                    if ($text === 'دانلود آهنگ با کیفیت 320') {
                        $dl_link_320 = $node->attr('href');
                    }

                    if ($text === 'دانلود آهنگ با کیفیت 128') {
                        $dl_link_128 = $node->attr('href');
                    }
                });

            // اگر هیچ لینک دانلودی پیدا نشد
            if (!$dl_link_320 && !$dl_link_128) {
                return null;
            }

            return [
                'musicUrl' => $musicUrl,
                'musicName' => trim($matches[2] ?? null),
                'singer' => trim($matches[1] ?? null),
                'lyrics' => $lyrics,
                'dl_link_320' => $dl_link_320,
                'dl_link_128' => $dl_link_128,
            ];
        } catch (\Throwable $e) {

            logger()->error('Music crawl failed', [
                'url' => $musicUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function crawlPage($page = 0)
    {
        try {
            $url = 'https://rozmusic.com/page/' . $page;

            $html = Http::withoutVerifying()
                ->get($url)
                ->body();

            usleep(1000000);

            $crawler = new Crawler($html);

            $results = [];

            $crawler
                ->filter('article h2.title a')
                ->each(function ($node) use (&$results) {

                    $results[] = $node->attr('href');
                });

            return $results;
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
