<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPurchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * پاک‌سازی دوره‌ای داده‌های قدیمی مربوط به پرداخت و اشتراک.
 *
 * این دستور با ReconcilePendingPayments وظیفه متفاوتی دارد:
 * ReconcilePendingPayments تلاش می‌کند پرداخت‌های Pending را از درگاه
 * مجدداً بررسی و در صورت موفقیت تأیید کند؛ اما این دستور مسئول پاک‌سازی
 * مواردی است که دیگر قابل استفاده یا پیگیری نیستند.
 *
 * عملیات این دستور:
 *
 * 1. منقضی کردن Paymentهای Created که برای مدت مشخصی بدون استفاده مانده‌اند.
 * 2. منقضی کردن Paymentهای Pending که بیشتر از زمان تعیین‌شده منتظر مانده‌اند.
 * 3. منقضی کردن Subscription هایی که تاریخ اعتبارشان تمام شده است.
 * 4. حذف فیزیکی Payment های Failed و Expired قدیمی و Purchase مربوط به آن‌ها.
 *
 * پرداخت های Paid حذف نمی‌شوند تا سابقه مالی و اطلاعات تراکنش‌ها حفظ شود.
 */
class CleanupStalePayments extends Command
{
    protected $signature = 'payments:cleanup';
    protected $description = 'علامت‌گذاری سفارش‌های راکد به‌عنوان منقضی + حذف فیزیکی پرداخت‌های ناموفق/منقضیِ قدیمی';

    /**
     * داده‌های منقضی و قدیمی پرداخت و اشتراک را پاک‌سازی می‌کند.
     *
     * مدت‌زمان‌های مربوط به انقضای پرداخت و مدت نگهداری داده‌های قدیمی
     * از تنظیمات پروژه خوانده می‌شوند تا بدون تغییر کد قابل تنظیم باشند.
     *
     * حذف پرداخت و Purchase مرتبط داخل Transaction انجام می‌شود تا
     * در صورت بروز خطا، حذف یکی بدون حذف دیگری باقی نماند.
     */
    public function handle(): int
    {
        $createdExpiryHours = (int) config('services.payment.created_expiry_hours', 24);
        $pendingExpiryHours  = (int) config('services.payment.pending_expiry_hours', 24);
        $retentionDays       = (int) config('services.payment.cleanup_retention_days', 90);

        // ۱. created هایی که کاربر هیچ‌وقت حتی لینک رو نزد
        $expiredCreated = Payment::where('status', PaymentStatus::Created->value)
            ->where('created_at', '<', now()->subHours($createdExpiryHours))
            ->update(['status' => PaymentStatus::Expired->value]);

        // ۲. pending هایی که ReconcilePendingPayments هم دیگه امیدی بهشون نداره
        $expiredPending = Payment::where('status', PaymentStatus::Pending->value)
            ->where('updated_at', '<', now()->subHours($pendingExpiryHours))
            ->update(['status' => PaymentStatus::Expired->value]);

        // ۳. اشتراک‌های منقضی‌شده — چرخه‌ی زندگی سرویسه، جدا از خود تراکنش‌ها
        $expiredSubscriptions = Subscription::where('status', SubscriptionStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        // ۴. حذف فیزیکی پرداخت‌های ناموفق/منقضیِ خیلی قدیمی — که جدول برای همیشه سنگین نشه.
        // توجه: فقط failed/expired حذف می‌شن؛ paid هیچ‌وقت حذف نمی‌شه (سابقه‌ی مالی).
        $deleted = 0;
        Payment::whereIn('status', [PaymentStatus::Failed->value, PaymentStatus::Expired->value])
            ->where('updated_at', '<', now()->subDays($retentionDays))
            ->chunkById(200, function ($payments) use (&$deleted) {
                foreach ($payments as $payment) {
                    DB::transaction(function () use ($payment) {
                        // چون رابطه پلی‌مورفیکه (نه FK واقعی)، باید خودمون purchase مرتبط رو هم دستی پاک کنیم
                        if ($payment->payable_type === SubscriptionPurchase::class) {
                            SubscriptionPurchase::where('id', $payment->payable_id)->delete();
                        }
                        $payment->delete();
                    });
                    $deleted++;
                }
            });

        $this->info(
            "پاکسازی: {$expiredCreated} سفارش راکد و {$expiredPending} پرداخت ناتمام منقضی شدن، "
                . "{$expiredSubscriptions} اشتراک منقضی شد، {$deleted} پرداخت/سفارش قدیمی حذف فیزیکی شدن."
        );

        return self::SUCCESS;
    }
}
