<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Commands\BaseStatusCommand;

class BaleStatusCommand extends BaseStatusCommand
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
