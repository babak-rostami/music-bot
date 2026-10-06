<?php

namespace App\Services\MusicFinder;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * مدیریت و نگهداری نمونه‌های MusicFinderService برای بات‌های آهنگ‌یاب کانفیگ‌شده.
 *
 * برای هر بات خارجی فقط یک نمونه MusicFinderService ایجاد می‌شود و این
 * نمونه‌ها در طول عمر Process فعلی در حافظه نگهداری می‌شوند.
 *
 * این Registry دو وظیفه اصلی دارد:
 *
 * 1. ساخت و نگهداری MusicFinderServiceهای مربوط به بات‌های کانفیگ‌شده.
 * 2. انتخاب بات مناسب برای درخواست‌های جدید به‌صورت Round Robin.
 *
 * Round Robin یعنی درخواست‌ها به‌ترتیب بین بات‌های موجود پخش می‌شوند.
 * برای مثال، اگر سه بات داشته باشیم:
 *
 *     bot_1 → bot_2 → bot_3 → bot_1 → bot_2 → ...
 *
 * این کار باعث می‌شود درخواست‌ها فقط روی یک بات متمرکز نشوند و فشار و
 * محدودیت‌های تلگرام بین چند بات تقسیم شود.
 *
 * انتخاب Round Robin بر اساس یک شمارنده مشترک در Cache انجام می‌شود؛
 * بنابراین با هر درخواست، نوبت به بات بعدی منتقل می‌شود.
 *
 * متد get() نیز برای زمانی است که یک کلید مشخص در اختیار داریم.
 * اگر آن کلید معتبر باشد، همان MusicFinderService برگردانده می‌شود؛
 * در غیر این صورت برای جلوگیری از خطا، یکی از بات‌های موجود به‌صورت
 * Round Robin انتخاب می‌شود.
 */
class MusicFinderBotRegistry
{
    private static ?array $instances = null;

    /**
     * تمام MusicFinderServiceهای مربوط به بات‌های فعال و کانفیگ‌شده را برمی‌گرداند.
     *
     * نمونه‌ها فقط یک‌بار ساخته می‌شوند و در static property کلاس نگهداری می‌شوند
     * تا در فراخوانی‌های بعدی دوباره برای هر بات سرویس جدید ساخته نشود.
     */
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

    /**
     * MusicFinderService مربوط به کلید مشخص را برمی‌گرداند.
     *
     * اگر کلید معتبر نباشد، برای جلوگیری از Fail شدن درخواست، یکی از بات‌های
     * موجود به‌صورت Round Robin انتخاب می‌شود.
     */
    public static function get(string $key): MusicFinderService
    {
        $all = self::all();

        // اگه کلید نامعتبر/قدیمی بود (مثلاً بات حذف شده)، به‌جای کرش، یکی رو round-robin بده
        return $all[$key] ?? self::next();
    }

    /**
     * بات بعدی را با استفاده از الگوریتم Round Robin انتخاب می‌کند.
     *
     * Round Robin یعنی انتخاب بات‌ها به‌صورت چرخشی و نوبتی انجام شود.
     * برای مثال با سه بات:
     *
     *     درخواست 1 → bot_1
     *     درخواست 2 → bot_2
     *     درخواست 3 → bot_3
     *     درخواست 4 → bot_1
     *     درخواست 5 → bot_2
     *
     * شمارنده انتخاب در Cache نگهداری می‌شود تا نوبت انتخاب بین درخواست‌ها
     * مشترک باشد.
     */
    public static function next(): MusicFinderService
    {
        $all = self::all();
        $keys = array_keys($all);
        $index = Cache::increment('music_finder_round_robin') % count($keys);
        return $all[$keys[$index]];
    }
}
