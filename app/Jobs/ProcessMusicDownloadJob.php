<?php

namespace App\Jobs;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Telegram\Services\TelegramService;

class ProcessMusicDownloadJob extends BaseMusicDownloadJob
{
    protected function bot(): BotApiInterface
    {
        return app(TelegramService::class);
    }

    protected function queueLockPrefix(): string
    {
        return 'song';
    }
}
