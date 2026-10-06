<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\ZarinpalGateway;
use InvalidArgumentException;

/**
 * درگاه پرداخت مناسب را بر اساس کلید دریافت شده ایجاد می‌کند.
 *
 * اگر درگاه مشخصی ارسال نشود، درگاه پیش فرض پروژه استفاده می‌شود.
 *
 * برای اضافه کردن درگاه جدید:
 * ۱. یک کلاس بسازید که PaymentGatewayInterface را پیاده‌سازی کند.
 * ۲. درگاه جدید را به match اضافه کنید.
 *
 * بعد از این تغییر، PaymentService و کنترلرها نیازی به تغییر ندارند.
 */
class PaymentGatewayFactory
{
    public static function make(?string $key = null): PaymentGatewayInterface
    {
        $key = $key ?? config('services.payment.default_gateway', 'zarinpal');

        return match ($key) {
            'zarinpal' => app(ZarinpalGateway::class),
            default    => throw new InvalidArgumentException("درگاه پرداخت ناشناخته: {$key}"),
        };
    }
}
