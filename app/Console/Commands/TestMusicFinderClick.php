<?php

namespace App\Console\Commands;

use App\Services\MusicFinder\MusicFinderBotRegistry;
use App\Services\MusicFinder\MusicFinderResult;
use Illuminate\Console\Command;

class TestMusicFinderClick extends Command
{
    protected $signature = 'music-finder:click {botKey} {messageId} {data} {--platform=telegram}';
    protected $description = 'تست کلیک روی یکی از دکمه‌های نتیجه (باید botKey همونی باشه که music-finder:test نشون داد)';

    public function handle(): int
    {
        $finder = MusicFinderBotRegistry::get($this->argument('botKey'));

        $result = $finder->clickButton(
            $this->option('platform'),
            (int) $this->argument('messageId'),
            $this->argument('data')
        );

        $this->line('نوع نتیجه: <comment>' . $result->type . '</comment>');

        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO => $this->info(
                "✅ آهنگ دانلود شد:\n  مسیر: {$result->filePath}\n  عنوان: {$result->title}"
            ),
            MusicFinderResult::TYPE_TELEGRAM_RELAY => $this->info(
                "✅ آهنگ رله شد:\n  کانال: {$result->relayChannelId}  message_id: {$result->relayMessageId}"
            ),
            MusicFinderResult::TYPE_ERROR => $this->error($result->text),
            default => $this->warn('نتیجه غیرمنتظره: ' . $result->type),
        };

        return self::SUCCESS;
    }
}
