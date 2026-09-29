<?php

namespace App\Bot\Shared\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * تلاش خودکار مجدد فقط برای خطاهای واقعیِ اتصال (قطعی/ریست شدن کانکشن،
 * تایم‌اوت شبکه) — نه برای خطاهای منطقی بات (مثل بلاک بودن کاربر)، چون
 * اون‌ها هیچ‌وقت با تلاش دوباره درست نمی‌شن و فقط وقت تلف می‌کنن.
 */
trait HasRetryableHttp
{
    protected function http(): PendingRequest
    {
        return Http::retry(3, 2000, function ($exception, $request) {
            return $exception instanceof ConnectionException;
        }, throw: false);
    }
}
