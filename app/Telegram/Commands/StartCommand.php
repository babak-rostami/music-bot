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

        $welcomeText = "سلام {$firstName}! 👋\n\nبه ربات جستجوی آهنگ خوش اومدی.\nیه بخش از متن آهنگ مورد نظرت رو بفرست تا برات پیداش کنم.";

        $this->telegramService->sendMessage($chatId, $welcomeText);
    }
}
