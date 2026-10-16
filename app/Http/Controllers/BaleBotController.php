<?php

namespace App\Http\Controllers;

use App\Bot\Bale\Services\BaleActionService;
use App\Bot\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BaleBotController extends Controller
{
    /**
     * مدت نگهداری شناسه‌ی هر Update در Cache، برای جلوگیری از پردازش دوباره‌اش.
     *
     * اگه بله (مثل تلگرام) به‌خاطر دیر رسیدن پاسخ وب‌هوک همون Update رو دوباره
     * بفرسته، بدون این محافظت یعنی: دانلود و ارسال دوباره‌ی همون آهنگ، مصرف
     * دوباره‌ی سهمیه‌ی جستجوی پیشرفته، یا تلاش برای ساخت دوباره‌ی یه Payment —
     * همه بی‌سر و صدا و بدون هیچ خطایی توی لاگ.
     */
    private const PROCESSED_UPDATE_TTL_HOURS = 24;

    public function __construct(
        private NotificationService $notifier,
        private BaleActionService $baleActionService,
    ) {}

    public function index(Request $request)
    {
        if (!$this->shouldProcess($request)) {
            return response()->json(['status' => 'ok']);
        }

        try {
            // اگه روی دکمه ای کلیک کرده
            if ($request->has('callback_query')) {
                $this->baleActionService->handleCallback($request);
            } elseif ($request->has('message')) {
                // اگه پیام فرستاده - متن، لینک، ویدیو و صوت و ...
                $this->baleActionService->handleMessage($request);
            }
        } catch (\Throwable $e) {
            $this->notifier->error('وبهوک بله', $e->getMessage(), 'bale');
            Log::error('Bale webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
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
            "bale-webhook-update:{$updateId}",
            true,
            now()->addHours(self::PROCESSED_UPDATE_TTL_HOURS)
        );
    }
}
