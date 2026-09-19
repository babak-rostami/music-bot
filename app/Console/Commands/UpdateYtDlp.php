<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateYtDlp extends Command
{
    protected $signature = 'ytdlp:update';
    protected $description = 'به‌روزرسانی خودکار yt-dlp (برای سازگاری با تغییرات یوتیوب/ساندکلاد)';

    public function handle(): int
    {
        $this->info('در حال بررسی و به‌روزرسانی yt-dlp...');

        exec('yt-dlp -U 2>&1', $output, $exitCode);

        foreach ($output as $line) {
            $this->line($line);
        }

        if ($exitCode !== 0) {
            $this->error('به‌روزرسانی yt-dlp با خطا مواجه شد.');
            return self::FAILURE;
        }

        $this->info('✅ yt-dlp به‌روز است.');
        return self::SUCCESS;
    }
}
