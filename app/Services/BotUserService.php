<?php

namespace App\Services;

use App\Models\BotUser;
use Illuminate\Support\Facades\Log;

/**
 * ─────────────────────────────────────────────────────────────────────
 *  سرویس مدیریت کاربران ربات
 * ─────────────────────────────────────────────────────────────────────
 *
 *  مسئولیت‌ها:
 *
 *  ۱) registerFromMessage()  → ثبت یا به‌روزرسانی کاربر وقتی پیامی می‌فرستد
 *     • کاربر جدید → ثبت + نوتیفیکیشن «کاربر جدید»
 *     • کاربر قدیمی → به‌روزرسانی نام/یوزرنیم/فعالیت
 *     • اگر کاربر قبلاً بلاک‌شده بود ولی برگشته → ریست شمارش خطا
 *
 *     جستجو بر اساس (platform, user_id) انجام می‌شود چون آیدی تلگرام
 *     و بله مستقل هستند.
 *
 *  ۲) recordFailedSend()     → ثبت یک ارسال ناموفق
 *     • شمارش failed_send_count را یکی اضافه می‌کند
 *     • اگر از آستانه عبور کرد → is_blocked = true + نوتیفیکیشن
 *
 *  ۳) recordSuccessfulSend() → ریست شمارش خطا بعد از ارسال موفق
 */
class BotUserService
{
    /**
     * تعداد خطای متوالی که بعد از آن کاربر «بلاک‌شده» در نظر گرفته می‌شود.
     * از کانفیگ خوانده می‌شود، پیش‌فرض ۳.
     */
    private function blockThreshold(): int
    {
        return (int) config('telegram.blocked_threshold', 3);
    }

    // ═══════════════════════════════════════════════════
    //  ثبت / به‌روزرسانی کاربر
    // ═══════════════════════════════════════════════════

    /**
     * وقتی کاربر پیامی می‌فرستد، اطلاعاتش را ثبت یا به‌روزرسانی می‌کند.
     *
     * جستجو بر اساس (platform, user_id) چون آیدی هر پلتفرم مستقل است.
     *
     * @param array  $message  آبجکت message از تلگرام/بله
     * @param string $platform telegram | bale
     */
    public function registerFromMessage(array $message, string $platform): BotUser
    {
        $from   = $message['from'] ?? [];
        $userId = $from['id'] ?? 0;

        if (!$userId) {
            throw new \InvalidArgumentException('user_id در پیام وجود ندارد');
        }

        $isNewUser = false;

        /** @var BotUser|null $botUser */
        $botUser = BotUser::where('platform', $platform)
            ->where('user_id', $userId)
            ->first();

        if (!$botUser) {
            // ── کاربر جدید در این پلتفرم ──
            $botUser = BotUser::create([
                'platform'          => $platform,
                'user_id'           => $userId,
                'username'          => $from['username'] ?? null,
                'first_name'        => $from['first_name'] ?? null,
                'last_name'         => $from['last_name'] ?? null,
                'failed_send_count' => 0,
                'is_blocked'        => false,
                'last_activity_at'  => now(),
            ]);
            $isNewUser = true;
        } else {
            // ── کاربر قدیمی → به‌روزرسانی ──
            $updates = [
                'username'         => $from['username'] ?? $botUser->username,
                'first_name'       => $from['first_name'] ?? $botUser->first_name,
                'last_name'        => $from['last_name'] ?? $botUser->last_name,
                'last_activity_at' => now(),
            ];

            // اگر کاربر فعال شده (پیام فرستاده) → خطاهای قبلی را صفر کن
            if ($botUser->failed_send_count > 0) {
                $updates['failed_send_count'] = 0;
            }

            // اگر قبلاً بلاک شده بود ولی دوباره پیام فرستاده → یعنی بلاک را برداشته
            if ($botUser->is_blocked) {
                $updates['is_blocked'] = false;
                $updates['blocked_at'] = null;
            }

            $botUser->update($updates);
            $botUser->refresh();
        }

        // نوتیفیکیشن کاربر جدید
        if ($isNewUser) {
            try {
                app(NotificationService::class)->userJoined($botUser, $platform);
            } catch (\Throwable $e) {
                Log::warning('BotUserService: نوتیفیکیشن کاربر جدید ناموفق', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $botUser;
    }

    // ═══════════════════════════════════════════════════
    //  ردیابی بلاک شدن
    // ═══════════════════════════════════════════════════

    /**
     * ثبت یک ارسال ناموفق (برای تشخیص بلاک شدن)
     *
     * هر بار ارسال پیام به کاربر ناموفق باشد، این متد را صدا بزنید.
     * وقتی شمارش خطا از آستانه عبور کند → کاربر بلاک‌شده علامت می‌خورد.
     *
     * @param int    $userId   شناسه کاربر داخل همان پلتفرم
     * @param string $platform telegram | bale
     */
    public function recordFailedSend(int $userId, string $platform): ?BotUser
    {
        /** @var BotUser|null $botUser */
        $botUser = BotUser::where('platform', $platform)
            ->where('user_id', $userId)
            ->first();

        if (!$botUser) {
            return null;
        }

        $wasBlocked = $botUser->is_blocked;

        $botUser->increment('failed_send_count');
        $botUser->refresh();

        // اگر از آستانه عبور کرد و هنوز بلاک‌شده علامت نخورده → بلاک کن
        if (!$wasBlocked && $botUser->failed_send_count >= $this->blockThreshold()) {
            $botUser->update([
                'is_blocked' => true,
                'blocked_at' => now(),
            ]);
            $botUser->refresh();

            try {
                app(NotificationService::class)->userBlocked($botUser, $platform);
            } catch (\Throwable $e) {
                Log::warning('BotUserService: نوتیفیکیشن بلاک‌شدن ناموفق', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $botUser;
    }

    /**
     * بعد از ارسال موفق، شمارش خطا را صفر کن (کاربر فعال است)
     *
     * @param int    $userId   شناسه کاربر داخل همان پلتفرم
     * @param string $platform telegram | bale
     */
    public function recordSuccessfulSend(int $userId, string $platform): void
    {
        /** @var BotUser|null $botUser */
        $botUser = BotUser::where('platform', $platform)
            ->where('user_id', $userId)
            ->first();

        if (!$botUser) {
            return;
        }

        if ($botUser->failed_send_count > 0 || $botUser->is_blocked) {
            $botUser->update([
                'failed_send_count' => 0,
                'is_blocked'        => false,
                'blocked_at'        => null,
            ]);
        }
    }

    // ═══════════════════════════════════════════════════
    //  Helper: تشخیص اینکه آیا خطا به‌خاطر بلاک‌بودن کاربر است
    // ═══════════════════════════════════════════════════

    /**
     * بررسی می‌کند که آیا پاسخ API نشان‌دهنده بلاک‌بودن کاربر است یا خیر.
     *
     * کدها/متن‌های رایج:
     *  - HTTP 403 Forbidden
     *  - "bot was blocked by the user"
     *  - "chat not found"
     *  - "user is deactivated"
     */
    public static function isBlockedError($response): bool
    {
        if (!method_exists($response, 'successful') || $response->successful()) {
            return false;
        }

        $status      = $response->status();
        $description = strtolower((string) $response->json('description', ''));

        return $status === 403
            || str_contains($description, 'blocked')
            || str_contains($description, 'chat not found')
            || str_contains($description, 'user is deactivated')
            || str_contains($description, 'chat deactivated');
    }
}
