<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;

class ProcessBaleMusicDownloadJob extends BaseMusicDownloadJob
{
    protected function bot(): BotApiInterface
    {
        return app(BaleService::class);
    }

    protected function queueLockPrefix(): string
    {
        return 'bale-song';
    }
}
