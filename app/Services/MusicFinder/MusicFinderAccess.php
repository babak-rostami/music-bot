<?php

namespace App\Services\MusicFinder;

use Illuminate\Support\Facades\Cache;

/**
 * کنترل دسترسی و هم‌زمانی درخواست‌های Music Finder برای کاربران.
 *
 * این کلاس دو مسئولیت مستقل دارد:
 *
 * 1. کنترل دسترسی به قابلیت Music Finder:
 *    - وقتی قابلیت برای همه فعال باشد، تمام کاربران مجاز هستند.
 *    - در حالت تست، فقط ادمین همان پلتفرم می‌تواند از قابلیت استفاده کند.
 *
 * 2. جلوگیری از ارسال چند درخواست هم‌زمان توسط یک کاربر:
 *    اگر یک کاربر در حال پردازش یک درخواست Music Finder باشد، درخواست
 *    جدید همان کاربر پذیرفته نمی‌شود تا پاسخ‌های چند درخواست با یکدیگر
 *    قاطی نشوند.
 *
 * برای کنترل درخواست هم‌زمان، یک کلید Cache مخصوص هر کاربر و پلتفرم
 * ساخته می‌شود و با Cache::add به‌صورت اتمیک رزرو می‌شود.
 *
 * این Lock با Lock مربوط به MusicFinderService تفاوت دارد:
 *
 * - MusicFinderAccess:
 *   مشخص می‌کند یک کاربر خودش هم‌زمان چند درخواست داشته باشد یا نه.
 *
 * - MusicFinderService:
 *   از یک Music Finder خارجی در برابر درخواست‌های هم‌زمان محافظت می‌کند
 *   تا پاسخ‌های کاربران مختلف با یکدیگر قاطی نشوند.
 *
 * رزرو کاربر دارای TTL است تا اگر Process به هر دلیلی قبل از release
 * شدن متوقف شد، وضعیت Busy برای همیشه باقی نماند.
 */
class MusicFinderAccess
{
    //اگر به هر دلیلی release() اجرا نشد، حداکثر بعد از ۴۵ ثانیه وضعیت Busy خودکار منقضی شود.
    private const BUSY_TTL_SECONDS = 45;

    /**
     * بررسی می‌کند کاربر اجازه استفاده از Music Finder را دارد یا خیر.
     *
     * اگر قابلیت برای همه فعال شده باشد، تمام کاربران مجاز هستند.
     * در غیر این صورت، قابلیت فقط برای ادمین همان پلتفرم در دسترس است.
     */
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
     * مشخص می‌کند Music Finder هنوز در حالت تست است یا برای همه کاربران
     * فعال شده است.
     *
     * مقدار true یعنی قابلیت هنوز عمومی نشده و فقط کاربران مجاز تست
     * می‌توانند از آن استفاده کنند.
     */
    public static function isTestMode(): bool
    {
        return !config('services.music_finder_bot.enabled_for_all');
    }

    /**
     * تلاش می‌کند یک کاربر را برای پردازش یک درخواست Music Finder رزرو کند.
     *
     * اگر کاربر در حال حاضر درخواست دیگری در حال پردازش داشته باشد،
     * رزرو انجام نمی‌شود و false برگردانده می‌شود.
     *
     * در صورت موفقیت، true برگردانده شده و کاربر تا پایان TTL به‌عنوان
     * Busy شناخته می‌شود.
     *
     * Cache::add به‌صورت اتمیک عمل می‌کند؛ بنابراین اگر دو درخواست
     * هم‌زمان از یک کاربر برسند، فقط یکی از آن‌ها می‌تواند رزرو را بگیرد.
     */
    public static function tryReserve(string $platform, int|string $chatId): bool
    {
        return Cache::add(self::busyKey($platform, $chatId), true, self::BUSY_TTL_SECONDS);
    }

    /**
     * رزرو فعال کاربر را آزاد می‌کند تا درخواست بعدی او بتواند پردازش شود.
     *
     * این متد باید پس از پایان پردازش، چه در حالت موفق و چه در حالت خطا،
     * اجرا شود؛ معمولاً با استفاده از finally تا در صورت رخ دادن Exception
     * نیز وضعیت Busy باقی نماند.
     */
    public static function release(string $platform, int|string $chatId): void
    {
        Cache::forget(self::busyKey($platform, $chatId));
    }

    /**
     * کلید Cache یکتا برای وضعیت پردازش هم‌زمان کاربر ایجاد می‌کند.
     */
    private static function busyKey(string $platform, int|string $chatId): string
    {
        return "music_finder_busy:{$platform}:{$chatId}";
    }
}
