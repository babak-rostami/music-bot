<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class SongController extends Controller
{
    public function crawlSongAgain(Song $song)
    {
        // crawl song page and update donwload url
        try {
            $html = Http::withoutVerifying()
                ->get($song->source_url)
                ->body();

            $crawler = new Crawler($html);

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

            $update_song_lyrics = 0;
            if ($song->lyrics === null && $lyrics) {
                $update_song_lyrics = true;
                $song->lyrics = $lyrics;
            }

            $song->download_link_128 = $dl_link_128;
            $song->download_link_320 = $dl_link_320;

            if ($song->isDirty()) {
                $song->save();
            }

            if ($update_song_lyrics) {
                Elasticsearch::update($song, [
                    'lyrics' => $lyrics
                ]);
            }
            dd('crawled successfully');
        } catch (\Throwable $e) {

            logger()->error('Music crawl failed', [
                'url' => $song->source_url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
