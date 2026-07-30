<?php

namespace App\Bot\Telegram\Services;

use Illuminate\Support\Facades\Log;

class MembershipService
{
    /**
     * وضعیت‌هایی که یعنی کاربر واقعاً عضو کانال است
     */
    private const MEMBER_STATUSES = ['member', 'administrator', 'creator'];

    public function __construct(protected TelegramService $telegram) {}

    /**
     * چک می‌کنه آیا کاربر عضو همه کانال‌های اسپانسر هست یا نه
     */
    public function isMember(int $userId): bool
    {
        return empty($this->getMissingChannels($userId));
    }

    /**
     * لیست کانال‌هایی که کاربر هنوز عضو آنها نیست
     *
     * @return array<int, array{name: string, url: string, chat_id: string}>
     */
    public function getMissingChannels(int $userId): array
    {
        $channels = config('telegram.required_channels', []);
        $missing = [];

        foreach ($channels as $channel) {
            $member = $this->telegram->getChatMember($channel['chat_id'], $userId);

            // اگه API خطا داد (ربات ادمین نیست، کانال پیدا نشد و...) → فرض می‌کنیم عضو نیست
            if (!$member) {
                Log::info('User is not member (API error or not found)', [
                    'user_id' => $userId,
                    'channel' => $channel['chat_id'] ?? null,
                ]);
                $missing[] = $channel;
                continue;
            }

            $status = $member['status'] ?? '';

            if (!in_array($status, self::MEMBER_STATUSES, true)) {
                $missing[] = $channel;
            }
        }

        return $missing;
    }

    /**
     * پیام "لطفاً عضو کانال‌ها بشو" رو فقط با کانال‌های عضو نشده می‌فرسته
     *
     * @param int|string $chatId
     * @param array $missingChannels  لیست کانال‌هایی که کاربر هنوز عضو آنها نیست
     * @param string $userName        نام کاربر برای شخصی‌سازی
     * @return int|null               message_id پیام ارسال‌شده، یا null اگه ارسال نشد
     */
    public function sendJoinMessage(int|string $chatId, array $missingChannels, string $userName = 'دوست عزیز'): ?int
    {
        // اگه لیست خالیه، پیامی نمی‌فرستیم (کاربر عضو همه کانال‌ها شده)
        if (empty($missingChannels)) {
            return null;
        }

        $text = "🙋‍♂️ سلام {$userName}، خوش اومدی\n\n"
            . "برای استفاده از ربات باید عضو کانال‌های اسپانسر بشی.\n"
            . "بعد از عضویت، روی دکمه «✅ بررسی عضویت» بزن.\n\n"
            . "روی اسم کانال ها کلیک کن و عضو شو 👇";

        // ساخت دکمه‌ها: هر کانال یک ردیف، دکمه بررسی عضویت در ردیف آخر
        $buttons = [];

        foreach ($missingChannels as $channel) {
            $buttons[] = [
                ['text' => '📢 ' . $channel['name'], 'url' => $channel['url']],
            ];
        }

        $buttons[] = [
            ['text' => '✅ بررسی عضویت', 'callback_data' => 'check_membership'],
        ];

        $response = $this->telegram->sendMessage($chatId, $text, $buttons);

        if (!$response->successful()) {
            Log::warning('Failed to send join message', [
                'chat_id' => $chatId,
                'response' => $response->json('description'),
            ]);
            return null;
        }

        return (int) $response->json('result.message_id');
    }

    /**
     * پیام موفقیت‌آمیز بودن عضویت رو می‌فرسته (وقتی pending message نداریم)
     */
    public function sendSuccessMessage(int|string $chatId, string $userName = 'دوست عزیز'): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "✅ {$userName} عزیز، عضویتت تأیید شد! 🎉\n\n"
                . "حالا می‌تونی از ربات استفاده کنی. لطفاً درخواستت رو بفرست."
        );
    }
}
