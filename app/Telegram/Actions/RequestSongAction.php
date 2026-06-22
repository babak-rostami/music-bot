<?php

namespace App\Telegram\Actions;

use App\Models\SongRequest;
use App\Services\AdminNotifier;
use App\Services\TelegramService;
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
            ]);

            // پیام تأیید برای کاربر (پیام لیست آهنگ‌ها رو عوض می‌کنیم)
            $this->telegram->editMessageText(
                $chatId,
                $messageId,
                "✅ درخواست شما ثبت شد!\n\n"
                    . "🎵 آهنگ در لیست جستجو قرار گرفت\n\n"
                    . "⏰ تا چند ساعت آینده برات آپلود میشه.\n\n"
                    . "💎 وقتی آماده شد، همینجا خبرت می‌کنم!",
                [] // حذف دکمه‌ها
            );

            // toast کوچک روی دکمه
            $this->telegram->answerCallbackQuery($callbackQueryId, '✅ درخواست ثبت شد!');

            // نوتیفیکیشن به کانال ادمین
            $this->adminNotifier->notifyNewSongRequest($request);
        } catch (\Throwable $e) {
            Log::error('Failed to save song request', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);

            $this->telegram->answerCallbackQuery($callbackQueryId, '❌ خطا در ثبت درخواست، دوباره تلاش کن');
        }
    }
}
