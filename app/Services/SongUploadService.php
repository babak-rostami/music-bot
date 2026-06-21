<?php

namespace App\Services;

use App\Jobs\ProcessMusicDownloadJob;
use App\Models\Song;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SongUploadService
{
    public function processAndSend($callback, $data, $chatId, $telegramService): void
    {
        $messageId = $callback['message']['message_id'];

        // ۱. ویرایش پیام برای دادن بازخورد به کاربر
        $telegramService->editMessageText(
            $chatId,
            $messageId,
            '✅ درخواست شما دریافت شد. در حال آماده‌سازی فایل... 🎶',
            [] // حذف دکمه‌ها
        );

        [$quality, $song] = $this->extractSongData($data);

        // 🆕 بررسی Cache: آیا قبلاً این آهنگ با این کیفیت توی کانال Cache هست؟
        $fileIdColumn = "telegram_file_id_{$quality}";
        $cachedFileId = $song->{$fileIdColumn};

        if ($cachedFileId) {
            // 🚀 Cache Hit: فقط فوروارد، بدون دانلود، بدون ffmpeg، بدون آپلود
            $telegramService->sendAudioByFileId(
                $chatId,
                $cachedFileId,
                $song->title,
                $song->artist_name
            );

            if ($song->lyrics) {
                $telegramService->sendMessage(
                    $chatId,
                    "🎤 متن آهنگ:\n\n" . $song->lyrics
                );
            }

            $telegramService->deleteMessage($chatId, $messageId);

            return;
        }

        // 🐢 Cache Miss: فقط dispatch کن، خودت فوری برگرد
        ProcessMusicDownloadJob::dispatch(
            $song->id,
            $quality,
            $chatId,
            $messageId,
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
