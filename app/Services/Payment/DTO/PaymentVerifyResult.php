<?php

namespace App\Services\Payment\DTO;

/**
 * نتیجه استاندارد تأیید پرداخت.
 *
 * درگاه‌های مختلف ممکن است پاسخ‌های متفاوتی داشته باشند،
 * اما PaymentService برای همه آن‌ها یک نتیجه با این ساختار دریافت می‌کند.
 *
 * اگر پرداخت موفق باشد، اطلاعاتی مثل کد پیگیری و چند رقم آخر کارت
 * در این DTO قرار می‌گیرد.
 *
 * اگر درگاه اعلام کند که پرداخت قبلاً تأیید شده است،
 * alreadyVerified برابر 1 می‌شود و این حالت هم موفقیت در نظر گرفته می‌شود.
 */
class PaymentVerifyResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $refId = null,
        public readonly ?string $cardPan = null,
        public readonly bool $alreadyVerified = false,
        public readonly ?int $rawCode = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
