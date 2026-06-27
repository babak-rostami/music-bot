<?php

namespace App\Telegram\Commands;

use App\Services\TelegramService;
use App\Telegram\Contracts\CommandHandlerInterface;

class StartCommand implements CommandHandlerInterface
{
    public function __construct(protected TelegramService $telegramService) {}

    public function canHandle(string $text): bool
    {
        // این هندلر فقط به کامند /start واکنش نشان می‌دهد
        return $text === '/start';
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $firstName = $message['from']['first_name'];

        $welcomeText = "سلام {$firstName}! 👋\n\n"
            . "به ربات موزیک‌یاب خوش اومدی. 🎵\n\n"
            . "یه تیکه از متن آهنگ رو برام بفرست؛ حتی اگه فقط چند کلمه ازش یادت مونده باشه.\n\n"
            . "من آهنگ رو پیدا می‌کنم و فایلش رو برات می‌فرستم. 🚀";

        $this->telegramService->sendMessage($chatId, $welcomeText);
    }
}
