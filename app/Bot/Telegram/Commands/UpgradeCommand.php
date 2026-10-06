<?php

namespace App\Bot\Telegram\Commands;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseUpgradeCommand;
use App\Bot\Telegram\Services\TelegramService;

/**
 * منطق اصلی نمایش پلن‌ها در BaseUpgradeCommand قرار دارد و
 * این کلاس فقط پلتفرم و سرویس ارتباط را مشخص می‌کند.
 */
class UpgradeCommand extends BaseUpgradeCommand
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
