<?php

namespace App\Bot\Telegram\Actions;

use App\Bot\Telegram\Services\TelegramService;
use App\Jobs\MusicFinderJob;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RequestSongAction
{
    public function __construct(
        protected TelegramService $telegram,
        protected AdminNotifier $adminNotifier,
    ) {}

    /**
     * وقتی کاربر روی دکمه "❌ هیچکدوم نبود - ثبت درخواست" کلیک می‌کنه
     */
    public function execute(string $callbackQueryId, string $token, int|string $chatId, int $messageId): void
    {
        // از Cache بگیر و حذفش کن (یه بار مصرف)
        $payload = Cache::pull("song_request:{$token}");

        if (!$payload) {
            $this->telegram->answerCallbackQuery(
                $callbackQueryId,
                '⚠️ درخواست منقضی شده، لطفاً دوباره جستجو کن'
            );
            return;
        }

        try {
            // ذخیره در دیتابیس
            $request = SongRequest::create([
                'user_id'    => $payload['from']['id'],
                'username'   => $payload['from']['username'] ?? null,
                'first_name' => $payload['from']['first_name'] ?? null,
                'last_name'  => $payload['from']['last_name'] ?? null,
                'chat_id'    => $payload['chat_id'],
                'query'      => $payload['query'],
                'status'     => SongRequest::STATUS_PENDING,
                'platform'     => 'telegram'
            ]);

            $statusText = MusicFinderAccess::isTestMode()
                ? "✅ پیامت با موفقیت ثبت شد\n\n⏰ آهنگ در صف دانلود قرار گرفت.\n\n💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!"
                : "🔎 در حال جستجو، چند لحظه صبر کن...";

            $this->telegram->editMessageText($chatId, $messageId, $statusText, []);

            $this->telegram->answerCallbackQuery($callbackQueryId, '✅ درخواست ثبت شد!');

            $this->adminNotifier->notifyNewSongRequest($request, 'telegram');

            if (MusicFinderAccess::allowed('telegram', $payload['from']['id'])) {
                if ($quotaMessage = MusicFinderUsageService::guard('telegram', $chatId)) {
                    $this->telegram->sendMessage($chatId, $quotaMessage);
                } elseif (MusicFinderAccess::tryReserve('telegram', $chatId)) {
                    MusicFinderJob::dispatch('telegram', $chatId, 'text', text: $payload['query'], originalQuery: $payload['query'])
                        ->onQueue('music-finder');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to save song request', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            $this->telegram->answerCallbackQuery($callbackQueryId, '❌ خطا در ثبت درخواست، دوباره تلاش کن');
        }
    }
}
