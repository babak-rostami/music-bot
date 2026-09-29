<?php

namespace App\Bot\Bale\Commands;

use App\Bot\Bale\Services\BaleService;
use App\Jobs\MusicFinderJob;
use App\Models\Song;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderUsageService;
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
        return $text !== '' && !str_starts_with($text, '/');
    }

    public function handle(array $message): void
    {
        $text = trim($message['text'] ?? '');
        if ($text === '') {
            return;
        }
        $chatId = $message['chat']['id'];
        $from   = $message['from'];

        $result = Song::searchElastic($text)->take(8);

        // ─────────────────────────────────────────────
        // حالت ۱: هیچ نتیجه‌ای پیدا نشد
        // ─────────────────────────────────────────────
        if ($result->isEmpty()) {
            $request = $this->saveSongRequest($from, $chatId, $text);

            $statusText = MusicFinderAccess::isTestMode()
                ? "✅ پیامت با موفقیت ثبت شد\n\n⏰ آهنگ در صف دانلود قرار گرفت.\n\n💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!"
                : "🔎 در حال جستجو، چند لحظه صبر کن...";

            $this->baleService->sendMessage($chatId, $statusText);

            $this->adminNotifier->notifyNewSongRequest($request, 'bale');

            if (MusicFinderAccess::allowed('bale', $from['id'])) {
                if ($quotaMessage = MusicFinderUsageService::guard('bale', $chatId)) {
                    $this->baleService->sendMessage($chatId, $quotaMessage);
                } elseif (MusicFinderAccess::tryReserve('bale', $chatId)) {
                    MusicFinderJob::dispatch('bale', $chatId, 'text', text: $text, originalQuery: $text)
                        ->onQueue('music-finder');
                }
            }

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
            ['text' => '❌💎 جستجوی پیشرفته 💎❌', 'callback_data' => "request_song_{$token}"]
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
