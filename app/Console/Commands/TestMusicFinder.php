<?php

namespace App\Console\Commands;

use App\Services\MusicFinder\MusicFinderResult;
use App\Services\MusicFinder\MusicFinderService;
use Illuminate\Console\Command;

class TestMusicFinder extends Command
{
    protected $signature = 'music-finder:test {query : متن، لینک، یا مسیر فایل محلی} {--media= : اگه query مسیر فایله: audio یا video یا voice}';
    protected $description = 'تست مستقیم سرویس MusicFinder بدون نیاز به تلگرام/بله (برای دیباگ)';

    public function handle(MusicFinderService $finder): int
    {
        $query = $this->argument('query');
        $mediaType = $this->option('media');

        $this->info('در حال ارسال درخواست... (اگه صف بود بهت می‌گم)');

        $result = $mediaType
            ? $finder->sendMedia($query, $mediaType, fn() => $this->warn('⏳ صف شلوغه، منتظر می‌مونم...'))
            : (preg_match('~youtube\.com|youtu\.be|instagram\.com|tiktok\.com~i', $query)
                ? $finder->identifyFromLink($query, fn() => $this->warn('⏳ صف شلوغه، منتظر می‌مونم...'))
                : $finder->sendText($query, fn() => $this->warn('⏳ صف شلوغه، منتظر می‌مونم...')));

        $this->newLine();
        $this->line('نوع نتیجه: <comment>' . $result->type . '</comment>');

        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO => $this->info(
                "✅ آهنگ دانلود شد:\n  مسیر: {$result->filePath}\n  عنوان: {$result->title}\n  خواننده: {$result->performer}"
            ),
            MusicFinderResult::TYPE_BUTTONS => $this->displayButtons($result),
            MusicFinderResult::TYPE_ERROR => $this->error($result->text),
        };

        return self::SUCCESS;
    }

    private function displayButtons(MusicFinderResult $result): void
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
        $this->comment("php artisan tinker");
        $this->comment("app(App\\Services\\MusicFinder\\MusicFinderService::class)->clickButton({$result->sourceMessageId}, 'DATA_رو_از_بالا_کپی_کن')");
    }
}
