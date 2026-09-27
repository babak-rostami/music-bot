<?php

namespace App\Services\MusicFinder;

use App\Models\MusicFinderRequest;
use Carbon\Carbon;

class MusicFinderUsageService
{
    public static function recordDatabaseDelivery(string $platform, int|string $userId, int $songId): void
    {
        MusicFinderRequest::create([
            'platform' => $platform,
            'user_id'  => $userId,
            'source'   => MusicFinderRequest::SOURCE_DATABASE,
            'song_id'  => $songId,
        ]);
    }

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
     * اگه کاربر توی محدودیت باشه null برمی‌گردونه (اجازه‌ی ادامه)،
     * وگرنه متن آماده‌ی «سهمیه تموم شده» رو برمی‌گردونه که مستقیم بفرستی به کاربر.
     */
    public static function guard(string $platform, int|string $userId): ?string
    {
        $limit = (int) config('services.music_finder_bot.free_requests_limit', 3);
        $windowHours = (int) config('services.music_finder_bot.free_requests_window_hours', 12);

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
            . "جستجو با لینک یوتیوب و اینستاگرام و تیک تاک\n"
            . "جستجو با ویدیو\n"
            . "جستجو با صدای آهنگ";
    }

    /**
     * بعد از یه تحویل موفق صدا زده می‌شه؛ اگه هنوز سهمیه باقی مونده بود،
     * یه پیام دوستانه برمی‌گردونه؛ اگه دقیقاً همین الان صفر شد، چیزی برنمی‌گردونه
     * (چون خود پیام «سهمیه تمام شد» دفعه‌ی بعدی که درخواست بده نشونش می‌دیم، نه همین لحظه).
     */
    public static function remainingQuotaMessage(string $platform, int|string $userId): ?string
    {
        $limit = (int) config('services.music_finder_bot.free_requests_limit', 3);
        $windowHours = (int) config('services.music_finder_bot.free_requests_window_hours', 12);

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
            . "جستجو با لینک یوتیوب و اینستاگرام و تیک تاک\n"
            . "جستجو با ویدیو\n"
            . "جستجو با صدای آهنگ";
    }
}
