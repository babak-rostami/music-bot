<?php

namespace App\Services\Payment;

use App\Bot\Telegram\Services\TelegramService;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionDuration;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPurchase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * مدیریت اصلی فرآیند پرداخت.
 *
 * این کلاس جریان اصلی پرداخت را از زمان ایجاد خرید تا تأیید نهایی
 * مدیریت می‌کند:
 *
 * ۱. ساخت خرید و Payment
 * ۲. ارسال Payment به درگاه
 * ۳. دریافت Authority و انتقال کاربر به درگاه
 * ۴. تأیید پرداخت بعد از بازگشت کاربر از درگاه
 * ۵. تحویل(ثبت) محصول خریداری‌شده بعد از پرداخت موفق
 *
 * این کلاس عمداً به جزئیات درگاه یا محصول وابسته نیست.
 *
 * برای درگاه، از PaymentGatewayFactory استفاده می‌کند تا درگاه مناسب انتخاب شود
 *
 * برای تحویل(ثبت) محصول نیز از PayableFulfillerFactory استفاده می‌کند
 * تا بر اساس نوع محصول، Fulfiller مناسب انتخاب شود.
 *
 * بنابراین اگر در آینده درگاه یا نوع محصول جدیدی اضافه شود،
 * منطق اصلی این کلاس نیازی به تغییر نخواهد داشت.
 */
class PaymentService
{
    /**
     * دیتای خرید و پرداخت مربوط به آن را در دیتابیس ایجاد می‌کند.
     *
     * ابتدا قیمت نهایی محاسبه می‌شود، سپس اطلاعات خرید و پرداخت
     * در یک تراکنش دیتابیس ایجاد می‌شوند.
     *
     * مثلا اطلاعاتی مثل نام پلن، تعداد درخواست‌ها، مدت و قیمت به‌صورت اسنپ شات
     * در خرید ذخیره می‌شوند تا تغییرات بعدی پلن روی خرید قبلی تأثیری نداشته باشد
     *
     * در این مرحله هنوز پرداختی در درگاه انجام نشده است؛
     * فقط سطر پرداخت برای شروع فرآیند پرداخت ساخته می‌شود.
     */
    public function createPurchaseAndPayment(string $platform, int|string $userId, SubscriptionPlan $plan, SubscriptionDuration $duration): Payment
    {
        $price = app(SubscriptionPricingService::class)->finalPrice($plan, $duration);

        return DB::transaction(function () use ($platform, $userId, $plan, $duration, $price) {
            $purchase = SubscriptionPurchase::create([
                'platform'                     => $platform,
                'user_id'                      => $userId,
                'plan_id'                      => $plan->id,
                'plan_name_snapshot'           => $plan->name,
                'requests_per_window_snapshot' => $plan->requests_per_window,
                'window_hours_snapshot'        => $plan->window_hours,
                'duration_months'              => $duration->months(),
                'discount_percent'             => $duration->discountPercent(),
                'price_toman'                  => $price,
            ]);

            return Payment::create([
                'token'        => Str::random(48),
                'platform'     => $platform,
                'user_id'      => $userId,
                'payable_type' => SubscriptionPurchase::class,
                'payable_id'   => $purchase->id,
                'amount_toman' => $price,
                'gateway'      => 'zarinpal',
                'status'       => PaymentStatus::Created,
            ]);
        });
    }

    /**
     * Payment را به درگاه ارسال کرده و آدرس صفحه پرداخت را برمی‌گرداند.
     *
     * اگر Payment قبلاً به درگاه ارسال شده و هنوز Pending باشد،
     * از Authority قبلی استفاده می‌شود و درخواست جدیدی به درگاه ارسال نمی‌شود.
     * این کار از ایجاد چند تراکنش برای یک Payment جلوگیری می‌کند.
     *
     * اگر Payment هنوز به درگاه ارسال نشده باشد، یک درخواست جدید ایجاد می‌شود،
     * Authority دریافت‌شده در Payment ذخیره می‌شود و آدرس صفحه پرداخت برگردانده می‌شود.
     *
     * درگاه واقعی توسط PaymentGatewayFactory انتخاب می‌شود؛
     * بنابراین این متد به پیاده‌سازی یک درگاه خاص وابسته نیست.
     */
    public function redirectToGateway(Payment $payment): string
    {
        $gateway = PaymentGatewayFactory::make($payment->gateway);

        if ($payment->status === PaymentStatus::Pending && $payment->authority) {
            return $gateway->getRedirectUrl($payment->authority);
        }

        $callbackUrl = route('payment.callback', ['token' => $payment->token]);
        $description = 'پرداخت ربات — شناسه ' . $payment->token;

        $result = $gateway->requestPayment($payment->amount_toman, $description, $callbackUrl);

        if (!$result->success) {
            throw new \RuntimeException($result->errorMessage ?? 'خطا در اتصال به درگاه پرداخت');
        }

        $payment->update([
            'status'    => PaymentStatus::Pending,
            'authority' => $result->authority,
        ]);

        return $result->redirectUrl;
    }

