<?php

namespace App\Services;

use App\Models\Song;

class MusicService
{
    public function __construct(
        private NotificationService $notifier
    ) {}

    /**
     * $title = اسم آهنگ
     * $artist_name = اسم خواننده
     * $sourceUrl = آدرس صفحه
     */
    public function songExist($title, $artist_name): bool
    {
        // ۱. چک کردن تکراری بودن
        $isSongExist = Song::where('title', $title)
            ->where('artist_name', $artist_name)->exists();

        if ($isSongExist) {
            return true;
        }

        // ۲. اگر نبود
        return false;
    }
}
