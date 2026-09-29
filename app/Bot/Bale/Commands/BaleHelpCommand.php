<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseHelpCommand;

class BaleHelpCommand extends BaseHelpCommand
{
    public function __construct(private BaleService $baleService) {}

    protected function bot(): BotApiInterface
    {
        return $this->baleService;
    }
}
