<?php

namespace App\Bot\Telegram\Services;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Bot\Telegram\Actions\RequestSongAction;
use App\Bot\Telegram\Actions\ShowDownloadOptionsAction;
use App\Bot\Telegram\MessageProcessor;
use App\Services\SongUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramActionService
{

    public function __construct(
        protected TelegramService $telegramService,
        protected BotUserService $botUserService,
        protected NotificationService $notifier,
        protected MembershipService $membershipService,
        protected MessageProcessor $messageProcessor,
        protected ShowDownloadOptionsAction $showDownloadOptionsAction,
        protected SongUploadService $songUploadService,
        protected RequestSongAction $requestSongAction,
    ) {}

    // ─────────────────────────────────────────────────
    // پیام‌های متنی
    // ─────────────────────────────────────────────────

    public function handleMessage(Request $request): void
    {
        $message  = $request->input('message');
        $userId   = $message['from']['id'];
        $userName = $message['from']['first_name'] ?? 'دوست عزیز';
        $chatId   = $message['chat']['id'];

        // ✅ ثبت/به‌روزرسانی کاربر + نوتیفیکیشن «پیام دریافت شد»
        try {
            $this->botUserService->registerFromMessage($message, 'telegram');
            $this->notifier->messageReceived($message, 'telegram');
        } catch (\Throwable $e) {
            Log::warning('Failed to register/notify user', ['error' => $e->getMessage()]);
        }

        // کانال‌هایی که هنوز عضو نیست رو بگیر
        $missing = $this->membershipService->getMissingChannels($userId);

        if (empty($missing)) {
            // ✅ عضو همه کانال‌ها → کش رو پاک کن و پروسس کن
            $this->forgetPendingMessage($userId);
            $this->forgetLastJoinMessage($userId);

            // ۱. اگر پیام مدیا (عکس، ویدیو، آدیو و...) بود
            if ($this->isMediaMessage($message)) {
                $this->handleTelegramMediaMessage($message);
                return; // پردازش متن انجام نشود و خروج
            }

            if ($this->isInstaUrl($message)) {
                $this->handleInstaUrl($message);
                return;
            }

            // ۲. اگر پیام مدیا نبود، متن بررسی و پردازش شود
            $this->messageProcessor->process($message);
            return;
        }

        // ❌ هنوز عضو نیست
        // ۱. پیام اصلیش رو کش کن (برای اجرا بعد از عضو شدن)
        $this->cachePendingMessage($userId, $message);

        // ۲. پیام قبلی "عضو شو" رو پاک کن (اگه وجود داره)
        $this->deleteLastJoinMessage($chatId, $userId);

        // ۳. پیام جدید فقط با کانال‌های عضو نشده بفرست
        $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);

        if ($newMessageId) {
            $this->rememberLastJoinMessage($userId, $newMessageId);
        }
        return;
    }


    /**
     * بررسی اینکه آیا پیام تلگرام حاوی مدیا است یا خیر
     */
    private function isMediaMessage(array $message): bool
    {
        return isset($message['photo'])
            || isset($message['video'])
            || isset($message['audio'])
            || isset($message['voice'])
            || isset($message['video_note'])
            || isset($message['document']);
    }

    private function isInstaUrl(array $message): bool
    {
        $text = $this->getMessageText($message);

        if ($text === '') {
            return false;
        }

        return preg_match(
            '~(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:instagram\.com|instagr\.am)\/[^\s]+~iu',
            $text
        ) === 1;
    }

    private function handleInstaUrl(array $message): void
    {
        $chatId = $message['chat']['id'];

        $this->telegramService->sendMessage(
            $chatId,
            "🎵 پست اینستاگرام فرستادی؟\n\n"
                . "توی صف جستجو قرار گرفت 🔎\n"
                . "وقتی پیدا شد آهنگ رو همینجا برات ارسال میکنم 🫡⚡️\n"
        );
    }

    private function getMessageText(array $message): string
    {
        return trim(
            $message['text']
                ?? $message['caption']
                ?? ''
        );
    }

    /**
     * مدیریت و ارسال مدیای تلگرام به کانال کش
     */
    private function handleTelegramMediaMessage(array $message): void
    {
        $chatId    = $message['chat']['id'];
        $messageId = $message['message_id'];

        // کپی پیام به کانال کش تلگرام
        $this->telegramService->copyMessage($chatId, $messageId);

        $this->telegramService->sendMessage(
            $chatId,
            "🎵 فایل فرستادی؟\n\n"
                . "توی صف جستجو قرار گرفت 🔎\n"
                . "وقتی پیدا شد آهنگ رو همینجا برات ارسال میکنم 🫡⚡️\n"
        );
    }

    // ─────────────────────────────────────────────────
    // کلیک روی دکمه‌ها
    // ─────────────────────────────────────────────────

    public function handleCallback(Request $request): void
    {
        $callback  = $request->input('callback_query');
        $userId    = $callback['from']['id'];
        $userName  = $callback['from']['first_name'] ?? 'دوست عزیز';
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'];

        // ۱. دکمه "بررسی عضویت"
        if ($data === 'check_membership') {
            $this->handleMembershipCheck(
                $callback['id'],
                $chatId,
                $messageId,
                $userId,
                $userName
            );
            return;
        }

        // ۲. بقیه دکمه‌ها → اول چک کن عضو هست یا نه
        $missing = $this->membershipService->getMissingChannels($userId);
        if (!empty($missing)) {
            // ❌ هنوز عضو نیست → پیام قبلی "عضو شو" رو پاک کن و جدید بفرست
            $this->deleteLastJoinMessage($chatId, $userId);

            $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);

            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }

            return;
        }

        // ۳. عضو هست → دکمه‌های مربوط به آهنگ رو هندل کن
        if (str_starts_with($data, 'song_')) {
            $songId = (int) str_replace('song_', '', $data);
            $this->songUploadService->findBestLinkAndSendForTelegram($callback, $songId, $chatId, $this->telegramService);
            // $this->showDownloadOptionsAction->execute($callback['id'], $songId, $chatId, $messageId);
            // } elseif (str_starts_with($data, 'download_music_')) {
            //     $this->songUploadService->processAndSend($callback, $data, $chatId, $this->telegramService);
        } elseif (str_starts_with($data, 'request_song_')) {
            // 🆕 کاربر روی دکمه "هیچکدوم نبود - ثبت درخواست" زده
            $token = str_replace('request_song_', '', $data);
            $this->requestSongAction->execute($callback['id'], $token, $chatId, $messageId);
        }
    }

    // ─────────────────────────────────────────────────
    // منطق بررسی عضویت (وقتی روی دکمه «بررسی عضویت» زده شد)
    // ─────────────────────────────────────────────────

    private function handleMembershipCheck(
        string $callbackQueryId,
        int|string $chatId,
        int $messageId,
        int $userId,
        string $userName
    ): void {
        // پیام فعلی (که روش کلیک شده) رو پاک کن
        $this->telegramService->deleteMessage($chatId, $messageId);
        $this->forgetLastJoinMessage($userId);

        // دوباره چک کن چه کانال‌هایی هنوز عضو نیست
        $missing = $this->membershipService->getMissingChannels($userId);

        if (empty($missing)) {
            // ✅ همه کانال‌ها رو عضو شد
            $this->telegramService->answerCallbackQuery($callbackQueryId, '✅ عضویت تأیید شد!');

            $pendingMessage = $this->pullPendingMessage($userId);

            if ($pendingMessage) {
                // پیام اصلی کاربر رو اجرا کن
                $this->messageProcessor->process($pendingMessage);
            } else {
                // پیام در انتظار نداشت → فقط خوش‌آمد بگو
                $this->membershipService->sendSuccessMessage($chatId, $userName);
            }
        } else {
            // ❌ هنوز کانال‌هایی هست که عضو نشده
            // پیام جدید فقط با کانال‌های باقی‌مونده بفرست (بدون toast)
            $this->telegramService->answerCallbackQuery($callbackQueryId);

            $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);

            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }
        }
    }

    // ─────────────────────────────────────────────────
    // Cache: پیام در انتظار کاربر (برای اجرا بعد از عضو شدن)
    // ─────────────────────────────────────────────────

    private function pendingMessageKey(int $userId): string
    {
        return "telegram:pending_message:{$userId}";
    }

    private function cachePendingMessage(int $userId, array $message): void
    {
        $ttl = (int) config('telegram.pending_message_ttl', 10);
        Cache::put($this->pendingMessageKey($userId), $message, now()->addMinutes($ttl));
    }

    private function pullPendingMessage(int $userId): ?array
    {
        return Cache::pull($this->pendingMessageKey($userId));
    }

    private function forgetPendingMessage(int $userId): void
    {
        Cache::forget($this->pendingMessageKey($userId));
    }


    // ─────────────────────────────────────────────────
    // Cache: message_id آخرین پیام "عضو شو" (برای پاک کردن)
    // ─────────────────────────────────────────────────

    private function lastJoinMessageKey(int $userId): string
    {
        return "telegram:last_join_message:{$userId}";
    }

    private function rememberLastJoinMessage(int $userId, int $messageId): void
    {
        $ttl = (int) config('telegram.pending_message_ttl', 10);
        Cache::put($this->lastJoinMessageKey($userId), $messageId, now()->addMinutes($ttl));
    }

    /**
     * پیام قبلی "عضو شو" رو پاک می‌کنه (اگه وجود داشته باشه)
     * اگه قبلاً پاک شده باشه، خطا رو نادیده می‌گیره
     */
    private function deleteLastJoinMessage(int|string $chatId, int $userId): void
    {
        $lastMessageId = Cache::pull($this->lastJoinMessageKey($userId));

        if (!$lastMessageId) {
            return;
        }

        $response = $this->telegramService->deleteMessage($chatId, (int) $lastMessageId);

        if (!$response->successful()) {
            Log::debug('Could not delete previous join message (probably already deleted)', [
                'chat_id'    => $chatId,
                'message_id' => $lastMessageId,
                'error'      => $response->json('description'),
            ]);
        }
    }

    private function forgetLastJoinMessage(int $userId): void
    {
        Cache::forget($this->lastJoinMessageKey($userId));
    }
}
