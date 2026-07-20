<?php

namespace App\Bot\Bale\Actions;

use App\Bot\Bale\Services\BaleService;
use App\Models\Song;

class BaleShowDownloadOptionsAction
{
    public function __construct(protected BaleService $baleService) {}

    /**
     * وقتی کاربر روی دکمه کلیک می‌کند (callback)
     */
    public function execute(
        string $callbackQueryId,
        int $songId,
        int|string $chatId,
        int $messageId
    ): void {
        $song = Song::find($songId);

        if (!$song) {
            $this->baleService->answerCallbackQuery($callbackQueryId, '❌ آهنگ پیدا نشد.');
            return;
        }

        $buttons = $this->makeDownloadButtons($song);
        $text = $this->makeDownloadText($song, !empty($buttons));

        $this->baleService->editMessageText(
            $chatId,
            $messageId,
            $text,
            $buttons
        );

        $this->baleService->answerCallbackQuery($callbackQueryId);
    }

    /**
     * وقتی کاربر از سایت وارد بات می‌شود (/start dl_123)
     */
    public function sendToChat(int|string $chatId, int $songId): void
    {
        $song = Song::find($songId);

        if (!$song) {
            $this->baleService->sendMessage(
                $chatId,
                "❌ متأسفانه این آهنگ پیدا نشد یا حذف شده است."
            );
            return;
        }

        $buttons = $this->makeDownloadButtons($song);
        $text = $this->makeDownloadText($song, !empty($buttons));

        $this->baleService->sendMessage(
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

        return [$row];
    }
}
