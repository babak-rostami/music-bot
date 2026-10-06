<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * یه تراکنش مالی خالص — و فقط همین.
 * این مدل عمداً هیچ ایده‌ای از «کاربر چی خریده» نداره؛
 * به‌جاش از طریق (پلی‌مورفیک) به هر مدلی که نماینده‌ی اون خریده وصل می‌شه
 *  (الان: SubscriptionPurchase، فردا: هر محصول دیگه‌ای).
 * این جداسازی باعث میشه برای فروش یه محصول کاملاً جدید
 * لازم نباشه این کلاس یا مایگریشنش دست بخوره.
 */
class Payment extends Model
{
    protected $fillable = [
        'token',
        'platform',
        'user_id',
        'payable_type',
        'payable_id',
        'amount_toman',
        'gateway',
        'authority',
        'ref_id',
        'card_pan',
        'status',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'status'  => PaymentStatus::class,
        'meta'    => 'array',
        'paid_at' => 'datetime',
    ];

    /** چیزی که این پرداخت بابتش انجام شده
     * مثلا — SubscriptionPurchase
     * یا هر مدل نماینده خرید آینده. */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** اشتراکی که (در صورت موفقیت پرداخت) از روی این تراکنش فعال شده
     * فقط برای پرداخت‌های نوع اشتراک معنا داره. */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /** میان‌بر خوانا به‌جای مقایسه‌ی دستی — هرجا می‌خوایم بدونیم پرداخت انجام شده یا نه. */
    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /** لینک برگشت به همون بات (تلگرام یا بله) که کاربر ازش اومده
     * برای دکمه‌ی «برگشت به ربات» توی صفحات نتیجه. */
    public function getBotDeepLinkAttribute(): string
    {
        return $this->platform === 'bale'
            ? 'https://ble.ir/' . config('telegram.bale_bot_username')
            : 'https://t.me/' . config('telegram.bot_username');
    }
}
