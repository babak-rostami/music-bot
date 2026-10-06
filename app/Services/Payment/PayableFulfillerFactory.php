<?php

namespace App\Services\Payment;

use App\Models\SubscriptionPurchase;
use App\Services\Payment\Contracts\PayableFulfillerInterface;
use App\Services\Payment\Fulfillers\SubscriptionFulfillmentService;

/**
 * سرویس مناسب برای تحویل محصول خریداری‌شده را بر اساس نوع payable انتخاب می‌کند.
 *
 * هر نوع payable باید یک Fulfiller مخصوص خودش داشته باشد که
 * PayableFulfillerInterface را پیاده‌سازی کرده باشد.
 *
 * اگر نوع payable شناخته‌شده نباشد، null برمی‌گرداند؛
 * یعنی برای آن نوع، عملیات تحویل خاصی انجام نمی‌شود.
 *
 * برای اضافه کردن نوع جدید:
 * ۱. مدل یا payable جدید را ایجاد کنید.
 * ۲. یک Fulfiller بسازید که PayableFulfillerInterface را پیاده‌سازی کند.
 * ۳. نوع جدید را به match اضافه کنید.
 *
 * با این کار، PaymentService نیازی به تغییر ندارد.
 * 
 * «پول با موفقیت پرداخت شد.» چی خریداری شده تا آن را تحویل بدهیم؟.
 * Payment -> payable -> SubscriptionPurchase -> SubscriptionFulfillmentService -> فعال کردن اشتراک کاربر
 * Payment -> ProductPurchase -> ProductFulfillmentService -> فعال کردن/تحویل محصول
 */
class PayableFulfillerFactory
{
    public static function make(string $payableType): ?PayableFulfillerInterface
    {
        return match ($payableType) {
            SubscriptionPurchase::class => app(SubscriptionFulfillmentService::class),
            default => null,
        };
    }
}
