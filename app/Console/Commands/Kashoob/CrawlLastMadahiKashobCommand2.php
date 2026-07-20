<?php

namespace App\Console\Commands\Kashoob;

use App\Jobs\Crawlers\Kashoob\ScrapeKashoobLastPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:crawl-last-madahi-kashob-command-2')]
#[Description('Crawl the latest page of kashoob.com and save new songs')]
class CrawlLastMadahiKashobCommand2 extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Dispatching ScrapeKashoobLastPage job...');

        // ScrapeKashoobLastPage::dispatch()
        //     ->onQueue('crawl-musics-page');
        \App\Jobs\Crawlers\Kashoob\ScrapeKashoobLastPage::dispatch();


        $this->info('✅ Job dispatched to queue: crawl-musics-page');

        return Command::SUCCESS;
    }
}
