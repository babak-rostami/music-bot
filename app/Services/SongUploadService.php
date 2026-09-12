<?php

namespace App\Services;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Jobs\ProcessMusicDownloadJob;
use App\Models\Song;
use Illuminate\Support\Facades\Cache;

class SongUploadService
{
    public function __construct(
        protected NotificationService $notifier,
        protected BotUserService $botUserService
    ) {}

    // ═══════════════════════════════════════════════════
    //  تلگرام
    // ═══════════════════════════════════════════════════

    /**
     * بهترین لینک آهنگ رو پیدا کن و ارسال کن برای تلگرام
     */
    public function findBestLinkAndSendForTelegram($callback, $songId, $chatId, $telegramService): void
    {
        $messageId = $callback['message']['message_id'];
        $telegramService->editMessageText(
            $chatId,
            $messageId,
            '⏳ در حال آماده‌سازی فایل... 🎶',
            []
        );

        ProcessMusicDownloadJob::dispatch($songId, $chatId, $messageId)
            ->onQueue('download-music');
    }

    // ═══════════════════════════════════════════════════
    //  بله
    // ═══════════════════════════════════════════════════

    /**
     * بهترین لینک آهنگ رو پیدا کن و ارسال کن برای تلگرام
     */
    public function findBestLinkAndSendForBale($callback, $songId, $chatId, $baleService): void
    {
        $callbackId = $callback['id'];
        $messageId  = $callback['message']['message_id'];

        // 🔒 هر کاربر در هر لحظه فقط یک دانلود
        // (اگر می‌خوای فقط جلوی تکرارِ همان آهنگ گرفته بشه، ":{$songId}" رو به کلید برگردون)
        $lockKey = "bale:download_lock:{$chatId}";
        $lockTtl = 300; // هماهنگ با timeout جاب و امکان چند بار تلاش مجدد

        // ✅ اتمیک: بین وب‌هوک‌های همزمان فقط یکی از این چک رد می‌شه
        if (! Cache::add($lockKey, true, now()->addSeconds($lockTtl))) {
            $baleService->answerCallbackQuery($callbackId, '⏳ یک فایل در حال آماده‌سازیه، لطفاً صبر کن...');
            return;
        }

        try {
            $baleService->answerCallbackQuery($callbackId);
            $baleService->editMessageText($chatId, $messageId, '⏳ در حال آماده‌سازی فایل... 🎶', []);

            ProcessBaleMusicDownloadJob::dispatch($songId, $chatId, $messageId, $lockKey)
                ->onQueue('download-music');
        } catch (\Throwable $e) {
            Cache::forget($lockKey); // اگه وسط کار چیزی شکست، کاربر قفل نمونه
            report($e);
        }
    }
}
