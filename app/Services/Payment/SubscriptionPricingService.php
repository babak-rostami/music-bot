<?php

namespace App\Services\Payment;

use App\Enums\SubscriptionDuration;
use App\Models\SubscriptionPlan;

/**
 * تنها جایی که فرمول قیمت‌گذاری (قیمت پایه ضربدر ماه، منهای درصد تخفیف * مدت) تعریف شده.
 * چه موقع نمایش دکمه‌ها، چه موقع ساخت واقعی پرداخت همه از همین یه متد صدا می‌زنن
 * که هیچ‌وقت دو جای مختلف کد، قیمت رو با فرمول‌های متفاوت حساب نکنن.
 */
class SubscriptionPricingService
{
    /** قیمت نهایی یه پلن برای یه مدت مشخص، با تخفیف اعمال‌شده. */
    public function finalPrice(SubscriptionPlan $plan, SubscriptionDuration $duration): int
    {
        $baseTotal = $plan->monthly_price_toman * $duration->months();
        $discount  = intdiv($baseTotal * $duration->discountPercent(), 100);

        return $baseTotal - $discount;
    }
}
