<?php

namespace App\Services\MusicFinder;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

/**
 * نقطه اتصال برنامه لاراول به سشن مربوط به MadelineProto.
 *
 * سشن واقعی تلگرام در یک Process/Daemon جداگانه اجرا می‌شود.
 * این کلاس مسئول ایجاد لاگین یا سشن جدید نیست؛ بلکه همان سشن
 * موجود را از طریق MadelineProto در اختیار کد لاراول قرار می‌دهد.
 *
 * تنظیمات API از کانفیگ خوانده می‌شوند و سشن از مسیر مشخص‌شده
 * در storage استفاده می‌شود.
 */
class MadelineConnection
{
    public static function get(): API
    {
        $session = storage_path('app/private/madeline/session.madeline');

        $settings = new Settings;
        $settings->getAppInfo()->setApiId((int) config('services.madeline.api_id'));
        $settings->getAppInfo()->setApiHash(config('services.madeline.api_hash'));

        $madeline = new API($session, $settings);
        $madeline->start(); // اتصال به Session موجود را برقرار می‌کند لاگین جدیدی انجام نمی‌شود.

        return $madeline;
    }
}
