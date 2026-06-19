<?php

namespace App\Jobs;

use App\Services\MusicCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CrawlMusicJob implements ShouldQueue
{
    use Queueable;

    public int $page_number;

    public function __construct(int $page_number)
    {
        $this->page_number = $page_number;
    }

    public function handle(MusicCrawlerService $musicCrawlerService): void
    {
        $musicCrawlerService->startCrawlPages($this->page_number);
    }
}
