<?php

namespace App\Bot\Shared;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Telegram\Services\TelegramService;

class BotFactory
{
    public static function make(string $platform): BotApiInterface
    {
        return match ($platform) {
            'bale'  => app(BaleService::class),
            default => app(TelegramService::class),
        };
    }
}
