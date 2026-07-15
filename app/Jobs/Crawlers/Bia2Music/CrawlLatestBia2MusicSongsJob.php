<?php

namespace App\Jobs\Crawlers\Bia2Music;

use App\Services\Crawlers\Bia2Music\Bia2MusicCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlLatestBia2MusicSongsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('bia2music-crawler');
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping('bia2music-latest-songs'),
        ];
    }

    public function handle(Bia2MusicCrawlerService $crawlerService): void
    {
        $savedCount = $crawlerService->crawlOnlyLatestPage();

        Log::info('Bia2Music latest songs crawled.', [
            'saved_count' => $savedCount,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Bia2Music latest songs crawl job failed.', [
            'message' => $exception->getMessage(),
        ]);
    }
}
