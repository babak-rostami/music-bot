<?php

namespace App\Bot\Shared\Commands;

use App\Bot\Shared\BotApiInterface;
use App\Services\MusicFinder\MusicFinderUsageService;

abstract class BaseStatusCommand
{
    public function canHandle(string $text): bool
    {
        return trim($text) === '/status';
    }

    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];

        $limit = (int) config('services.music_finder_bot.free_requests_limit', 3);
        $windowHours = (int) config('services.music_finder_bot.free_requests_window_hours', 12);
        $remaining = MusicFinderUsageService::remainingCount($this->platform(), $userId);

        $text = "💎 وضعیت درخواست‌های امروز\n\n"
            . "{$remaining} از {$limit} درخواست جستجوی پیشرفته‌ات باقی مونده.\n\n"
            . "🎁 هر {$windowHours} ساعت، {$limit} درخواست هدیه داری.\n\n"
            . "🔤 جستجو و دانلود با متن آهنگ\n"
            . "کاملاً رایگان و نامحدود است. ♾️\n\n"
            . "👇 جستجوی پیشرفته شامل:\n"
            . "🔗 جستجو با لینک یوتیوب، اینستاگرام و تیک‌تاک\n"
            . "🎬 جستجو با ویدیو\n"
            . "🎙 جستجو با قسمتی از صدای آهنگ\n"
            . "🔤 جستجوی پیشرفته متن آهنگ";

        $this->bot()->sendMessage($chatId, $text);
    }

    abstract protected function platform(): string;
    abstract protected function bot(): BotApiInterface;
}
