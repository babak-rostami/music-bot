<?php

namespace App\Jobs\Crawlers\Kashoob;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeKashoobListPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(
        public int $pageNumber
    ) {}

    public function handle(): void
    {
        $url = "https://kashoob.com/audios/type/madahi/?p={$this->pageNumber}";

        Log::info("Start ScrapeKashoobListPage: page {$this->pageNumber}", ['url' => $url]);

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

            Log::info("Found " . count($songUrls) . " songs on page {$this->pageNumber}");

            foreach ($songUrls as $songUrl) {
                ScrapeKashoobSongPage::dispatch($songUrl)
                    ->onQueue('crawl-musics-page');
            }
        } catch (\Exception $e) {
            Log::error("Error ScrapeKashoobListPage page {$this->pageNumber}: " . $e->getMessage());
            $this->release(30);
        }
    }
}
