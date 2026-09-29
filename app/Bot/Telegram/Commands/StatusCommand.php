<?php

namespace App\Bot\Telegram\Commands;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseStatusCommand;
use App\Bot\Telegram\Services\TelegramService;

class StatusCommand extends BaseStatusCommand
{
    public function __construct(private TelegramService $telegramService) {}

    protected function platform(): string
    {
        return 'telegram';
    }

    protected function bot(): BotApiInterface
    {
        return $this->telegramService;
    }
}
