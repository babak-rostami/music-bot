<?php

namespace App\Bot\Shared;

use Illuminate\Support\Facades\Cache;

/**
 * لیست دستوراتی که توی منوی «/» تلگرام/بله نشون داده می‌شن، و متد ثبتشون.
 *
 * چرا با کد، نه BotFather؟ چون این‌جوری هر وقت خواستی یه دستور کم/زیاد کنی،
 * فقط همین فایل رو ویرایش می‌کنی — نیازی به رفتن توی تنظیمات دستی بات نیست.
 *
 * برای اضافه/کم کردن یه دستور: فقط یکی از دو متد پایین رو ویرایش کن.
 */
class BotCommandRegistrar
{
    /**
     * دستوراتی که همه‌ی کاربرا (ادمین هم جزوشونه) باید ببینن.
     *
     * ⚠️ فعلاً این‌ها فقط برای ادمین ثبت می‌شن (نه برای همه)، چون هنوز داریم تست می‌کنیم.
     * جایی که این کلاس صدا زده می‌شه (StartCommand) یه بخش کامنت‌شده داره که
     * بعد از اتمام تست، بازش می‌کنی تا همین لیست برای همه‌ی کاربرا هم فعال بشه.
     */
    public static function publicCommands(): array
    {
        return [
            ['command' => 'start', 'description' => 'شروع ربات'],
            ['command' => 'help', 'description' => 'راهنمای استفاده'],
            ['command' => 'status', 'description' => 'وضعیت درخواست‌های امروز'],
            // 👇 فعلاً غیرفعال — هروقت آماده بود، این خط رو از کامنت در بیار:
            // ['command' => 'upgrade', 'description' => 'جستجوی پیشرفته'],
        ];
    }

    /**
     * دستورات اضافه‌ی مخصوص ادمین (قبلاً پیاده‌سازی شدن، اینجا فقط ثبت می‌شن).
     */
    public static function adminExtraCommands(): array
    {
        return [
            ['command' => 'new_song', 'description' => 'ایجاد آهنگ جدید'],
            ['command' => 'send_message', 'description' => 'ارسال پیام به کاربر'],
            ['command' => 'broadcast', 'description' => 'ارسال پیام به کاربران'],
        ];
    }

    /**
     * لیست کامل منوی ادمین: عمومی + اختصاصی ادمین.
     */
    public static function adminFullMenu(): array
    {
        return [...self::publicCommands(), ...self::adminExtraCommands()];
    }

    /**
     * ثبت واقعی دستورات — با کش، که هر بار /start زده نشه دوباره صدا زده بشه.
     * هر ۷ روز یه‌بار خودکار دوباره ثبت می‌کنه (برای اینکه اگه لیست رو توی کد عوض کردی،
     * حداکثر تا یه هفته بعد خودش به‌روز بشه، بدون نیاز به کار دستی).
     */
    public static function ensureRegistered(BotApiInterface $bot, bool $isAdmin, int|string $chatId): void
    {
        // $cacheKey = $isAdmin
        //     ? "bot_commands_registered:{$bot->platform()}:admin:{$chatId}"
        //     : "bot_commands_registered:{$bot->platform()}:public";

        // if (Cache::has($cacheKey)) {
        //     return;
        // }

        $commands = $isAdmin ? self::adminFullMenu() : self::publicCommands();
        $scopeChatId = $isAdmin ? $chatId : null;

        $bot->setMyCommands($commands, $scopeChatId);

        // Cache::put($cacheKey, true, now()->addDays(7));
    }
}
