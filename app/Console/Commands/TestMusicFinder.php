<?php

namespace App\Console\Commands;

use App\Services\MusicFinder\MusicFinderBotRegistry;
use App\Services\MusicFinder\MusicFinderResult;
use Illuminate\Console\Command;

class TestMusicFinder extends Command
{
    protected $signature = 'music-finder:test {query} {--media=} {--platform=telegram}';
    protected $description = 'تست مستقیم سرویس MusicFinder (round-robin بین بات‌ها) بدون نیاز به تلگرام/بله واقعی';

    public function handle(): int
    {
        $query = $this->argument('query');
        $mediaType = $this->option('media');
        $platform = $this->option('platform');

        $finder = MusicFinderBotRegistry::next();
        $this->info("بات انتخاب‌شده (round-robin): {$finder->key()} — پلتفرم مقصد شبیه‌سازی‌شده: {$platform}");

        $result = $mediaType
            ? $finder->sendMedia($platform, $query, $mediaType, fn() => $this->warn('⏳ صف شلوغه...'))
            : (preg_match('~youtube\.com|youtu\.be|instagram\.com|tiktok\.com~i', $query)
                ? $finder->identifyFromLink($platform, $query, fn() => $this->warn('⏳ صف شلوغه...'))
                : $finder->sendText($platform, $query, fn() => $this->warn('⏳ صف شلوغه...')));

        $this->newLine();
        $this->line('نوع نتیجه: <comment>' . $result->type . '</comment>');

        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO => $this->info(
                "✅ آهنگ دانلود شد:\n  مسیر: {$result->filePath}\n  عنوان: {$result->title}\n  خواننده: {$result->performer}"
            ),
            MusicFinderResult::TYPE_TELEGRAM_RELAY => $this->info(
                "✅ آهنگ رله شد (بدون دانلود):\n  کانال رله: {$result->relayChannelId}\n  message_id: {$result->relayMessageId}\n  عنوان: {$result->title}"
            ),
            MusicFinderResult::TYPE_BUTTONS => $this->displayButtons($finder->key(), $result),
            MusicFinderResult::TYPE_ERROR => $this->error($result->text),
        };

        return self::SUCCESS;
    }

    private function displayButtons(string $botKey, MusicFinderResult $result): void
    {
        $this->line('متن: ' . $result->text);
        $this->line('message_id مبدا: ' . $result->sourceMessageId);
        $this->newLine();

        foreach ($result->buttons as $row) {
            foreach ($row as $button) {
                $this->line("  🔘 {$button['text']}  →  callback_data: {$button['callback_data']}");
            }
        }

        $this->newLine();
        $this->comment('برای تست کلیک روی یکی از دکمه‌ها:');
        $this->comment("php artisan music-finder:click {$botKey} {$result->sourceMessageId} 'DATA_رو_از_بالا_کپی_کن'");
    }
}
