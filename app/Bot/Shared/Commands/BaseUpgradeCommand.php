<?php

namespace App\Bot\Shared\Commands;

use App\Bot\Shared\BotApiInterface;
use App\Models\SubscriptionPlan;
use App\Services\MusicFinder\MusicFinderAccess;

/**
 * نقطه ورود قابلیت ارتقای اشتراک از داخل بات.
 *
 * این کلاس مسئول نمایش پلن‌های فعال و ارسال دکمه انتخاب پلن به کاربر است.
 *
 * این مرحله فقط برای نمایش و انتخاب اولیه پلن است و هیچ Payment ی ایجاد نمی‌کند.
 * ساخت Payment بعد از آن انجام می‌شود که کاربر پلن و مدت اشتراک را انتخاب کرده باشد.
 *
 * این کلاس به‌صورت abstract طراحی شده تا بات‌های مختلف بتوانند
 * با پیاده‌سازی platform() و bot() از همین منطق مشترک استفاده کنند.
 */
abstract class BaseUpgradeCommand
{
    /**
     * بررسی می‌کند که متن دریافتی دستور /upgrade باشد.
     */
    public function canHandle(string $text): bool
    {
        return trim($text) === '/upgrade';
    }

    /**
     * پلن‌های فعال اشتراک را به کاربر نمایش می‌دهد.
     *
     * ابتدا دسترسی کاربر به قابلیت ارتقا بررسی می‌شود.
     * سپس معرفی قابلیت ارسال شده و پلن‌های فعال از دیتابیس دریافت می‌شوند.
     *
     * برای هر پلن یک دکمه انتخاب ارسال می‌شود.
     * در این مرحله هنوز Payment ساخته نمی‌شود؛ ساخت Payment بعد از
     * انتخاب پلن و مدت اشتراک انجام خواهد شد.
     *
     * اگر هیچ پلن فعالی وجود نداشته باشد، پیام مناسبی به کاربر نمایش داده می‌شود.
     */
    public function handle(array $message): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $bot = $this->bot();

        if (!MusicFinderAccess::allowed($this->platform(), (int) $userId)) {
            return;
        }

        $bot->sendMessage(
            $chatId,
            "🚀 ارتقای حساب کاربری\n\n"
                . "جستجوی حرفه‌ای با لینک، ویدیو و صدا\n"
                . "با بهترین هوش مصنوعی آهنگ‌یاب\n\n"
                . "محدودیت ۱۲ ساعته رو افزایش بده"
        );

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            $bot->sendMessage($chatId, 'در حال حاضر پلنی برای ارتقا موجود نیست.');
            return;
        }

        foreach ($plans as $plan) {
            $text = "{$plan->display_title}\n\n"
                . "{$plan->requests_per_window} درخواست در هر {$plan->window_hours} ساعت\n"
                . "💰 " . number_format($plan->monthly_price_toman) . " تومان در ماه";

            $bot->sendMessage($chatId, $text, [[
                ['text' => '💎 فعال‌سازی 💎', 'callback_data' => "plan_select_{$plan->id}"],
            ]]);
        }
    }

    abstract protected function platform(): string;
    abstract protected function bot(): BotApiInterface;
}
