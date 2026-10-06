<?php

namespace App\Enums;

/**
 * وضعیت یک اشتراک فعال‌شده. جدا از PaymentStatus هست چون این دو تا state
 * machine دو مفهوم متفاوتن: یکی سرنوشت یه تراکنش مالیه، یکی سرنوشت یه
 * سرویسیه که کاربر داره ازش استفاده می‌کنه — ممکنه پرداخت خیلی وقت پیش paid
 * شده باشه ولی اشتراک همین الان expired بشه.
 */
enum SubscriptionStatus: string
{
    case Active   = 'active';
    case Expired  = 'expired';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Active   => 'فعال',
            self::Expired  => 'منقضی‌شده',
            self::Canceled => 'لغوشده',
        };
    }
}
