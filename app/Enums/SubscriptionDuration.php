<?php

namespace App\Enums;

/**
 * مدت‌های قابل‌خرید (۱ و ۳ و ۶ ماهه) و تخفیف هرکدوم. این یه مفهوم عمودیه که
 * روی همه‌ی پلن‌ها یکسان اعمال می‌شه — برای همین جدا از SubscriptionPlan
 * نگهش داشتیم، نه این‌که توی خود جدول پلن‌ها چندبار تکرار بشه. برای اضافه
 * کردن یه مدت جدید (مثلاً ۱۲ ماهه) فقط یه case دیگه اینجا کافیه.
 */
enum SubscriptionDuration: int
{
    case OneMonth    = 1;
    case ThreeMonths = 3;
    case SixMonths   = 6;

    /** تعداد ماه، به‌صورت یه عدد ساده برای محاسبات (ضرب در قیمت پایه و مانند آن). */
    public function months(): int
    {
        return $this->value;
    }

    public function discountPercent(): int
    {
        return match ($this) {
            self::OneMonth    => 0,
            self::ThreeMonths => 10,
            self::SixMonths   => 20,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OneMonth    => '۱ ماهه',
            self::ThreeMonths => '۳ ماهه',
            self::SixMonths   => '۶ ماهه',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::OneMonth    => '🗓',
            self::ThreeMonths => '🔥',
            self::SixMonths   => '🚀',
        };
    }

    /** یه خط کوتاه مثل «۱۰٪ تخفیف» برای کنار اسم مدت؛ اگه تخفیفی نباشه null می‌ده. */
    public function badgeNote(): ?string
    {
        return $this->discountPercent() > 0 ? "{$this->discountPercent()}٪ تخفیف" : null;
    }
}
