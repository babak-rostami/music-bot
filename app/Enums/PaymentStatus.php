<?php

namespace App\Enums;

/**
 * پنج حالتی که یک پرداخت توی عمرش می‌تونه داشته باشه. از created تا paid مسیر
 * عادیه؛ از paid دیگه هیچ برگشتی وجود نداره — این عمدیه تا دوبار شارژ کردن
 * یه تراکنش از نظر منطقی اصلاً امکان‌پذیر نباشه.
 */
enum PaymentStatus: string
{
    case Created = 'created'; // لینک ساخته شده، هنوز کلیک نشده
    case Pending = 'pending'; // کلیک شده، وارد درگاه شده، هنوز تأیید نشده
    case Paid    = 'paid';    // تأیید نهایی موفق
    case Failed  = 'failed';  // ناموفق/لغوشده
    case Expired = 'expired'; // خیلی وقته معلق مونده و دیگه معتبر نیست

    /**
     * متن فارسی هر وضعیت، برای نمایش توی پنل ادمین. یه‌جا نگهش داشتیم که اگه
     * خواستیم متنی رو عوض کنیم، لازم نباشه دنبال فایل های پراکنده بگردیم.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'ساخته‌شده (کلیک نشده)',
            self::Pending => 'در انتظار تکمیل',
            self::Paid    => 'پرداخت‌شده',
            self::Failed  => 'ناموفق',
            self::Expired => 'منقضی‌شده',
        };
    }

    /**
     * کلاس‌های تیلویند برای بج رنگی هر وضعیت توی جدول‌های ادمین — به همون دلیلی
     * که لیبل جداست، رنگ‌ها هم یه‌جا متمرکز موندن.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Created => 'bg-gray-100 text-gray-600',
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Paid    => 'bg-green-100 text-green-800',
            self::Failed  => 'bg-red-100 text-red-800',
            self::Expired => 'bg-gray-200 text-gray-500',
        };
    }
}
