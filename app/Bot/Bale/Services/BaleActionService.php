<?php

namespace App\Bot\Bale\Services;

use App\Bot\Bale\MessageProcessor;
use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Bot\Shared\BaseBotActionService;
use App\Bot\Shared\BotAdminService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\MembershipService;
use App\Services\AdminNotifier;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SubscriptionPricingService;
use App\Services\SongReactionService;
use App\Services\SongUploadService;
use Illuminate\Http\Request;

class BaleActionService extends BaseBotActionService
{
    public function __construct(
        BotUserService $botUserService,
        NotificationService $notifier,
        AdminNotifier $adminNotifier,
        MembershipService $membershipService,
        BotAdminService $adminService,
        SongUploadService $songUploadService,
        SongReactionService $reactionService,
        MessageProcessor $messageProcessor,
        PaymentService $paymentService,
        SubscriptionPricingService $pricingService,
    ) {
        parent::__construct(
            $botUserService,
            $notifier,
            $adminNotifier,
            $membershipService,
            $adminService,
            $songUploadService,
            $reactionService,
            $messageProcessor,
            $paymentService,
            $pricingService
        );
    }

    public function handleMessage(Request $request): void
    {
        $this->processMessage($request->input('message'));
    }

    public function handleCallback(Request $request): void
    {
        $this->processCallback($request->input('callback_query'));
    }

    protected function platform(): string
    {
        return 'bale';
    }

    protected function isGroupOrChannelMessage(array $message): bool
    {
        $chatType = $message['chat']['type'] ?? 'private';
        return $chatType !== 'private';
    }

    protected function resolveUserName(array $from): string
    {
        return $from['first_name'] ?? $from['username'] ?? 'دوست عزیز';
    }

    protected function membershipCheckCallbackData(): string
    {
        return 'bale_check_membership';
    }

    protected function handleGenericMediaMessage(BotApiInterface $bot, array $message): void
    {
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];

        // مدیای کاربرها جدا از کانال کش آهنگ‌ها (bale_cache_channel_id) نگه داشته
        // می‌شه تا اون کانال فقط برای file_id آهنگ‌های واقعی باقی بمونه. اگه
        // bale_user_media_channel_id تنظیم نشده باشه، forwardMessage خودش
        // برمی‌گرده به bale_cache_channel_id (رفتار قبلی).
        $bot->forwardMessage($chatId, $messageId, config('telegram.bale_user_media_channel_id'));
        // $bot->sendMessage(
        //     $chatId,
        //     "✅ فایلی که فرستادی با موفقیت ثبت شد\n\nآهنگ توی صف دانلود قرار گرفت 🔎\n\n💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!"
        // );
    }
}
