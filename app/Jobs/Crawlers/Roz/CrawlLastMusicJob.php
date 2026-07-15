<?php

namespace App\Jobs\Crawlers\Roz;

use App\Services\Crawlers\Roz\MusicCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CrawlLastMusicJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(MusicCrawlerService $musicCrawlerService): void
    {
        $musicCrawlerService->startLast();
    }
}
