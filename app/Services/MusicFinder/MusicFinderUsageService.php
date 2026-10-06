<?php

namespace App\Services\MusicFinder;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\MusicFinderRequest;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;

/**
 * مدیریت سهمیه استفاده از جستجوی پیشرفته Music Finder برای هر کاربر.
 *
 * این سرویس مسئول ثبت درخواست‌های Music Finder، بررسی میزان مصرف سهمیه،
 * محاسبه تعداد درخواست‌های باقی‌مانده و تعیین محدودیت فعلی کاربر است.
 *
 * سهمیه فقط برای جستجوهای انجام‌شده از سرویس خارجی Music Finder مصرف می‌شود.
 * تحویل آهنگ از دیتابیس داخلی رایگان و نامحدود است و فقط برای آمارگیری ثبت می‌شود.
 *
 * محدودیت کاربر بر اساس وضعیت فعلی اشتراک تعیین می‌شود:
 *
 * - اگر کاربر اشتراک فعال داشته باشد، محدودیت Snapshot‌شده همان اشتراک
 *   استفاده می‌شود.
 * - اگر اشتراک فعال نداشته باشد، محدودیت رایگان از config خوانده می‌شود.
 *
 * Snapshot یعنی مقادیر پلن مانند تعداد درخواست و بازه زمانی در زمان خرید
 * داخل Subscription ذخیره شده‌اند و تغییرات بعدی پلن روی اشتراک‌های قبلی
 * تأثیری نمی‌گذارد.
 *
 * سهمیه به‌صورت Sliding Window محاسبه می‌شود؛ یعنی فقط درخواست‌هایی که
 * در بازه زمانی تعیین‌شده اخیر ثبت شده‌اند در مصرف فعلی حساب می‌شوند.
 *
 * برای مثال اگر سهمیه کاربر 3 درخواست در هر 12 ساعت باشد، فقط درخواست‌های
 * 12 ساعت اخیر بررسی می‌شوند. با خارج شدن قدیمی‌ترین درخواست از این بازه،
 * دوباره یک ظرفیت از سهمیه آزاد می‌شود.
 *
 * این سرویس مستقیماً جدول Payments را بررسی نمی‌کند. Payment فقط نشان‌دهنده
 * تراکنش مالی است و وضعیت دسترسی فعلی کاربر از Subscription مشخص می‌شود.
 */
class MusicFinderUsageService
{
    /**
     * تحویل موفق یک آهنگ از دیتابیس داخلی را ثبت می‌کند.
     *
     * این نوع درخواست رایگان و نامحدود است و در محاسبه سهمیه جستجوی پیشرفته
     * کاربر لحاظ نمی‌شود.
     *
     * ثبت این اطلاعات صرفاً برای آمارگیری و بررسی میزان استفاده از دیتابیس
     * داخلی انجام می‌شود.
     */
    public static function recordDatabaseDelivery(string $platform, int|string $userId, int $songId): void
    {
        MusicFinderRequest::create([
            'platform' => $platform,
            'user_id'  => $userId,
            'source'   => MusicFinderRequest::SOURCE_DATABASE,
            'song_id'  => $songId,
        ]);
    }

    /**
     * تحویل موفق یک آهنگ از سرویس خارجی Music Finder را ثبت می‌کند.
     *
     * این نوع درخواست مصرف سهمیه جستجوی پیشرفته محسوب می‌شود و در محاسبه
     * تعداد درخواست‌های استفاده‌شده کاربر قرار می‌گیرد.
     *
     * queryText نیز در صورت وجود برای ثبت عبارت یا ورودی جستجو ذخیره می‌شود.
     */
    public static function recordExternalDelivery(string $platform, int|string $userId, ?string $queryText): void
    {
        MusicFinderRequest::create([
            'platform'   => $platform,
            'user_id'    => $userId,
            'source'     => MusicFinderRequest::SOURCE_EXTERNAL,
            'query_text' => $queryText,
        ]);
    }

    /**
     * بررسی می‌کند کاربر اجازه انجام یک جستجوی پیشرفته جدید را دارد یا خیر.
     *
     * ابتدا محدودیت فعلی کاربر، شامل تعداد درخواست و بازه زمانی، مشخص می‌شود.
     * سپس تعداد درخواست‌های خارجی ثبت‌شده در همان بازه بررسی می‌شود.
     *
     * اگر سهمیه باقی مانده باشد، null برگردانده می‌شود تا درخواست بتواند
     * ادامه پیدا کند.
     *
     * اگر سهمیه تمام شده باشد، زمان دقیق آزاد شدن اولین درخواست مصرف‌شده
     * محاسبه شده و پیام آماده اطلاع‌رسانی به کاربر برگردانده می‌شود.
     *
     * این متد صرفاً سهمیه را بررسی می‌کند و خودش مصرف جدیدی ثبت نمی‌کند.
     * ثبت مصرف فقط پس از تحویل موفق نتیجه توسط recordExternalDelivery انجام می‌شود.
     */
    public static function guard(string $platform, int|string $userId): ?string
    {
        ['limit' => $limit, 'window_hours' => $windowHours] = self::activePlanLimits($platform, $userId);

        $recent = MusicFinderRequest::where('platform', $platform)
            ->where('user_id', $userId)
            ->where('source', MusicFinderRequest::SOURCE_EXTERNAL)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->orderBy('created_at')
            ->get(['created_at']);

        if ($recent->count() < $limit) {
            return null;
        }

        $retryAfter = $recent->first()->created_at->copy()->addHours($windowHours);

        return self::formatQuotaMessage($retryAfter, $limit, $windowHours);
    }

