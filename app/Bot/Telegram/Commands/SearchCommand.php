<?php

namespace App\Bot\Telegram\Commands;

use App\Bot\Telegram\Contracts\CommandHandlerInterface;
use App\Bot\Telegram\Services\TelegramService;
use App\Models\Song;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SearchCommand implements CommandHandlerInterface
{
    public function __construct(
        protected TelegramService $telegramService,
        protected AdminNotifier $adminNotifier,
    ) {}

    public function canHandle(string $text): bool
    {
        return !str_starts_with($text, '/');
    }

    public function handle(array $message): void
    {
        $text   = $message['text'];
        $chatId = $message['chat']['id'];
        $from   = $message['from'];

        $result = Song::searchElastic($text)->take(5);

        // ─────────────────────────────────────────────
        // حالت ۱: هیچ نتیجه‌ای پیدا نشد → ثبت درخواست
        // ─────────────────────────────────────────────
        if ($result->isEmpty()) {
            $request = $this->saveSongRequest($from, $chatId, $text);

            $this->telegramService->sendMessage(
                $chatId,
                "✅ پیامت با موفقیت ثبت شد\n\n"
                    . "⏰ آهنگ در صف دانلود قرار گرفت.\n\n"
                    . "💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!",
            );

            // نوتیفیکیشن به ادمین
            $this->adminNotifier->notifyNewSongRequest($request, 'telegram');

            return;
        }

        // ─────────────────────────────────────────────
        // حالت ۲: نتایج پیدا شد → لیست + دکمه ثبت درخواست
        // ─────────────────────────────────────────────
        $buttons = [];

        foreach ($result as $song) {
            $buttons[] = [
                ['text' => '🎵 ' . $song->title . ' - ' . $song->artist_name, 'callback_data' => 'song_' . $song->id]
            ];
        }

        // دکمه آخر: اگه هیچکدوم نبود
        // callback_data فقط ۶۴ بایت جا داره، پس query رو توی Cache ذخیره می‌کنیم
        $token = Str::random(20);
        Cache::put("song_request:{$token}", [
            'query'   => $text,
            'from'    => $from,
            'chat_id' => $chatId,
        ], now()->addMinutes(30));

        $buttons[] = [
            ['text' => '❌ پیدا نکردی؟ کلیک کن ❌', 'callback_data' => "request_song_{$token}"]
        ];

        $this->telegramService->sendMessage(
            $chatId,
            'کدوم آهنگ رو برات بفرستم؟',
            $buttons
        );
    }

    /**
     * ذخیره درخواست آهنگ در دیتابیس
     */
    private function saveSongRequest(array $from, int $chatId, string $query): SongRequest
    {
        return SongRequest::create([
            'user_id'    => $from['id'],
            'username'   => $from['username'] ?? null,
            'first_name' => $from['first_name'] ?? null,
            'last_name'  => $from['last_name'] ?? null,
            'chat_id'    => $chatId,
            'query'      => $query,
            'status'     => SongRequest::STATUS_PENDING,
            'platform'     => 'telegram'
        ]);
    }
}
