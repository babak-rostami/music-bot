<?php

namespace App\Models;

use App\Enums\SubscriptionDuration;
use App\Services\Payment\SubscriptionPricingService;
use Illuminate\Database\Eloquent\Model;

/**
 * ردیف‌های این جدول، کاتالوگ فروشیه که ادمین از پنل مدیریت می‌کنه (می‌سازه،
 * قیمت پایه‌ش رو عوض می‌کنه، فعال/غیرفعالش می‌کنه). این مدل هیچ‌وقت مستقیم
 * به تراکنش مالی وصل نمی‌شه — رابطه‌ش با خرید واقعی از طریق SubscriptionPurchase
 * (اسنپ‌شاتی که لحظه‌ی خرید ساخته می‌شه) برقرار می‌شه، که یعنی عوض کردن قیمت
 * یه پلن، خریدهای قبلی رو دستکاری نمی‌کنه.
 */
class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'emoji',
        'requests_per_window',
        'window_hours',
        'monthly_price_toman',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** سفارش‌هایی که از روی این پلن ساخته شدن — چه پرداخت شده باشه چه نشده باشه. */
    public function purchases()
    {
        return $this->hasMany(SubscriptionPurchase::class, 'plan_id');
    }

    /** اشتراک‌های واقعاً فعال‌شده از این پلن — معیار دقیق «این پلن چندبار فروش رفته». */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function getDisplayTitleAttribute(): string
    {
        return trim(($this->emoji ? $this->emoji . ' ' : '') . $this->name);
    }

    /** قیمت نهایی این پلن برای یه مدت مشخص */
    public function priceForDuration(SubscriptionDuration $duration): int
    {
        return app(SubscriptionPricingService::class)->finalPrice($this, $duration);
    }
}
