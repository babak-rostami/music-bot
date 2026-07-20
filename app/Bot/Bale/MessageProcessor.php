<?php

namespace App\Bot\Bale;

use App\Bot\Bale\Commands\BaleSearchCommand;
use App\Bot\Bale\Commands\BaleStartCommand;
use App\Bot\Bale\Services\BaleService;

class MessageProcessor
{
    protected array $handlers;

    public function __construct(BaleStartCommand $startCommand, BaleSearchCommand $searchCommand)
    {
        $this->handlers = [
            $startCommand,
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
        (new BaleService())->sendMessage($message['chat']['id'], 'متوجه منظورت نشدم!');
    }
}
