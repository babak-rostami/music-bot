<?php

namespace App\Jobs\Crawlers\Bia2Music;

use App\Services\Crawlers\Bia2Music\Bia2MusicCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class DispatchFullBia2MusicCrawlerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $timeout = 120;

    public function __construct(
        public int $fromPage = Bia2MusicCrawlerService::LAST_PAGE,
        public int $toPage = 1
    ) {
        $this->onQueue('bia2music-crawler');
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping('bia2music-full-dispatcher'),
        ];
    }

    public function handle(): void
    {
        $delaySeconds = 0;
        $delayBetweenPages = 1;

        for ($page = $this->fromPage; $page >= $this->toPage; $page--) {
            CrawlBia2MusicPageJob::dispatch($page)
                ->delay(now()->addSeconds($delaySeconds));

            $delaySeconds += $delayBetweenPages;
        }
    }
}
