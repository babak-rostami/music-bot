<?php

namespace App\Console\Commands\Bia2Music;

use App\Jobs\Crawlers\Bia2Music\DispatchFullBia2MusicCrawlerJob;
use App\Services\Crawlers\Bia2Music\Bia2MusicCrawlerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crawler:bia2music:full 
                            {--from= : Start page}
                            {--to=1 : End page}')]
#[Description('Dispatch full Bia2Music crawler jobs from last page to first page.')]
class DispatchFullBia2MusicCrawlerCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromPage = (int) ($this->option('from') ?: Bia2MusicCrawlerService::LAST_PAGE);
        $toPage = (int) $this->option('to');

        DispatchFullBia2MusicCrawlerJob::dispatch($fromPage, $toPage);

        $this->info("Bia2Music full crawler dispatched from page {$fromPage} to {$toPage}.");

        return self::SUCCESS;
    }
}
