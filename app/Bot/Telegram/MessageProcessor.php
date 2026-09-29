<?php

namespace App\Bot\Telegram;

use App\Bot\Shared\MessageProcessorInterface;
use App\Bot\Telegram\Commands\HelpCommand;
use App\Bot\Telegram\Commands\SearchCommand;
use App\Bot\Telegram\Commands\StartCommand;
use App\Bot\Telegram\Commands\StatusCommand;
use App\Bot\Telegram\Contracts\CommandHandlerInterface;
use App\Bot\Telegram\Services\TelegramService;

class MessageProcessor implements MessageProcessorInterface
{
    /**
     * @var array<CommandHandlerInterface>
     */
    protected array $handlers;

    public function __construct(
        StartCommand $startCommand,
        HelpCommand $helpCommand,
        StatusCommand $statusCommand,
        SearchCommand $searchCommand,
        protected TelegramService $telegramService,
    ) {
        $this->handlers = [
            $startCommand,
            $helpCommand,
            $statusCommand,
            $searchCommand, // همیشه آخر، چون هر متنی رو قبول می‌کنه
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
        $this->telegramService->sendMessage($message['chat']['id'], 'متوجه منظورت نشدم!');
    }
}
