<?php

namespace App\Console\Commands\Roz;

use App\Jobs\Crawlers\Roz\CrawlLastMusicJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:crawl-last-musics-command')]
#[Description('crawl last musics')]
class CrawlLastMusicsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        CrawlLastMusicJob::dispatch()->onQueue('crawl-musics-page');

        $this->info('Job dispatched successfully!');
    }
}
