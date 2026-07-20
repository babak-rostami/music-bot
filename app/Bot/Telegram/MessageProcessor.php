<?php

namespace App\Bot\Telegram;

use App\Bot\Telegram\Commands\SearchCommand;
use App\Bot\Telegram\Commands\StartCommand;
use App\Bot\Telegram\Contracts\CommandHandlerInterface;
use App\Bot\Telegram\Services\TelegramService;

class MessageProcessor
{
    /**
     * @var array<CommandHandlerInterface>
     */
    protected array $handlers;

    public function __construct(StartCommand $startCommand, SearchCommand $searchCommand)
    {
        // ترتیب هندلرها مهم است. کامندهای خاص باید قبل از کامندهای عمومی بیایند.
        $this->handlers = [
            $startCommand,
            // اینجا می‌توانید کامندهای بعدی را اضافه کنید (/help, /settings, ...)
            $searchCommand, // کامند جستجو (پیش‌فرض) در آخر قرار می‌گیرد
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

        // اختیاری: اگر هیچ هندلری پیدا نشد، یک پاسخ پیش‌فرض بفرست
        (new TelegramService())->sendMessage($message['chat']['id'], 'متوجه منظورت نشدم!');
    }
}
