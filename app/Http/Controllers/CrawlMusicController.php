<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class CrawlMusicController extends Controller
{
    public function crawl()
    {
        dd('salam docker');
        for ($i = 0; $i <= 1811; $i++) {
            $results = $this->crawlPage($i);
            foreach ($results as $music_page) {
                if (!$this->storeSongInDataBase($music_page)) {
                    return;
                }
            }
        }
    }

    private function storeSongInDataBase($music_page): bool
    {
        $music = $this->getMusicInfo($music_page);

        if (!$music['musicName']) {
            return true;
        }

        if (Song::where('source_url', $music['musicUrl'])->exists()) {
            return false;
        }

        Song::create([
            'source_url' => $music['musicUrl'],
            'title' => $music['musicName'],
            'artist_name' => $music['singer'],
            'lyrics' => $music['lyrics'],
            'download_link_128' => $music['dl_link_128'],
            'download_link_320' => $music['dl_link_320'],
        ]);

        return true;
    }

    private function getMusicInfo($musicUrl)
    {
        $html = Http::withoutVerifying()
            ->get($musicUrl)
            ->body();

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
            'musicName' => trim($matches[2] ?? ''),
            'singer' => trim($matches[1] ?? ''),
            'lyrics' => $lyrics,
            'dl_link_320' => $dl_link_320,
            'dl_link_128' => $dl_link_128,
        ];
    }

    private function crawlPage($page = 0)
    {
        if ($page == 0) {
            $url = 'https://rozmusic.com';
        } else {
            $url = 'https://rozmusic.com/page/' . $page;
        }
        $html = Http::withoutVerifying()
            ->get($url)
            ->body();
        $crawler = new Crawler($html);

        $results = [];

        $crawler
            ->filter('article h2.title a')
            ->each(function ($node) use (&$results) {

                $results[] = $node->attr('href');
            });

        return $results;
    }
}
