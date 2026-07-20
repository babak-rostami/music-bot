<?php

namespace App\Services;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Telegram\Services\TelegramService;
use App\Models\SongRequest;
use Illuminate\Support\Facades\Log;

class AdminNotifier
{
    public function __construct(protected TelegramService $telegram, protected BaleService $bale) {}

    /**
     * نوتیفیکیشن به کانال ادمین وقتی درخواست آهنگ جدید ثبت میشه
     *
     * اگه کانال cache تنظیم نشده باشه، silently skip می‌کنه.
     */
    public function notifyNewSongRequest(SongRequest $request, $platform): void
    {
        if ($platform == 'telegram') {
            $this->notifyNewSongRequestTelegram($request);
        } elseif ($platform == 'bale') {
            $this->notifyNewSongRequestBale($request);
        }
    }

    private function notifyNewSongRequestTelegram($request)
    {
        $channelId = config('telegram.cache_channel_id');
        if (!$channelId) {
            return;
        }
        try {
            $message = $this->buildSongRequestMessage($request);
            $this->telegram->sendMessage($channelId, $message, null, 'HTML');
        } catch (\Throwable $e) {
            Log::warning('Failed to send admin notification for song request', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
    private function notifyNewSongRequestBale($request)
    {
        $channelId = config('telegram.bale_cache_channel_id');
        if (!$channelId) {
            return;
        }
        try {
            $message = $this->buildSongRequestMessage($request);
            $this->bale->sendMessage($channelId, $message, null, 'HTML');
        } catch (\Throwable $e) {
            Log::warning('Failed to send admin notification for song request', [
                'request_id' => $request->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * ساخت متن نوتیفیکیشن با HTML formatting
     */
    private function buildSongRequestMessage(SongRequest $request): string
    {
        // ── اطلاعات کاربر ──
        $userLine = e($request->full_name ?: 'بدون نام');

        if ($request->username) {
            $userLine .= " (@" . e($request->username) . ")";
        }

        $userLine .= " · <code>{$request->user_id}</code>";

        // ── آمار ──
        $totalPending = SongRequest::pending()->count();
        $todayPending = SongRequest::pending()
            ->whereDate('created_at', today())
            ->count();
        $totalAll = SongRequest::count();

        // ── ساخت پیام ──
        $message = "🔔 <b>درخواست جدید برای آپلود آهنگ</b>\n\n"
            . "👤 <b>کاربر:</b> {$userLine}\n"
            . "🔍 <b>جستجو:</b> «" . e($request->query) . "»\n\n"
            . "📊 <b>آمار درخواست‌ها:</b>\n"
            . "├ ⏳ در انتظار: <b>{$totalPending}</b>";

        if ($todayPending > 0) {
            $message .= " <i>({$todayPending} تای امروز)</i>";
        }

        $message .= "\n└ 📦 کل از ابتدا: <b>{$totalAll}</b>\n\n"
            . "🕐 <i>" . $request->created_at->format('Y-m-d H:i') . "</i>";

        return $message;
    }
}
