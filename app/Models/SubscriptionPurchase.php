<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * یه «سفارش» — یعنی دقیقاً چی، با چه قیمت و چه مشخصاتی.
 * سرنوشتش (پرداخت شد یا نه) از روی 
 * Payment ای که از طریق رابطه‌ی پلی‌مورفیک بهش وصله خونده می‌شه
 * نه از یه استاتوس جدا که ممکنه از هم عقب بیفتن.
 */
class SubscriptionPurchase extends Model
{
    protected $fillable = [
        'platform',
        'user_id',
        'plan_id',
        'plan_name_snapshot',
        'requests_per_window_snapshot',
        'window_hours_snapshot',
        'duration_months',
        'discount_percent',
        'price_toman',
    ];

    /** پلن اصلی‌ای که این سفارش ازش ساخته شده (ممکنه بعداً حذف شده باشه و خالی باشه). */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /** پرداخت(های) متصل به این سفارش، از طریق رابطه‌ی پلی‌مورفیک */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
