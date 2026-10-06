<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTO\PaymentRequestResult;
use App\Services\Payment\DTO\PaymentVerifyResult;

/**
 * قراردادی که هر درگاه پرداخت (الان مثلا زرین‌پال) باید پیاده کنه.
 *  هدف اینه که سیستم هیچ‌وقت مستقیم با یه درگاه خاص کار نکنه
 * اضافه کردن یه درگاه جدید یعنی فقط یه کلاس جدید که این اینترفیس رو پیاده می‌کنه
 */
interface PaymentGatewayInterface
{
    /** شناسه‌ی کوتاه درگاه (مثلاً 'zarinpal')
     * برای ذخیره توی ستون gateway 
     */
    public function key(): string;

    /** شروع یه تراکنش جدید با درگاه
     *  authority و لینک ریدایرکت می‌گیریم. */
    public function requestPayment(int $amountToman, string $description, string $callbackUrl, ?string $mobile = null): PaymentRequestResult;

    /** پرسیدن از درگاه که آیا این
     *  authority واقعاً پول گرفته یا نه
     *  مبلغ رو هم می‌فرستیم که درگاه خودش چک کنه. */
    public function verifyPayment(string $authority, int $amountToman): PaymentVerifyResult;

    /**
     * ساخت دوباره‌ی لینک درگاه از روی authority موجود (بدون درخواست جدید) —
     * برای وقتی کاربر دوباره روی لینک قبلی کلیک کرده.
     */
    public function getRedirectUrl(string $authority): string;
}
