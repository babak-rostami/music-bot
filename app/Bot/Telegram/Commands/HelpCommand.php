<?php

namespace App\Bot\Telegram\Commands;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseHelpCommand;
use App\Bot\Telegram\Services\TelegramService;

class HelpCommand extends BaseHelpCommand
{
    public function __construct(private TelegramService $telegramService) {}

    protected function bot(): BotApiInterface
    {
        return $this->telegramService;
    }
}
