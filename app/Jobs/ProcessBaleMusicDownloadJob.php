<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;

class ProcessBaleMusicDownloadJob extends BaseMusicDownloadJob
{
    public function __construct(int $songId, int|string $chatId, int|null $messageId, ?string $lockKey = null)
    {
        parent::__construct($songId, $chatId, $messageId, $lockKey);
        $this->onQueue('download-music-bale');
    }

    protected function bot(): BotApiInterface
    {
        return app(BaleService::class);
    }

    protected function queueLockPrefix(): string
    {
        return 'bale-song';
    }
}
