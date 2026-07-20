<?php

namespace App\Bot\Telegram\Actions;

use App\Bot\Telegram\Services\TelegramService;
use App\Models\Song;

class ShowDownloadOptionsAction
{
    public function __construct(protected TelegramService $telegramService) {}

    /**
     * این متد برای زمانی است که کاربر روی دکمه داخل بات کلیک می‌کند.
     * یعنی callback_data مثل song_123
     */
    public function execute(
        string $callbackQueryId,
        int $songId,
        int|string $chatId,
        int $messageId
    ): void {
        $song = Song::find($songId);

        if (!$song) {
            $this->telegramService->answerCallbackQuery($callbackQueryId, '❌ آهنگ پیدا نشد.');
            return;
        }

        $buttons = $this->makeDownloadButtons($song);
        $text = $this->makeDownloadText($song, !empty($buttons));

        $this->telegramService->editMessageText(
            $chatId,
            $messageId,
            $text,
            $buttons
        );

        $this->telegramService->answerCallbackQuery($callbackQueryId);
    }

    /**
     * این متد برای زمانی است که کاربر از سایت وارد بات می‌شود.
     * یعنی پیام /start dl_123 به بات می‌رسد.
     */
    public function sendToChat(int|string $chatId, int $songId): void
    {
        $song = Song::find($songId);

        if (!$song) {
            $this->telegramService->sendMessage(
                $chatId,
                "❌ متأسفانه این آهنگ پیدا نشد یا حذف شده است."
            );

            return;
        }

        $buttons = $this->makeDownloadButtons($song);
        $text = $this->makeDownloadText($song, !empty($buttons));

        $this->telegramService->sendMessage(
            $chatId,
            $text,
            $buttons
        );
    }

    private function makeDownloadText(Song $song, bool $hasButtons): string
    {
        $text = "🎧 {$song->title}\n";
        $text .= "👤 {$song->artist_name}\n\n";

        if ($hasButtons) {
            $text .= "کیفیت موردنظرت رو برای دانلود انتخاب کن 👇";
        } else {
            $text .= "❌ در حال حاضر لینک دانلودی برای این آهنگ ثبت نشده است.";
        }

        return $text;
    }

    private function makeDownloadButtons(Song $song): array
    {
        $row = [];

        if (!empty($song->download_link_128)) {
            $row[] = [
                'text' => 'دانلود با کیفیت 128',
                'callback_data' => 'download_music_128_' . $song->id,
            ];
        }

        if (!empty($song->download_link_320)) {
            $row[] = [
                'text' => 'دانلود با کیفیت 320',
                'callback_data' => 'download_music_320_' . $song->id,
            ];
        }

        if (empty($row)) {
            return [];
        }

        return [
            $row
        ];
    }
}
