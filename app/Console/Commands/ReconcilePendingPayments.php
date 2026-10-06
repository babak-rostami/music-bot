<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Console\Command;

/**
 * پرداخت‌های Pending را در پس‌زمینه مجدداً بررسی می‌کند.
 *
 * ممکن است کاربر بعد از پرداخت از درگاه به سایت برنگردد؛
 * مثلاً صفحه را ببندد یا Callback به هر دلیلی به برنامه نرسد.
 *
 * این دستور پرداخت‌های Pending مربوط به ۲۴ ساعت اخیر را پیدا می‌کند
 * و با استفاده از PaymentService وضعیت آن‌ها را مستقیماً از درگاه بررسی می‌کند.
 *
 * اگر پرداخت موفق باشد، PaymentService آن را تأیید کرده و محصول خریداری‌شده
 * را نیز فعال می‌کند.
 *
 * این دستور فقط مسئول Verify کردن پرداخت‌ها است و پرداخت‌ها را Expire
 * یا حذف نمی‌کند. پاک‌سازی پرداخت‌های قدیمی بر عهده CleanupStalePayments است.
 */
class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile';
    protected $description = 'تلاش فعال برای تأیید پرداخت‌های pending که کاربر برنگشته — فقط verify می‌کنه، چیزی رو expire/حذف نمی‌کنه';

    /**
     * پرداخت‌های Pending را از درگاه استعلام می‌کند.
     *
     * فقط پرداخت‌هایی بررسی می‌شوند که:
     * - وضعیت آن‌ها Pending باشد.
     * - Authority داشته باشند.
     * - حداقل ۵ دقیقه از آخرین تغییرشان گذشته باشد.
     * - بیشتر از ۲۴ ساعت از آخرین تغییرشان نگذشته باشد.
     *
     * برای هر پرداخت PaymentService مسئول Verify و در صورت موفقیت
     * فعال‌سازی محصول خریداری‌شده است.
     */
    public function handle(PaymentService $paymentService): int
    {
        $pending = Payment::where('status', PaymentStatus::Pending->value)
            ->whereNotNull('authority')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->where('updated_at', '>=', now()->subHours(24))
            ->get();

        $paid = 0;
        $failed = 0;

        foreach ($pending as $payment) {
            $paymentService->verify($payment) ? $paid++ : $failed++;
        }

        $this->info("تطبیق پرداخت‌ها: {$paid} تأیید شد، {$failed} ناموفق موند.");

        return self::SUCCESS;
    }
}
