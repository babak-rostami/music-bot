<?php

namespace App\Console\Commands\Bia2Music;

use App\Jobs\Crawlers\Bia2Music\CrawlLatestBia2MusicSongsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crawler:bia2music:latest')]
#[Description('Crawl latest songs from Bia2Music first mp3 page.')]
class CrawlLatestBia2MusicSongsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        CrawlLatestBia2MusicSongsJob::dispatch();

        $this->info('Bia2Music latest songs crawler dispatched.');

        return self::SUCCESS;
    }
}
