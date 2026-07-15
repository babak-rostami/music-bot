<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


// Schedule::command('app:crawl-last-musics-command')->everySixHours();
// Schedule::command('app:crawl-last-madahi-kashob-command')->everySixHours();
Schedule::command('crawler:bia2music:latest')->twiceDaily(0, 12);
