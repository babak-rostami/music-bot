<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupOldLogs extends Command
{
    protected $signature = 'logs:cleanup
        {--days=14 : فایل‌های لاگ روزانه‌ی قدیمی‌تر از این تعداد روز پاک می‌شن}
        {--madeline-max-mb=20 : اگه MadelineProto.log از این حجم بیشتر شد، فقط آخرین بخشش نگه داشته می‌شه}';

    protected $description = 'پاکسازی روزانه‌ی فایل‌های لاگ قدیمی تا سنگین نشن (laravel-*.log و MadelineProto.log)';

    public function handle(): int
    {
        $this->cleanupDatedLaravelLogs((int) $this->option('days'));
        $this->trimMadelineLog((int) $this->option('madeline-max-mb'));

        return self::SUCCESS;
    }

    /**
     * فایل‌های لاگ روزانه‌ی لاراول (وقتی LOG_CHANNEL=daily باشه: laravel-2026-09-28.log و ...)
     * که قدیمی‌تر از $days روز هستن رو پاک می‌کنه.
     *
     * برای اضافه کردن مسیر لاگ دیگه‌ای در آینده، فقط یه glob دیگه به این آرایه اضافه کن.
     */
    private function cleanupDatedLaravelLogs(int $days): void
    {
        $patterns = [
            storage_path('logs/laravel-*.log'),
        ];

        $cutoff = now()->subDays($days)->timestamp;
        $deleted = 0;

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        $this->info("لاگ‌های روزانه‌ی لاراول: {$deleted} فایل قدیمی‌تر از {$days} روز پاک شد.");
    }

    /**
     * MadelineProto.log مثل لاگ لاراول تاریخ‌دار نیست — یه فایل واحده که مدام بهش
     * اضافه می‌شه. برای همین به‌جای پاک‌کردن کامل، وقتی از سقف مشخصی بزرگ‌تر بشه
     * فقط نیمه‌ی دوم (جدیدترین لاگ‌ها) نگه داشته می‌شه.
     */
    private function trimMadelineLog(int $maxSizeMb): void
    {
        $path = base_path('madeline/MadelineProto.log');

        if (!file_exists($path)) {
            $this->info('MadelineProto.log هنوز وجود نداره، کاری لازم نیست.');
            return;
        }

        $maxBytes = $maxSizeMb * 1024 * 1024;
        $currentSize = filesize($path);

        if ($currentSize <= $maxBytes) {
            $this->info('حجم MadelineProto.log هنوز به سقف نرسیده (' . round($currentSize / 1024 / 1024, 1) . ' مگابایت).');
            return;
        }

        $keepBytes = intdiv($maxBytes, 2);

        $handle = fopen($path, 'r');
        fseek($handle, -$keepBytes, SEEK_END);
        $tail = fread($handle, $keepBytes);
        fclose($handle);

        file_put_contents($path, "...[لاگ‌های قدیمی‌تر پاک شدن]...\n" . $tail);

        $this->info('MadelineProto.log از ' . round($currentSize / 1024 / 1024, 1) . ' به ~' . round($keepBytes / 1024 / 1024, 1) . ' مگابایت کوچیک شد.');
    }
}
