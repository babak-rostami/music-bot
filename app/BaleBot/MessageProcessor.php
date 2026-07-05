<?php

namespace App\BaleBot;

use App\BaleBot\Commands\BaleStartCommand;
use App\BaleBot\Commands\BaleSearchCommand;
use App\Services\BaleService;

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