    /**
     * پرداخت را در درگاه تأیید کرده و در صورت موفقیت، محصول را تحویل می‌دهد.
     *
     * این متد معمولاً بعد از بازگشت کاربر از درگاه اجرا می‌شود.
     *
     * ابتدا بررسی می‌شود که Payment قبلاً Paid نشده باشد.
     * سپس با استفاده از Lock از تأیید هم‌زمان یک Payment توسط چند درخواست
     * جلوگیری می‌شود؛ چون ممکن است callback درگاه چند بار دریافت شود.
     *
     * بعد از تأیید موفق درگاه:
     * - وضعیت Payment به Paid تغییر می‌کند.
     * - Ref ID و اطلاعات پرداخت ذخیره می‌شود.
     * - زمان پرداخت ثبت می‌شود.
     * - محصول خریداری‌شده توسط Fulfiller مربوط به آن تحویل داده می‌شود.
     *
     * این متد درباره نوع محصول اطلاعی ندارد.
     * نوع محصول از payable_type مشخص شده و PayableFulfillerFactory
     * Fulfiller مناسب را انتخاب می‌کند.
     *
     * به این ترتیب، اضافه شدن محصول جدید نیازی به تغییر این متد ندارد.
     */

    public function verify(Payment $payment): bool
    {
        if ($payment->status === PaymentStatus::Paid) {
            return true;
        }

        if (!$payment->authority) {
            return false;
        }

        /**
         * برای جلوگیری از پردازش هم‌زمان یک Payment قفل ایجاد می‌کنیم.
         *
         * ممکن است Callback درگاه به هر دلیلی بیش از یک بار یا تقریباً هم‌زمان
         * برای یک پرداخت دریافت شود.
         * اگر چند درخواست هم‌زمان وارد فرآیند Verify شوند،
         * ممکن است یک پرداخت چند بار تأیید یا محصول خریداری‌شده چند بار تحویل داده شود.
         *
         * کلید لاک بر اساس شناسه پرداخت ساخته می‌شود؛ بنابراین فقط درخواست‌هایی
         * که می‌خواهند همان پرداخت را Verify کنند با یکدیگر تداخل دارند و
         * پرداخت‌های دیگر می‌توانند هم‌زمان پردازش شوند.
         *
         * عدد 30 مدت اعتبار خود لاک است،
         * اگر فرآیند دارنده لاک با خطا متوقف شود،
         * لاک بعد از این مدت به‌صورت خودکار منقضی می‌شود تا برای همیشه باقی نماند.
         */
        $lock = Cache::lock("payment-verify:{$payment->id}", 30);

        /**
         * «اگر قفل دست یک درخواست دیگر است، حداکثر ۱۵ ثانیه صبر کن تا قفل آزاد شود.
         * اگر آزاد شد، قفل را بگیر و این کد را اجرا کن.»
         *
         * توجه کنید که 15 ثانیه مدت اعتبار لاک نیست؛ فقط حداکثر زمانی است
         * که این درخواست برای گرفتن لاک منتظر می‌ماند.
         */
        return $lock->block(15, function () use ($payment) {
            $payment->refresh();

            if ($payment->status === PaymentStatus::Paid) {
                return true;
            }

            $gateway = PaymentGatewayFactory::make($payment->gateway);
            $result = $gateway->verifyPayment($payment->authority, $payment->amount_toman);

            if (!$result->success) {
                $payment->update(['status' => PaymentStatus::Failed]);
                return false;
            }

            DB::transaction(function () use ($payment, $result) {
                $payment->update([
                    'status'   => PaymentStatus::Paid,
                    'ref_id'   => $result->refId,
                    'card_pan' => $result->cardPan,
                    'paid_at'  => now(),
                ]);

                // تحویل محصول — کاملاً پلی‌مورفیکه؛ PaymentService نمی‌دونه و نباید بدونه
                // این Payment مال اشتراکه یا یه محصول دیگه
                PayableFulfillerFactory::make($payment->payable_type)?->fulfill($payment->fresh());
            });

            $this->notifyAdmin($payment);

            return true;
        });
    }

    /**
     * یک اعلان عمومی از پرداخت موفق برای ادمین ارسال می‌کند.
     *
     * این اعلان فقط اطلاعات کلی پرداخت مانند پلتفرم، کاربر، نوع خرید،
     * مبلغ و Ref ID را شامل می‌شود.
     *
     * این متد نباید وارد جزئیات محصول یا منطق تحویل آن شود؛
     * چون این مسئولیت بر عهده Fulfiller مربوط به همان محصول است.
     *
     * خطا در ارسال اعلان نباید باعث شکست پرداخت شود؛
     * بنابراین خطای ارسال پیام فقط در لاگ ثبت می‌شود.
     */
    private function notifyAdmin(Payment $payment): void
    {
        $channelId = config('telegram.notify_channel_id');
        if (!$channelId) {
            return;
        }

        try {
            app(TelegramService::class)->sendMessage(
                $channelId,
                "💰 پرداخت جدید\n\n"
                    . "🤖 پلتفرم: " . ($payment->platform === 'bale' ? 'بله' : 'تلگرام') . "\n"
                    . "👤 chat_id: {$payment->user_id}\n"
                    . "📦 نوع: " . class_basename($payment->payable_type) . "\n"
                    . "💵 مبلغ: " . number_format($payment->amount_toman) . " تومان\n"
                    . "🔗 ref_id: {$payment->ref_id}"
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admin about payment', ['error' => $e->getMessage()]);
        }
    }
}
