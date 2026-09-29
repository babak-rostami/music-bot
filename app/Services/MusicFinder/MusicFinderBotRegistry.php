<?php

namespace App\Services\MusicFinder;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * نگهدارنده‌ی یه نمونه MusicFinderService برای هر بات آهنگ‌یاب کانفیگ‌شده،
 * به‌علاوه‌ی انتخاب‌گر یکی‌درمیون (round-robin) بین‌شون.
 *
 * برای شروع یه تعامل جدید (متن/لینک/رسانه) از next() استفاده کن.
 * برای ادامه‌ی یه تعامل قبلی (کلیک روی دکمه‌ای که قبلاً نشون داده شده)
 * از get($key) با همون کلیدی که موقع نمایش دکمه‌ها ذخیره کردیم استفاده کن —
 * چون callback_data مال یه بات خاصه، نمی‌شه با بات دیگه‌ای کلیکش کرد.
 */
class MusicFinderBotRegistry
{
    private static ?array $instances = null;

    /** @return array<string, MusicFinderService> */
    public static function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }

        $bots = array_filter(config('services.music_finder_bots', []), fn($bot) => !empty($bot['username']));

        if (empty($bots)) {
            throw new RuntimeException('هیچ بات آهنگ‌یابی کانفیگ نشده (MUSIC_FINDER_BOT_USERNAME / MUSIC_FINDER_BOT_2_USERNAME رو چک کن).');
        }

        self::$instances = [];
        foreach ($bots as $bot) {
            self::$instances[$bot['key']] = new MusicFinderService($bot['key'], $bot['username']);
        }

        return self::$instances;
    }

    public static function get(string $key): MusicFinderService
    {
        $all = self::all();

        // اگه کلید نامعتبر/قدیمی بود (مثلاً بات حذف شده)، به‌جای کرش، یکی رو round-robin بده
        return $all[$key] ?? self::next();
    }

    public static function next(): MusicFinderService
    {
        $all = self::all();
        $keys = array_keys($all);
        $index = Cache::increment('music_finder_round_robin') % count($keys);
        return $all[$keys[$index]];
    }
}
