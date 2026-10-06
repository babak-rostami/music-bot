<?php

namespace App\Services\Payment\Fulfillers;

use App\Bot\Shared\BotFactory;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPurchase;
use App\Services\Payment\Contracts\PayableFulfillerInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * اشتراک خریداری‌شده را بعد از پرداخت موفق فعال می‌کند.
 *
 * PaymentService فقط مسئول تأیید پرداخت است و وارد جزئیات محصول نمی‌شود.
 * بعد از تأیید موفق، PayableFulfillerFactory بر اساس نوع خرید، این سرویس
 * را برای تحویل اشتراک انتخاب می‌کند.
 *
 * این سرویس مسئول:
 * - ساخت Subscription برای خرید
 * - محاسبه تاریخ شروع و پایان اشتراک
 * - جلوگیری از فعال‌سازی تکراری یک پرداخت
 * - اطلاع‌رسانی به کاربر بعد از فعال شدن اشتراک
 *
 * اگر در آینده محصول دیگری به سیستم پرداخت اضافه شود، منطق آن محصول
 * در Fulfiller مخصوص خودش قرار می‌گیرد و این کلاس فقط مسئول اشتراک خواهد بود.
 */
class SubscriptionFulfillmentService implements PayableFulfillerInterface
{
    /**
     * اشتراک مربوط به یک Payment موفق را فعال می‌کند.
     *
     * ابتدا بررسی می‌شود که برای این پرداخت قبلاً Subscription ساخته شده یا نه.
     * این بررسی باعث می‌شود اگر متد به هر دلیلی دوباره اجرا شد، اشتراک دوباره
     * ساخته یا پیام موفقیت دوباره ارسال نشود.
     *
     * سپس بررسی می‌شود که payable این پرداخت واقعاً از نوع SubscriptionPurchase باشد.
     * در غیر این صورت، عملیات متوقف می‌شود تا این Fulfiller روی نوع خرید اشتباه اجرا نشود.
     *
     * در ادامه تاریخ شروع و پایان اشتراک محاسبه شده و Subscription ساخته می‌شود.
     *
     * ساخت Subscription داخل یک تراکنش تودرتو انجام می‌شود تا در صورت رخ دادن
     * خطای Unique Constraint در پردازش‌های هم‌زمان، خطا فقط در همین بخش مدیریت شود
     * و تراکنش اصلی Payment به‌صورت ناخواسته خراب نشود.
     *
     * بعد از ساخت موفق Subscription، فعال شدن اشتراک به کاربر اطلاع داده می‌شود.
     */
    public function fulfill(Payment $payment): void
    {
        if (Subscription::where('payment_id', $payment->id)->exists()) {
            return; // قبلاً فعال و اطلاع‌رسانی شده — idempotent
        }

        $purchase = $payment->payable;

        if (!$purchase instanceof SubscriptionPurchase) {
            Log::error('SubscriptionFulfillmentService called with non-subscription payable', ['payment_id' => $payment->id]);
            return;
        }

        [$startsAt, $expiresAt] = $this->calculateWindow($payment->platform, $payment->user_id, $purchase->duration_months);

        /*
        * این Transaction برای این نیست که چند عملیات را با هم انجام دهیم؛
        * در اینجا فقط Subscription ساخته می‌شود.
        *
        * دلیل اصلی استفاده از Transaction این است که fulfill() داخل Transaction
        * اصلی PaymentService اجرا می‌شود. این Transaction داخلی در لاراول
        * به‌صورت یک Savepoint داخل Transaction بیرونی مدیریت می‌شود.
        *
        * چرا Savepoint لازم است؟
        * ممکن است دو پردازش هم‌زمان بخواهند برای یک پرداخت یک اشتراک بسازند
        * به کمک Unique Constraint دیتابیس، فقط یکی از آن‌ها موفق می‌شود
        * و پردازش دوم با خطای Unique مواجه می‌شود.
        *
        * اگر این خطا مستقیماً داخل Transaction اصلی رخ دهد، مخصوصاً در PostgreSQL،
        * Transaction اصلی وارد وضعیت خطا می‌شود و دیگر نمی‌توانیم به‌سادگی
        * عملیات دیگری در آن انجام دهیم.
        *
        * با داشتن Transaction داخلی، خطا در محدوده همین بخش مدیریت می‌شود
        * و می‌توانیم در catch آن را بررسی کنیم. اگر خطا از نوع Unique باشد،
        * یعنی احتمالاً یک پردازش دیگر زودتر اشتراک را ساخته و این حالت
        * برای ما قابل قبول است؛ در غیر این صورت خطا را ارسال می‌کنیم.
        *
        * بنابراین این Transaction در واقع یک محدوده امن برای ساخت Subscription
        * در شرایط پردازش هم‌زمان ایجاد می‌کند، نه یک Transaction مستقل از
        * Transaction پرداخت.
        */
        try {
            /*
            * این تراکنش داخل تراکنش اصلی PaymentService اجرا می‌شود.
            *
            * هدف از تراکنش داخلی این است که خطای ایجاد اشتراک مخصوصاً
            * خطای Unique Constraint در پردازش‌های هم‌زمان، فقط همین عملیات را
            * تحت تأثیر قرار دهد و اجازه دهد خطا در همین بخش مدیریت شود.
            *
            * لااراول برای تراکنش‌های تودرتو در صورت پشتیبانی دیتابیس از Savepoint
            * استفاده می‌کند؛ بنابراین این یک تراکنش کاملاً مستقل از تراکنش بیرونی نیست.
            */
            $subscription = DB::transaction(function () use ($payment, $purchase, $startsAt, $expiresAt) {
                return Subscription::create([
                    'platform'                     => $payment->platform,
                    'user_id'                      => $payment->user_id,
                    'payment_id'                   => $payment->id,
                    'plan_id'                      => $purchase->plan_id,
                    'plan_name_snapshot'           => $purchase->plan_name_snapshot,
                    'requests_per_window_snapshot' => $purchase->requests_per_window_snapshot,
                    'window_hours_snapshot'        => $purchase->window_hours_snapshot,
                    'duration_months'              => $purchase->duration_months,
                    'starts_at'                    => $startsAt,
                    'expires_at'                   => $expiresAt,
                    'status'                       => SubscriptionStatus::Active,
                ]);
            });
            /*
            * اگر پردازش دیگری هم‌زمان همین Subscription را ساخته باشد،
            * دیتابیس به دلیل Unique Constraint خطا می‌دهد.
            *
            * این حالت برای ما خطای واقعی نیست؛ یعنی اشتراک موردنظر
            * قبلاً توسط پردازش دیگری ساخته شده است. بنابراین عملیات را
            * بدون ساخت مجدد و بدون ارسال دوباره پیام به کاربر تمام می‌کنیم.
            *
            * سایر خطاهای دیتابیس باید به لایه بالاتر منتقل شوند تا مخفی نشوند.
            */
        } catch (QueryException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'unique')) {
                throw $e;
            }
            return; // یه پردازش موازی دیگه همین الان ساختش — بی‌خطره
        }

        $this->notifyUser($payment, $subscription);
    }

    /**
     * بازه زمانی اشتراک جدید را محاسبه می‌کند.
     *
     * اگر کاربر همین حالا یک اشتراک فعال و منقضی‌نشده داشته باشد،
     * اشتراک جدید از تاریخ انقضای اشتراک فعلی شروع می‌شود.
     *
     * در غیر این صورت، اشتراک جدید از زمان فعلی شروع خواهد شد.
     *
     * به این ترتیب اگر کاربر قبل از تمام شدن اشتراک فعلی دوباره خرید کند،
     * مدت اشتراک جدید روی اشتراک قبلی قرار می‌گیرد و روزهای باقی‌مانده
     * اشتراک فعلی از بین نمی‌رود.
     *
     * در نهایت تاریخ شروع و تاریخ انقضای اشتراک جدید برگردانده می‌شود.
     */
    private function calculateWindow(string $platform, int|string $userId, int $durationMonths): array
    {
        $currentExpiry = Subscription::where('platform', $platform)
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::Active->value)
            ->where('expires_at', '>', now())
            ->max('expires_at');

        $startsAt = $currentExpiry ? Carbon::parse($currentExpiry) : now();

        return [$startsAt, $startsAt->copy()->addMonths($durationMonths)];
    }

    /**
     * بعد از فعال شدن موفق اشتراک، نتیجه خرید را به کاربر اطلاع می‌دهد.
     *
     * پلتفرم کاربر از روی Payment مشخص می‌شود تا پیام از طریق ربات مربوطه ارسال شود.
     *
     * پیام شامل اطلاعات مهم اشتراک مانند نام پلن، مدت اشتراک،
     * تعداد درخواست‌های مجاز و تاریخ انقضا است.
     *
     * خطا در ارسال پیام نباید باعث شکست پرداخت یا فعال‌سازی اشتراک شود؛
     * بنابراین خطای ارسال فقط در لاگ ثبت می‌شود.
     */
    private function notifyUser(Payment $payment, Subscription $subscription): void
    {
        try {
            $bot = BotFactory::make($payment->platform);
            $bot->sendMessage(
                $payment->user_id,
                "✅ پرداخت شما موفق بود!\n\n"
                    . "🎉 پلن {$subscription->plan_name_snapshot} ({$subscription->duration_months} ماهه) فعال شد.\n"
                    . "📈 از الان {$subscription->requests_per_window_snapshot} درخواست جستجوی پیشرفته در هر {$subscription->window_hours_snapshot} ساعت داری.\n"
                    . "📅 اعتبار تا: " . jdate($subscription->expires_at)->format('Y/m/d')
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify user after payment', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
        }
    }
}
