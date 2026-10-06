<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseUpgradeCommand;

/**
 * منطق اصلی نمایش پلن‌ها در BaseUpgradeCommand قرار دارد و
 * این کلاس فقط پلتفرم و سرویس ارتباط را مشخص می‌کند.
 */
class BaleUpgradeCommand extends BaseUpgradeCommand
{
    public function __construct(private BaleService $baleService) {}

    protected function platform(): string
    {
        return 'bale';
    }

    protected function bot(): BotApiInterface
    {
        return $this->baleService;
    }
}
