<?php

namespace App\Telegram\Commands;

use App\Models\Song;
use App\Services\TelegramService;
use App\Telegram\Contracts\CommandHandlerInterface;

class SearchCommand implements CommandHandlerInterface
{
    public function __construct(protected TelegramService $telegramService) {}

    public function canHandle(string $text): bool
    {
        // این هندلر به هر متنی که با / شروع نشده باشد، واکنش نشان می‌دهد.
        return !str_starts_with($text, '/');
    }

    public function handle(array $message): void
    {
        $text = $message['text'];
        $chatId = $message['chat']['id'];

        $result = Song::searchElastic($text)->take(5);

        if ($result->isEmpty()) {
            $this->telegramService->sendMessage($chatId, 'متاسفانه آهنگی با این مشخصات پیدا نکردم.');
            return;
        }

        $buttons = [];
        foreach ($result as $song) {
            $buttons[] = [
                ['text' => $song->title . ' - ' . $song->artist_name, 'callback_data' => 'song_' . $song->id]
            ];
        }

        $this->telegramService->sendMessage(
            $chatId,
            'کدوم آهنگ رو برات بفرستم؟',
            $buttons
        );
    }
}
