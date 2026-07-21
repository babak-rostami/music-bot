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
     * پردازش دانلود برای تلگرام
     */
    public function processAndSend($callback, $data, $chatId, $telegramService): void
    {
        $messageId = $callback['message']['message_id'];

        [$quality, $song] = $this->extractSongData($data);

        $userId        = (int) $chatId; // در چت خصوصی chatId == userId
        $fileIdColumn  = "telegram_file_id_{$quality}";
        $cachedFileId  = $song->{$fileIdColumn};

        $telegramService->editMessageText(
            $chatId,
            $messageId,
            '⏳ در حال آماده‌سازی فایل... 🎶',
            []
        );

        // ─────────────────────────────────────────────────
        // Cache Hit → ارسال فوری
        // ─────────────────────────────────────────────────
        if ($cachedFileId) {
            $response = $telegramService->sendAudioByFileId(
                $chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                $song->increment('downloads_count');

                if ($song->lyrics) {
                    $telegramService->sendMessage($chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
                }

                $telegramService->deleteMessage($chatId, $messageId);

                // ✅ نوتیفیکیشن ارسال موفق + ریست خطا
                $this->notifier->songSent($song, $chatId, $quality, 'telegram', $userId);
                $this->botUserService->recordSuccessfulSend($userId, 'telegram');

                return;
            }

            // ❌ ارسال ناموفق
            $error = $response->json('description', 'خطای ناشناخته');
            $this->notifier->songSendFailed($song, $chatId, $quality, $error, 'telegram', $userId);
            $this->botUserService->recordFailedSend($userId, 'telegram');

            // اگر خطا مربوط به file_id نبود (یعنی کاربر بلاک کرده) → ادامه نده
            if (BotUserService::isBlockedError($response)) {
                return;
            }

            // file_id نامعتبر → پاک کن و از مسیر job برو
            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // Cache Miss → ارسال به صف
        // ─────────────────────────────────────────────────
        ProcessMusicDownloadJob::dispatch($song->id, $chatId, $messageId)
            ->onQueue('download-music');
    }

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
     * پردازش دانلود برای بله (با قفل)
     */
    public function processAndSendForBale($callback, $data, $chatId, $baleService): void
    {
        $this->processAndSendForBaleWithLock($callback, $data, $chatId, $baleService, null);
    }

    /**
     * پردازش دانلود برای بله با قفل ضد تکرار
     */
    public function processAndSendForBaleWithLock($callback, $data, $chatId, $baleService, ?string $lockKey): void
    {
        $messageId = $callback['message']['message_id'];

        [$quality, $song] = $this->extractSongData($data);

        $userId       = (int) $chatId;
        $fileIdColumn = "bale_file_id_{$quality}";
        $cachedFileId = $song->{$fileIdColumn};

        // ─────────────────────────────────────────────────
        // Cache Hit → ارسال فوری
        // ─────────────────────────────────────────────────
        if ($cachedFileId) {
            $response = $baleService->sendAudioByFileId(
                $chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($response->successful()) {
                if ($song->lyrics) {
                    $baleService->sendMessage($chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
                }

                $baleService->deleteMessage($chatId, $messageId);

                // آزاد کردن قفل
                if ($lockKey) {
                    Cache::forget($lockKey);
                }

                // ✅ نوتیفیکیشن ارسال موفق + ریست خطا
                $this->notifier->songSent($song, $chatId, $quality, 'bale', $userId);
                $this->botUserService->recordSuccessfulSend($userId, 'bale');

                return;
            }

            // ❌ ارسال ناموفق
            $error = $response->json('description', 'خطای ناشناخته');
            $this->notifier->songSendFailed($song, $chatId, $quality, $error, 'bale', $userId);
            $this->botUserService->recordFailedSend($userId, 'bale');

            if ($lockKey) {
                Cache::forget($lockKey);
            }

            // اگر کاربر بلاک کرده → ادامه نده
            if (BotUserService::isBlockedError($response)) {
                return;
            }

            // file_id نامعتبر → پاک کن و از مسیر job برو
            $song->update([$fileIdColumn => null]);
        }

        // ─────────────────────────────────────────────────
        // Cache Miss → ارسال به صف
        // ─────────────────────────────────────────────────
        ProcessBaleMusicDownloadJob::dispatch(
            $song->id,
            $chatId,
            $messageId,
            $lockKey,
        )->onQueue('download-music');
    }

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
        $lockTtl = 120; // باید بیشتر از طولانی‌ترین حالتِ ممکنِ اجرای جاب باشه

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

    // ═══════════════════════════════════════════════════
    //  Helper
    // ═══════════════════════════════════════════════════

    private function extractSongData(string $data): array
    {
        $parts = explode('_', $data);

        return [
            $parts[2],
            Song::findOrFail($parts[3]),
        ];
    }
}
