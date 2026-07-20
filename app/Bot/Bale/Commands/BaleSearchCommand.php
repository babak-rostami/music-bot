<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Services\BaleService;
use App\Models\Song;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BaleSearchCommand
{
    public function __construct(
        protected BaleService $baleService,
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
        // حالت ۱: هیچ نتیجه‌ای پیدا نشد
        // ─────────────────────────────────────────────
        if ($result->isEmpty()) {
            $request = $this->saveSongRequest($from, $chatId, $text);

            $this->baleService->sendMessage(
                $chatId,
                "🔴 متأسفانه آهنگی با این مشخصات پیدا نکردم.\n\n"
                    . "✅ ولی نگران نباش! درخواست شما ثبت شد.\n\n"
                    . "⏰ تا چند ساعت آینده آهنگ رو برات آپلود می‌کنیم.\n\n"
                    . "💎 وقتی آماده شد، همینجا بهت خبر میدم!"
            );

            $this->adminNotifier->notifyNewSongRequest($request, 'bale');

            return;
        }

        // ─────────────────────────────────────────────
        // حالت ۲: نتایج پیدا شد
        // ─────────────────────────────────────────────
        $buttons = [];

        foreach ($result as $song) {
            $buttons[] = [
                ['text' => '🎵 ' . $song->title . ' - ' . $song->artist_name, 'callback_data' => 'song_' . $song->id]
            ];
        }

        // دکمه ثبت درخواست
        $token = Str::random(20);
        Cache::put("song_request:{$token}", [
            'query'   => $text,
            'from'    => $from,
            'chat_id' => $chatId,
        ], now()->addMinutes(30));

        $buttons[] = [
            ['text' => '❌ پیدا نکردی؟ کلیک کن ❌', 'callback_data' => "request_song_{$token}"]
        ];

        $this->baleService->sendMessage(
            $chatId,
            'کدوم آهنگ رو برات بفرستم؟',
            $buttons
        );
    }

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
            'platform'     => 'bale',
        ]);
    }
}
