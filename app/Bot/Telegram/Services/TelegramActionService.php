<?php

namespace App\Bot\Telegram\Services;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Bot\Shared\BaseBotActionService;
use App\Bot\Shared\BotAdminService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\MembershipService;
use App\Bot\Telegram\MessageProcessor;
use App\Services\AdminNotifier;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SubscriptionPricingService;
use App\Services\SongReactionService;
use App\Services\SongUploadService;
use Illuminate\Http\Request;

class TelegramActionService extends BaseBotActionService
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
        return 'telegram';
    }

    protected function isGroupOrChannelMessage(array $message): bool
    {
        // قبلاً این متد همیشه false برمی‌گردوند (با این توجیه که «بات تلگرام
        // همیشه پیام‌های خصوصی رو پردازش می‌کنه»)، در حالی که هدف واقعی متد
        // دقیقاً برعکسه: تشخیص اینکه پیام از گروه/کانال اومده تا نادیده گرفته
        // بشه. مقدار ثابت false یعنی اگه بات به هر دلیلی (حتی تصادفی) عضو یه
        // گروه/سوپرگروه بشه، پیام‌های اون گروه هم دقیقاً مثل پیام خصوصی کاربر
        // پردازش می‌شدن — ثبت BotUser، جستجوی آهنگ، همه‌چی. اینجا مثل نسخه‌ی
        // بله، واقعاً نوع چت رو چک می‌کنیم.
        $chatType = $message['chat']['type'] ?? 'private';

        return $chatType !== 'private';
    }

    protected function resolveUserName(array $from): string
    {
        return $from['first_name'] ?? 'دوست عزیز';
    }

    protected function membershipCheckCallbackData(): string
    {
        return 'check_membership';
    }

    protected function handleGenericMediaMessage(BotApiInterface $bot, array $message): void
    {
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];

        // مدیای کاربرها جدا از کانال کش آهنگ‌ها (cache_channel_id) نگه داشته می‌شه
        // تا اون کانال فقط برای file_id آهنگ‌های واقعی باقی بمونه. اگه
        // user_media_channel_id تنظیم نشده باشه، copyMessage خودش برمی‌گرده
        // به cache_channel_id (رفتار قبلی).
        $bot->copyMessage($chatId, $messageId, config('telegram.user_media_channel_id'));
    }
}
