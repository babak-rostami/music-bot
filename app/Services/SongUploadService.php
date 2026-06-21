<?php

namespace App\Services;

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
        $downloadUrl = $this->getDownloadUrl($song, $quality);
        $fileName = $this->generateFileName($song);
        $tempDirectory = 'temp/' . Str::uuid();
        Storage::disk('local')->makeDirectory($tempDirectory);

        try {
            // ۲. دانلود و پردازش آهنگ
            $originalFile = $this->downloadSong($downloadUrl, $tempDirectory);
            $cleanFile = $this->removeMetadata($originalFile, $tempDirectory, $fileName);

            // ۳. ارسال فایل صوتی به کاربر
            $telegramService->sendAudio(
                $chatId,
                $cleanFile,
                $fileName,
                $song->title,
                $song->artist_name
            );

            if ($song->lyrics) {
                $telegramService->sendMessage(
                    $chatId,
                    "🎤 متن آهنگ:\n\n" . $song->lyrics
                );
            }

            // <<< ۴. حذف پیام "در حال آماده‌سازی..." پس از ارسال موفق >>>
            $telegramService->deleteMessage($chatId, $messageId);
        } finally {
            // ۵. همیشه دایرکتوری موقت را پاک کن
            Storage::disk('local')->deleteDirectory($tempDirectory);
        }
    }


    private function extractSongData(string $data): array
    {
        $parts = explode('_', $data);

        return [
            $parts[2],
            Song::findOrFail($parts[3]),
        ];
    }

    private function getDownloadUrl(Song $song, string $quality): string
    {
        return $quality === '128'
            ? $song->download_link_128
            : $song->download_link_320;
    }

    private function generateFileName(Song $song): string
    {
        return preg_replace(
            '/[\/\\\\:*?"<>|]/u',
            '',
            "{$song->title} - {$song->artist_name}"
        ) . '.mp3';
    }

    private function downloadSong(
        string $downloadUrl,
        string $tempDirectory
    ): string {

        $filePath = storage_path(
            "app/private/{$tempDirectory}/original.mp3"
        );

        $stream = fopen($filePath, 'w');

        Http::timeout(300)
            ->withOptions([
                'sink' => $stream,
            ])
            ->get($downloadUrl);

        fclose($stream);

        return $filePath;
    }

    private function removeMetadata(
        string $originalFile,
        string $tempDirectory,
        string $fileName
    ): string {

        $cleanFile = storage_path(
            "app/private/{$tempDirectory}/{$fileName}"
        );

        exec(
            "ffmpeg -i "
                . escapeshellarg($originalFile)
                . " -map 0:a -c copy -map_metadata -1 "
                . escapeshellarg($cleanFile)
                . " -y"
        );

        return $cleanFile;
    }
}
