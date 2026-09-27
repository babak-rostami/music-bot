<?php

namespace App\Services;

use App\Bot\Shared\BotApiInterface;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Jobs\ProcessMusicDownloadJob;

class SongUploadService
{
    public function findBestLinkAndSend(array $callback, int $songId, int|string $chatId, BotApiInterface $bot): void
    {
        $messageId = $callback['message']['message_id'];

        $bot->editMessageText($chatId, $messageId, '⏳ در حال آماده‌سازی فایل... 🎶', []);

        match ($bot->platform()) {
            'bale'  => ProcessBaleMusicDownloadJob::dispatch($songId, $chatId, $messageId, null)->onQueue('download-music'),
            default => ProcessMusicDownloadJob::dispatch($songId, $chatId, $messageId)->onQueue('download-music'),
        };
    }
}
