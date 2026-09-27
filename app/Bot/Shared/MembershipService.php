<?php

namespace App\Bot\Shared;

use Illuminate\Support\Facades\Log;

class MembershipService
{
    private const MEMBER_STATUSES = ['member', 'administrator', 'creator'];

    public function getMissingChannels(BotApiInterface $bot, int $userId): array
    {
        $channels = $bot->platform() === 'bale'
            ? config('telegram.bale_required_channels', config('telegram.required_channels', []))
            : config('telegram.required_channels', []);

        $missing = [];

        foreach ($channels as $channel) {
            $member = $bot->getChatMember($channel['chat_id'], $userId);

            if (!$member) {
                $missing[] = $channel;
                continue;
            }

            if (!in_array($member['status'] ?? '', self::MEMBER_STATUSES, true)) {
                $missing[] = $channel;
            }
        }

        return $missing;
    }

    public function sendJoinMessage(BotApiInterface $bot, int|string $chatId, array $missingChannels, string $userName = 'دوست عزیز'): ?int
    {
        if (empty($missingChannels)) {
            return null;
        }

        $text = "🙋‍♂️ سلام {$userName}، خوش اومدی\n\n"
            . "برای استفاده از ربات لطفا اول عضو کانال شو.\n\n"
            . "بعد روی دکمه «✅ بررسی عضویت» بزن.\n\n"
            . "روی اسم کانال ها کلیک کن و عضو شو 👇";

        $buttons = [];
        foreach ($missingChannels as $channel) {
            $buttons[] = [['text' => '📢 ' . $channel['name'], 'url' => $channel['url']]];
        }

        $callbackData = $bot->platform() === 'bale' ? 'bale_check_membership' : 'check_membership';
        $buttons[] = [['text' => '✅ بررسی عضویت', 'callback_data' => $callbackData]];

        $response = $bot->sendMessage($chatId, $text, $buttons);

        if (!$response->successful()) {
            Log::warning('Failed to send join message', [
                'chat_id'  => $chatId,
                'response' => $response->json('description'),
            ]);
            return null;
        }

        return (int) $response->json('result.message_id');
    }

    public function sendSuccessMessage(BotApiInterface $bot, int|string $chatId, string $userName = 'دوست عزیز'): void
    {
        $bot->sendMessage(
            $chatId,
            "✅ {$userName} عزیز، عضویتت تأیید شد! 🎉\n\n
            حالا می‌تونی از ربات استفاده کنی\n\n
            لطفاً درخواستت رو بفرست"
        );
    }
}
