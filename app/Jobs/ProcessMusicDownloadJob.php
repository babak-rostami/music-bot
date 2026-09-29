<?php

namespace App\Jobs;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Telegram\Services\TelegramService;

class ProcessMusicDownloadJob extends BaseMusicDownloadJob
{
    public function __construct(int $songId, int|string $chatId, int|null $messageId, ?string $lockKey = null)
    {
        parent::__construct($songId, $chatId, $messageId, $lockKey);
        $this->onQueue('download-music-telegram');
    }

    protected function bot(): BotApiInterface
    {
        return app(TelegramService::class);
    }

    protected function queueLockPrefix(): string
    {
        return 'song';
    }
}
