<?php

namespace App\Console\Commands;

use App\Services\MusicFinder\MusicFinderResult;
use App\Services\MusicFinder\MusicFinderService;
use Illuminate\Console\Command;

class TestMusicFinderClick extends Command
{
    protected $signature = 'music-finder:click {messageId} {data}';
    protected $description = 'تست کلیک روی یکی از دکمه‌های نتیجه (برای دیباگ پاسخ صوتی)';

    public function handle(MusicFinderService $finder): int
    {
        $result = $finder->clickButton((int) $this->argument('messageId'), $this->argument('data'));

        $this->line('نوع نتیجه: <comment>' . $result->type . '</comment>');

        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO => $this->info(
                "✅ آهنگ دانلود شد:\n  مسیر: {$result->filePath}\n  عنوان: {$result->title}\n  خواننده: {$result->performer}"
            ),
            MusicFinderResult::TYPE_ERROR => $this->error($result->text),
            default => $this->warn('نتیجه غیرمنتظره: ' . $result->type),
        };

        return self::SUCCESS;
    }
}
