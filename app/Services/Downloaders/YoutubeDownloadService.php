<?php

namespace App\Services\Downloaders;

use App\Bot\NotificationService;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YoutubeDownloadService
{
    public function __construct(private readonly NotificationService $notifier) {}

    public function download(string $url, string $tempDir): ?string
    {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputTemplate = $tempDir . '/' . Str::uuid() . '.%(ext)s';

        $command = sprintf(
            'yt-dlp -x --audio-format mp3 --audio-quality 0 --no-playlist %s -o %s %s 2>&1',
            YtDlpCommandBuilder::commonArgs(),
            escapeshellarg($outputTemplate),
            escapeshellarg($url)
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            Log::warning('yt-dlp (YouTube) download failed', [
                'url'    => $url,
                'output' => $lines,
            ]);

            $this->alertIfBotCheck($lines);

            return null;
        }

        return $this->findDownloadedMp3($tempDir);
    }

    protected function findDownloadedMp3(string $tempDir): ?string
    {
        $files = glob("{$tempDir}/*.mp3");
        return $files[0] ?? null;
    }

    /**
     * وقتی یوتیوب با «Sign in to confirm you're not a bot» رد کنه، یه هشدار به کانال ادمین می‌فرسته.
     * برای جلوگیری از اسپم، حداکثر هر ۶ ساعت یه‌بار.
     */
    private function alertIfBotCheck(array $output): void
    {
        if (! str_contains(implode("\n", $output), 'Sign in to confirm')) {
            return;
        }

        if (Cache::has('ytdlp_bot_check_alerted')) {
            return;
        }

        Cache::put('ytdlp_bot_check_alerted', true, now()->addHours(6));

        $hasCookies = config('services.ytdlp.cookies_path') && file_exists(config('services.ytdlp.cookies_path'));

        $message = $hasCookies
            ? '⚠️ یوتیوب دوباره دانلود رو رد می‌کنه (Sign in to confirm you\'re not a bot). احتمالاً کوکی منقضی شده، یه کوکی تازه export و جایگزین کن.'
            : '⚠️ یوتیوب دانلودها رو رد می‌کنه (Sign in to confirm you\'re not a bot) و هنوز کوکی تنظیم نشده. برای رفع دائمی‌ترش یه فایل کوکی اضافه کن.';

        $this->notifier->sendToAdmin($message, ActivityLog::LEVEL_WARNING);
    }
}
