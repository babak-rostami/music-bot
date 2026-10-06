<?php

namespace App\Bot\Bale;

use App\Bot\Bale\Commands\BaleHelpCommand;
use App\Bot\Bale\Commands\BaleSearchCommand;
use App\Bot\Bale\Commands\BaleStartCommand;
use App\Bot\Bale\Commands\BaleStatusCommand;
use App\Bot\Bale\Commands\BaleUpgradeCommand;
use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\MessageProcessorInterface;

class MessageProcessor implements MessageProcessorInterface
{
    protected array $handlers;

    public function __construct(
        BaleStartCommand $startCommand,
        BaleHelpCommand $helpCommand,
        BaleStatusCommand $statusCommand,
        BaleUpgradeCommand $upgradeCommand,
        BaleSearchCommand $searchCommand,
        protected BaleService $baleService,
    ) {
        $this->handlers = [
            $startCommand,
            $helpCommand,
            $statusCommand,
            $upgradeCommand,
            $searchCommand,
        ];
    }

    public function process(array $message): void
    {
        $text = $message['text'] ?? '';

        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($text)) {
                $handler->handle($message);
                return;
            }
        }

        // پاسخ پیش‌فرض
        $this->baleService->sendMessage($message['chat']['id'], 'متوجه منظورت نشدم!');
    }
}
