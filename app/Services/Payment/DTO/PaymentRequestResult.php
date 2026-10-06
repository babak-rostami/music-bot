<?php

namespace App\Services\Payment\DTO;

/**
 * Data Transfer Object
 * نتیجه درخواست پرداخت را با یک فرمت ثابت برمی‌گرداند
 * هر درگاهی که درخواست پرداخت می‌فرستی، نتیجه را در نهایت به این شکل به من بده.
 * سرویس پرداخت لازم نیست فرمت خروجی هر درگاه رو بشناسه این فرمت رو بهش میدیم
 */
class PaymentRequestResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $authority = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
