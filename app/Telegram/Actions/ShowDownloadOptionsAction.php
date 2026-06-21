<?php

namespace App\Telegram\Actions;

use App\Models\Song;
use App\Services\TelegramService;

class ShowDownloadOptionsAction
{
    public function __construct(protected TelegramService $telegramService) {}

    public function execute(string $callbackQueryId, int $songId, int|string $chatId, int $messageId): void
    {
        $song = Song::findOrFail($songId);

        // 1. دکمه‌های دانلود را می‌سازیم
        $buttons = [
            [
                // اگر شرط درست باشد، دکمه را اضافه کن، در غیر این صورت هیچی
                ...($song->download_link_128 ? [[
                    'text' => 'دانلود با کیفیت 128',
                    'callback_data' => 'download_music_128_' . $song->id,
                ]] : []),

                ...($song->download_link_320 ? [[
                    'text' => 'دانلود با کیفیت 320',
                    'callback_data' => 'download_music_320_' . $song->id,
                ]] : []),
            ]
        ];
        // فیلتر کردن ردیف‌های خالی احتمالی
        $buttons = array_filter($buttons, fn($row) => !empty($row));

        // 2. پیام موجود را ویرایش می‌کنیم، به جای ارسال پیام جدید
        $this->telegramService->editMessageText(
            $chatId,
            $messageId, // <-- استفاده از messageId
            "🎧 " . $song->title . "\n👤 " . $song->artist_name,
            $buttons
        );
    }
}
