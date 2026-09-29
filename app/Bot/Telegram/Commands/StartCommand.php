<?php

namespace App\Bot\Telegram\Commands;

use App\Bot\Shared\BotAdminService;
use App\Bot\Shared\BotCommandRegistrar;
use App\Bot\Telegram\Actions\ShowDownloadOptionsAction;
use App\Bot\Telegram\Contracts\CommandHandlerInterface;
use App\Bot\Telegram\Services\TelegramService;
use App\Jobs\ProcessMusicDownloadJob;

class StartCommand implements CommandHandlerInterface
{
    public function __construct(
        protected TelegramService $telegramService,
        protected ShowDownloadOptionsAction $showDownloadOptionsAction,
        protected BotAdminService $adminService,
    ) {}

    public function canHandle(string $text): bool
    {
        $text = trim($text);

        // پشتیبانی از:
        // /start
        // /start dl_123
        // /start@YourBot dl_123
        return preg_match('/^\/start(@[A-Za-z0-9_]+)?(\s+.+)?$/u', $text) === 1;
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $firstName = $message['from']['first_name'] ?? 'دوست عزیز';
        $text = trim($message['text'] ?? '');

        $payload = $this->extractPayload($text);

        // اگر کاربر از سایت برای دانلود آهنگ آمده باشد
        if ($payload && $this->handlePayload($payload, $chatId)) {
            return;
        }

        // ثبت دستورات منو
        if ($this->adminService->isAdmin($this->telegramService, (int) $message['from']['id'])) {
            BotCommandRegistrar::ensureRegistered($this->telegramService, true, $chatId);
        } else {
            BotCommandRegistrar::ensureRegistered($this->telegramService, false, $chatId);
        }

        // حالت عادی /start
        $welcomeText = "🎵 خوش اومدی به قوی‌ترین ربات آهنگ‌یاب!\n\n"
            . "🔤 جستجو با متن آهنگ\n"
            . "🔗 لینک اینستاگرام و یوتیوب و تیک تاک\n"
            . "🎬 جستجو با ویدیو\n"
            . "🎙 قسمتی از صدای آهنگ\n"
            . "🔎 جستجوی پیشرفته\n"
            . "🎶 درخواست آهنگ جدید\n"
            . "کافیه یکی از روش‌های بالا رو امتحان کنی! 🚀";

        $this->telegramService->sendMessage($chatId, $welcomeText);
    }

    private function extractPayload(string $text): ?string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text));

        $parts = explode(' ', $text, 2);

        if (!isset($parts[1])) {
            return null;
        }

        $payload = trim($parts[1]);

        return $payload !== '' ? $payload : null;
    }

    private function handlePayload(string $payload, int|string $chatId): bool
    {
        // لینک سایت:
        // https://t.me/YourBotUsername?start=dl_123
        if (preg_match('/^dl_(\d+)$/', $payload, $matches)) {
            $songId = (int) $matches[1];

            ProcessMusicDownloadJob::dispatch($songId, $chatId, null, null);

            return true;
        }

        // برای سازگاری اگر جایی قبلاً از song_123 استفاده کردی
        if (preg_match('/^song_(\d+)$/', $payload, $matches)) {
            $songId = (int) $matches[1];

            $this->showDownloadOptionsAction->sendToChat($chatId, $songId);

            return true;
        }

        return false;
    }
}
