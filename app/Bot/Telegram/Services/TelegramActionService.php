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
        return false; // بات تلگرام همیشه پیام‌های خصوصی رو پردازش می‌کنه
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

        $bot->copyMessage($chatId, $messageId);
    }
}
