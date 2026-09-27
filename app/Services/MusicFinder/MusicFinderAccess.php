<?php

namespace App\Services\MusicFinder;

use Illuminate\Support\Facades\Cache;

class MusicFinderAccess
{
    private const BUSY_TTL_SECONDS = 90;

    public static function allowed(string $platform, int $userId): bool
    {
        if (config('services.music_finder_bot.enabled_for_all')) {
            return true;
        }

        $adminId = $platform === 'bale'
            ? config('telegram.bale_admin_id')
            : config('telegram.telegram_admin_id');

        return $adminId && (string) $userId === (string) $adminId;
    }

    /**
     * وقتی سرویس هنوز برای همه فعال نشده (حالت تست ادمین) true برمی‌گردونه.
     * برای این استفاده می‌شه که پیام‌های قدیمی «صف دانلود» فقط توی حالت تست نشون داده بشن.
     */
    public static function isTestMode(): bool
    {
        return !config('services.music_finder_bot.enabled_for_all');
    }

    /**
     * تلاش برای رزرو کردن نوبت این کاربر. اگه قبلاً یه درخواست از همین کاربر
     * در حال پردازشه، false برمی‌گردونه (یعنی نباید درخواست جدید دیسپچ بشه).
     */
    public static function tryReserve(string $platform, int|string $chatId): bool
    {
        return Cache::add(self::busyKey($platform, $chatId), true, self::BUSY_TTL_SECONDS);
    }

    /**
     * آزاد کردن نوبت کاربر — باید همیشه بعد از اتمام کار (موفق یا ناموفق) صدا زده بشه.
     */
    public static function release(string $platform, int|string $chatId): void
    {
        Cache::forget(self::busyKey($platform, $chatId));
    }

    private static function busyKey(string $platform, int|string $chatId): string
    {
        return "music_finder_busy:{$platform}:{$chatId}";
    }
}