    /**
     * تعداد درخواست‌های باقی‌مانده کاربر در بازه فعلی را محاسبه می‌کند.
     *
     * فقط درخواست‌های انجام‌شده از سرویس خارجی در بازه زمانی فعلی در نظر گرفته
     * می‌شوند و درخواست‌های دیتابیس داخلی در سهمیه محاسبه نمی‌شوند.
     *
     * مقدار نهایی هیچ‌وقت کمتر از صفر برگردانده نمی‌شود.
     */
    public static function remainingCount(string $platform, int|string $userId): int
    {
        ['limit' => $limit, 'window_hours' => $windowHours] = self::activePlanLimits($platform, $userId);

        $used = MusicFinderRequest::where('platform', $platform)
            ->where('user_id', $userId)
            ->where('source', MusicFinderRequest::SOURCE_EXTERNAL)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->count();

        return max(0, $limit - $used);
    }

    /**
     * پیام نمایش سهمیه باقی‌مانده را برای کاربر ایجاد می‌کند.
     *
     * این متد معمولاً پس از یک جستجوی پیشرفته موفق استفاده می‌شود تا تعداد
     * درخواست‌های باقی‌مانده به کاربر اطلاع داده شود.
     *
     * اگر سهمیه کاملاً مصرف شده باشد، null برمی‌گرداند تا پیام دیگری مانند
     * پیام اتمام سهمیه نمایش داده شود.
     */
    public static function remainingQuotaMessage(string $platform, int|string $userId): ?string
    {
        ['limit' => $limit, 'window_hours' => $windowHours] = self::activePlanLimits($platform, $userId);

        $used = MusicFinderRequest::where('platform', $platform)
            ->where('user_id', $userId)
            ->where('source', MusicFinderRequest::SOURCE_EXTERNAL)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->count();

        $remaining = $limit - $used;

        if ($remaining <= 0) {
            return null;
        }

        return "🎁 {$remaining} از {$limit} درخواست جستجوی پیشرفته‌ات برای امروز باقی مونده.\n\n"
            . "هر {$windowHours} ساعت {$limit} درخواست هدیه داری 💎\n\n"
            . "👇جستجوی پیشرفته یعنی👇\n"
            . "جستجو با لینک یوتیوب و اینستاگرام و تیک‌تاک\n"
            . "جستجو با ویدیو\n"
            . "جستجو با صدای آهنگ";
    }

    /**
     * محدودیت فعلی کاربر را برمی‌گرداند.
     *
     * خروجی شامل تعداد درخواست مجاز در هر بازه و طول همان بازه زمانی است.
     *
     * اگر کاربر اشتراک فعال داشته باشد، محدودیت Snapshot‌شده اشتراک برگردانده
     * می‌شود؛ در غیر این صورت محدودیت رایگان از config خوانده می‌شود.
     *
     * این متد بیشتر برای نمایش وضعیت فعلی کاربر، مانند /status، استفاده می‌شود.
     */
    public static function currentLimits(string $platform, int|string $userId): array
    {
        return self::activePlanLimits($platform, $userId);
    }


    /**
     * محدودیت فعال فعلی کاربر را تعیین می‌کند.
     *
     * ابتدا اشتراک فعال و منقضی‌نشده کاربر بررسی می‌شود.
     *
     * اگر اشتراک فعال وجود داشته باشد، تعداد درخواست و بازه زمانی از مقادیر
     * Snapshot‌شده همان اشتراک خوانده می‌شود تا محدودیت زمان خرید حفظ شود.
     *
     * اگر اشتراک فعالی وجود نداشته باشد، کاربر در حالت رایگان در نظر گرفته شده
     * و محدودیت پیش‌فرض از config خوانده می‌شود.
     *
     * @return array{limit: int, window_hours: int}
     */
    private static function activePlanLimits(string $platform, int|string $userId): array
    {
        $activeSubscription = Subscription::where('platform', $platform)
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::Active->value)
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();

        if ($activeSubscription) {
            return [
                'limit'        => $activeSubscription->requests_per_window_snapshot,
                'window_hours' => $activeSubscription->window_hours_snapshot,
            ];
        }

        return [
            'limit'        => (int) config('services.music_finder_bot.free_requests_limit', 3),
            'window_hours' => (int) config('services.music_finder_bot.free_requests_window_hours', 12),
        ];
    }

    /**
     * پیام نهایی اتمام سهمیه را ایجاد می‌کند.
     *
     * زمان باقی‌مانده تا آزاد شدن اولین درخواست مصرف‌شده را محاسبه کرده و
     * آن را به شکل قابل فهم برای کاربر، شامل ساعت و دقیقه، نمایش می‌دهد.
     *
     * علاوه بر زمان باقی‌مانده، تعداد درخواست مجاز و بازه زمانی سهمیه نیز
     * در پیام نمایش داده می‌شود.
     */
    private static function formatQuotaMessage(Carbon $retryAfter, int $limit, int $windowHours): string
    {
        $diffInMinutes = max(1, (int) now()->diffInMinutes($retryAfter, false));
        $hours = intdiv($diffInMinutes, 60);
        $minutes = $diffInMinutes % 60;

        $remaining = $hours > 0 ? "{$hours} ساعت و {$minutes} دقیقه" : "{$minutes} دقیقه";

        return "🎁 سهمیه جستجوی پیشرفته امروز تمام شد.\n\n"
            . "⏳ {$remaining} دیگه دوباره فعال میشه.\n\n"
            . "({$limit} درخواست در هر {$windowHours} ساعت هدیه🎁)\n\n"
            . "❌توجه❌\n"
            . "جستجو با متن آهنگ، رایگان و نامحدوده\n\n"
            . "👇جستجوی پیشرفته یعنی👇\n"
            . "جستجو با لینک یوتیوب و اینستاگرام و تیک‌تاک\n"
            . "جستجو با ویدیو\n"
            . "جستجو با صدای آهنگ";
    }
}
