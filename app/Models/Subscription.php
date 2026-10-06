<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * وضعیت واقعیِ سرویسی که کاربر خریده — تنها جدولی که برای فهمیدن «این کاربر
 * الان چه محدودیتی داره» بهش مراجعه می‌شه. فیلدهای اسنپ‌شاتش عمداً از
 * SubscriptionPurchase دوباره کپی شدن تا این کوئریِ پرتکرار (روی هر درخواست
 * جستجوی پیشرفته) نیازی به و جوین زدن به جدول‌های دیگه نداشته باشه.
 */
class Subscription extends Model
{
    protected $fillable = [
        'platform',
        'user_id',
        'payment_id',
        'plan_id',
        'plan_name_snapshot',
        'requests_per_window_snapshot',
        'window_hours_snapshot',
        'duration_months',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'status'     => SubscriptionStatus::class,
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** پرداختی که این اشتراک رو فعال کرده */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /** پلنی که این اشتراک ازش ساخته شده. */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /** چک می‌کنه هم وضعیتش اکتیو باشه هم هنوز منقضی نشده باشه
     * دو شرطی که همیشه باید با هم بررسی بشن. */
    public function isCurrentlyActive(): bool
    {
        return $this->status === SubscriptionStatus::Active && $this->expires_at->isFuture();
    }
}
