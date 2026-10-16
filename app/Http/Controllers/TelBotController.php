<?php

namespace App\Http\Controllers;

use App\Bot\NotificationService;
use App\Bot\Telegram\Services\TelegramActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelBotController extends Controller
{
    /**
     * مدت نگهداری شناسه‌ی هر Update در Cache، برای جلوگیری از پردازش دوباره‌اش.
     *
     * تلگرام اگه پاسخ وب‌هوک به‌موقع نرسه (یا connection قطع بشه)، همون Update
     * رو دوباره ارسال می‌کنه. بدون این محافظت، یه Update تکراری یعنی: دانلود و
     * ارسال دوباره‌ی همون آهنگ، مصرف دوباره‌ی سهمیه‌ی جستجوی پیشرفته، یا حتی
     * تلاش برای ساخت دوباره‌ی یه Payment — همه بی‌سر و صدا و بدون هیچ خطایی
     * توی لاگ.
     */
    private const PROCESSED_UPDATE_TTL_HOURS = 24;

    public function __construct(
        protected NotificationService $notifier,
        protected TelegramActionService $telegramActionService,
    ) {}

    public function index(Request $request)
    {
        if (!$this->hasValidSecretToken($request)) {
            abort(403);
        }

        if (!$this->shouldProcess($request)) {
            return response()->json(['status' => 'ok']);
        }

        try {
            if ($request->has('callback_query')) {
                $this->telegramActionService->handleCallback($request);
            } elseif ($request->has('message')) {
                $this->telegramActionService->handleMessage($request);
            }
        } catch (\Throwable $e) {
            $this->notifier->error('وبهوک تلگرام', $e->getMessage(), 'telegram');
            Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * اگه TELEGRAM_WEBHOOK_SECRET تنظیم شده باشه، باید هدر
     * X-Telegram-Bot-Api-Secret-Token دقیقاً همون مقدار رو داشته باشه؛ در غیر
     * این صورت درخواست با ۴۰۳ رد می‌شه. این همون مکانیزم رسمی تلگرام برای
     * اطمینان از اینه که درخواست واقعاً از سمت تلگرام اومده، نه یه فرستنده‌ی
     * دلخواه که مسیر /music-webhook رو حدس زده.
     *
     * تا وقتی این مقدار تنظیم نشده (پیش‌فرض)، این بررسی کاملاً غیرفعاله —
     * یعنی نصب‌هایی که هنوز موقع setWebhook پارامتر secret_token رو ثبت
     * نکردن، از کار نمی‌افتن.
     */
    private function hasValidSecretToken(Request $request): bool
    {
        $expected = config('telegram.webhook_secret');

        if (!$expected) {
            return true;
        }

        return hash_equals($expected, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'));
    }

    /**
     * جلوی پردازش دوباره‌ی یک Update رو می‌گیره.
     * Cache::add اتمیکه، پس حتی اگه دو درخواست با همین update_id دقیقاً
     * هم‌زمان برسن (یا وب‌هوک یه‌بار retry بشه)، فقط یکیشون پردازش می‌شه.
     */
    private function shouldProcess(Request $request): bool
    {
        $updateId = $request->input('update_id');

        if ($updateId === null) {
            return true;
        }

        return Cache::add(
            "telegram-webhook-update:{$updateId}",
            true,
            now()->addHours(self::PROCESSED_UPDATE_TTL_HOURS)
        );
    }
}
