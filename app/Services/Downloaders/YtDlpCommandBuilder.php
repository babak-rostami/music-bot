<?php

namespace App\Services\Downloaders;

/**
 * آرگومان‌های مشترک yt-dlp برای کاهش شانس بلاک/چک‌ربات یوتیوب:
 * - انتخاب کلاینت‌هایی که کمتر با «Sign in to confirm you're not a bot» مواجه می‌شن
 * - کند کردن درخواست‌ها (رفتار انسانی‌تر)
 * - کوکی واقعی، فقط اگه فایلش موجود باشه (اختیاری)
 * - پلاگین PO Token، فقط اگه سرویس bgutil در کانفیگ فعال باشه
 */
class YtDlpCommandBuilder
{
    public static function commonArgs(): string
    {
        $args = [
            '--extractor-args ' . escapeshellarg('youtube:player_client=web,android,ios'),
            '--sleep-requests 1',
            '--sleep-interval 2',
            '--max-sleep-interval 6',
        ];

        $cookiesPath = config('services.ytdlp.cookies_path');
        if ($cookiesPath && file_exists($cookiesPath)) {
            $args[] = '--cookies ' . escapeshellarg($cookiesPath);
        }

        $potBaseUrl = config('services.ytdlp.pot_base_url');
        if ($potBaseUrl) {
            $args[] = '--plugin-dirs ' . escapeshellarg('/opt/yt-dlp-plugins');
            $args[] = '--extractor-args ' . escapeshellarg("youtubepot-bgutilhttp:base_url={$potBaseUrl}");
        }

        return implode(' ', $args);
    }
}
