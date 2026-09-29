<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Actions\BaleShowDownloadOptionsAction;
use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotAdminService;
use App\Bot\Shared\BotCommandRegistrar;
use App\Jobs\ProcessBaleMusicDownloadJob;

class BaleStartCommand
{
    public function __construct(
        protected BaleService $baleService,
        protected BaleShowDownloadOptionsAction $showDownloadOptionsAction,
        protected BotAdminService $adminService,

    ) {}

    public function canHandle(string $text): bool
    {
        $text = trim($text);

        // پشتیبانی از:
        // /start
        // /start dl_123
        return preg_match('/^\/start(\s+.+)?$/u', $text) === 1;
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $firstName = $message['from']['first_name'] ?? $message['from']['username'] ?? 'دوست عزیز';
        $text = trim($message['text'] ?? '');

        $payload = $this->extractPayload($text);

        // اگر کاربر از سایت برای دانلود آهنگ آمده باشد
        if ($payload && $this->handlePayload($payload, $chatId)) {
            return;
        }

        // ثبت دستورات منو
        if ($this->adminService->isAdmin($this->baleService, (int) $message['from']['id'])) {
            BotCommandRegistrar::ensureRegistered($this->baleService, true, $chatId);
        } else {
            BotCommandRegistrar::ensureRegistered($this->baleService, false, $chatId);
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

        $this->baleService->sendMessage($chatId, $welcomeText);
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
        // لینک سایت: /start dl_123
        if (preg_match('/^dl_(\d+)$/', $payload, $matches)) {
            $songId = (int) $matches[1];
            ProcessBaleMusicDownloadJob::dispatch($songId, $chatId, null, null);
            return true;
        }

        // برای سازگاری
        if (preg_match('/^song_(\d+)$/', $payload, $matches)) {
            $songId = (int) $matches[1];
            $this->showDownloadOptionsAction->sendToChat($chatId, $songId);
            return true;
        }

        return false;
    }
}
