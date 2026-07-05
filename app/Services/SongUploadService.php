<?php

namespace App\Services;

use App\Jobs\ProcessMusicDownloadJob;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Models\Song;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SongUploadService
{
    /**
     * پردازش دانلود برای تلگرام
     */
    public function processAndSend($callback, $data, $chatId, $telegramService): void
    {
        $messageId = $callback['message']['message_id'];

        $telegramService->editMessageText(
            $chatId,
            $messageId,
            '✅ درخواست شما دریافت شد. در حال آماده‌سازی فایل... 🎶',
            []
        );

        [$quality, $song] = $this->extractSongData($data);

        $fileIdColumn = "telegram_file_id_{$quality}";
        $cachedFileId = $song->{$fileIdColumn};

        if ($cachedFileId) {
            $telegramService->sendAudioByFileId(
                $chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($song->lyrics) {
                $telegramService->sendMessage($chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
            }

            $telegramService->deleteMessage($chatId, $messageId);
            return;
        }

        ProcessMusicDownloadJob::dispatch($song->id, $quality, $chatId, $messageId)
            ->onQueue('download-music');
    }

    /**
     * پردازش دانلود برای بله (با قفل)
     * 
     * @param array $callback callback_query از بله
     * @param string $data داده دکمه
     * @param int|string $chatId
     * @param BaleService $baleService
     * @param string|null $lockKey کلید قفل (اختیاری، برای جلوگیری از تکرار)
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

        // ─────────────────────────────────────────────────
        // بررسی Cache بله
        // ─────────────────────────────────────────────────
        $fileIdColumn = "bale_file_id_{$quality}";
        $cachedFileId = $song->{$fileIdColumn};

        if ($cachedFileId) {
            // 🚀 Cache Hit: ارسال فوری
            $baleService->sendAudioByFileId(
                $chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($song->lyrics) {
                $baleService->sendMessage($chatId, "🎤 متن آهنگ:\n\n" . $song->lyrics);
            }

            $baleService->deleteMessage($chatId, $messageId);

            // آزاد کردن قفل
            if ($lockKey) {
                Cache::forget($lockKey);
            }
            return;
        }

        // ─────────────────────────────────────────────────
        // Cache Miss: ارسال به صف
        // ─────────────────────────────────────────────────
        ProcessBaleMusicDownloadJob::dispatch(
            $song->id,
            $quality,
            $chatId,
            $messageId,
            $lockKey // ارسال قفل به job تا بعد از اتمام آزاد کنه
        )->onQueue('download-music');
    }

    private function extractSongData(string $data): array
    {
        $parts = explode('_', $data);

        return [
            $parts[2],
            Song::findOrFail($parts[3]),
        ];
    }
}
