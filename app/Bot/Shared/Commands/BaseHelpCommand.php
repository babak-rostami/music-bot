<?php

namespace App\Bot\Shared\Commands;

use App\Bot\Shared\BotApiInterface;

abstract class BaseHelpCommand
{
    private const HELP_TEXT = <<<'TEXT'
🎵 راهنمای جستجوی آهنگ

🔎 جستجو با متن
- متن آهنگ رو کامل در یک پیام بفرست
- متن رو در چند پیام جداگانه ارسال نکن
- به پیام‌های قبلی ریپلای نکن
ربات پیام ریپلای‌شده رو تشخیص نمی‌ده.

🎙 جستجو با صدا
- اگر آهنگ در حال پخشه،
فقط صدای آهنگ رو با کیفیت خوب ضبط کن.
- صدای خودت رو ضبط و ارسال نکن.
- اگر متن آهنگ رو می‌دونی،
به‌جای ضبط صدا، متن آهنگ رو ارسال کن.

🎬 جستجو با ویدیو
- ویدیو رو با کیفیت مناسب ارسال کن.
- حجم ویدیو بیشتر از ۱۵ تا ۲۰ مگابایت نباشه.

🔗 جستجو با لینک
- لینک پست موردنظر از YouTube، Instagram یا TikTok رو بدون متن اضافه بفرست.

🚫 موارد غیرقابل استفاده
- عکس ارسال نکن.
- GIF ارسال نکن.
- استیکر ارسال نکن.
TEXT;

    public function canHandle(string $text): bool
    {
        return trim($text) === '/help';
    }

    public function handle(array $message): void
    {
        $this->bot()->sendMessage($message['chat']['id'], self::HELP_TEXT);
    }

    abstract protected function bot(): BotApiInterface;
}
