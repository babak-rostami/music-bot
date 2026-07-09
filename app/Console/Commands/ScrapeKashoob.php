<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeKashoobListPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:kashoob
                            {--pages=183 : Total pages to scrape}
                            {--start=1 : Start page}
                            {--batch=10 : Jobs per batch}
                            {--delay=3 : Delay between batches in seconds}')]
#[Description('Scrape all song pages from kashoob.com')]
class ScrapeKashoob extends Command
{

    public function handle(): int
    {
        $totalPages = (int) $this->option('pages');
        $startPage = (int) $this->option('start');
        $batchSize = (int) $this->option('batch');
        $delay = (int) $this->option('delay');

        $this->info("🚀 Starting kashoob scraper (pages {$startPage}-{$totalPages})");

        $bar = $this->output->createProgressBar($totalPages - $startPage + 1);
        $bar->start();

        $dispatched = 0;

        for ($page = $startPage; $page <= $totalPages; $page++) {
            ScrapeKashoobListPage::dispatch($page)
                ->onQueue('crawl-musics-page');

            $dispatched++;

            if ($dispatched % $batchSize === 0) {
                $bar->advance($batchSize);
                $this->newLine();
                $this->line("   ✅ {$dispatched} pages dispatched. Waiting {$delay}s...");
                sleep($delay);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Dispatched {$dispatched} list page jobs to queue 'crawl-musics-page'.");
        $this->line("");
        $this->line("⚙️  Run worker:");
        $this->line("   php artisan queue:work --queue=crawl-musics-page --sleep=3 --tries=3");
        $this->line("");
        $this->line("💡 Or run multiple workers in parallel:");
        $this->line("   php artisan queue:work --queue=crawl-musics-page --sleep=3 --tries=3 &");
        $this->line("   php artisan queue:work --queue=crawl-musics-page --sleep=3 --tries=3 &");

        return Command::SUCCESS;
    }
}
