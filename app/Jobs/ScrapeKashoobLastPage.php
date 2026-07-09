<?php

namespace App\Jobs;

use App\Models\Song;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeKashoobLastPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    /**
     * Each 12 hours, check the latest page (page 1) for new songs.
     */
    public function handle(): void
    {
        $url = 'https://kashoob.com/audios/type/madahi/?p=1';

        Log::info("Start ScrapeKashoobLastPage (12h check for new songs)");

        try {
            $html = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                ])
                ->timeout(60)
                ->get($url)
                ->body();

            $crawler = new Crawler($html);

            $songUrls = $crawler
                ->filter('a[href*="kashoob.com/audio/"]')
                ->each(function (Crawler $node) {
                    return $node->attr('href');
                });

            $songUrls = array_values(array_unique($songUrls));

            $newCount = 0;

            foreach ($songUrls as $songUrl) {
                $exists = Song::where('source_url', $songUrl)->exists();

                if (!$exists) {
                    ScrapeKashoobSongPage::dispatch($songUrl)
                        ->onQueue('crawl-musics-page');
                    $newCount++;
                }
            }

            Log::info("ScrapeKashoobLastPage: {$newCount} new songs dispatched, " .
                (count($songUrls) - $newCount) . " already exist.");
        } catch (\Exception $e) {
            Log::error("Error ScrapeKashoobLastPage: " . $e->getMessage());
            $this->release(60);
        }
    }
}
