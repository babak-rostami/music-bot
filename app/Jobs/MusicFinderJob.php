<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotFactory;
use App\Bot\Telegram\Services\TelegramService;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderResult;
use App\Services\MusicFinder\MusicFinderService;
use App\Services\MusicFinder\MusicFinderUsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MusicFinderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;

    public function __construct(
        public readonly string $platform,      // telegram | bale
        public readonly int|string $chatId,
        public readonly string $mode,          // text | link | media | select
        public readonly ?string $text = null,
        public readonly ?string $mediaPath = null,
        public readonly ?string $mediaType = null,
        public readonly ?int $sourceMessageId = null,
        public readonly ?string $callbackData = null,
        public readonly ?string $originalQuery = null,
    ) {}

    public function handle(MusicFinderService $finder): void
    {
        $service = BotFactory::make($this->platform);

        $onQueued = fn() => $service->sendMessage(
            $this->chatId,
            '⏳ تعداد درخواست ها خیلی زیاد شده، لطفا چند ثانیه دیگه دوباره بفرست...'
        );

        try {
            $result = match ($this->mode) {
                'text'   => $finder->sendText($this->text, $onQueued),
                'link'   => $finder->identifyFromLink($this->text, $onQueued),
                'media'  => $finder->sendMedia($this->mediaPath, $this->mediaType, $onQueued),
                'select' => $finder->clickButton($this->sourceMessageId, $this->callbackData, $onQueued),
            };

            $this->deliver($service, $result);
        } finally {
            if ($this->mediaPath && file_exists($this->mediaPath)) {
                @unlink($this->mediaPath);
            }

            MusicFinderAccess::release($this->platform, $this->chatId);
        }
    }

    private function deliver($service, MusicFinderResult $result): void
    {
        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO   => $this->deliverAudio($service, $result),
            MusicFinderResult::TYPE_BUTTONS => $this->deliverButtons($service, $result),
            MusicFinderResult::TYPE_ERROR   => $service->sendMessage($this->chatId, '⚠️ ' . $result->text),
        };
    }

    private function deliverAudio($service, MusicFinderResult $result): void
    {
        $fileName = ($result->title ?? 'آهنگ') . '.mp3';

        $service->sendAudio(
            $this->chatId,
            $result->filePath,
            $fileName,
            $result->title ?? 'آهنگ شناسایی‌شده',
            $result->performer ?? ''
        );

        $displayText = trim(($result->title ?? '') . ($result->performer ? ' - ' . $result->performer : ''));
        if ($displayText === '') {
            $displayText = $this->originalQuery ?? $this->text;
        }

        MusicFinderUsageService::recordExternalDelivery($this->platform, $this->chatId, $displayText);

        $quotaMessage = MusicFinderUsageService::remainingQuotaMessage($this->platform, $this->chatId);
        if ($quotaMessage) {
            $service->sendMessage($this->chatId, $quotaMessage);
        }

        $this->notifyAdmin($result);

        @unlink($result->filePath);
    }

    private function deliverButtons($service, MusicFinderResult $result): void
    {
        $buttons = [];
        foreach ($result->buttons as $row) {
            $line = [];
            foreach ($row as $button) {
                // callback_data واقعیِ بات مقصد رو ۱۰ دقیقه کش می‌کنیم و یه توکن کوتاه به کاربر می‌دیم
                $token = Str::random(12);
                Cache::put(
                    "music_finder_btn:{$token}",
                    ['message_id' => $result->sourceMessageId, 'data' => $button['callback_data']],
                    now()->addMinutes(10)
                );

                $line[] = ['text' => $button['text'], 'callback_data' => "mf_pick_{$token}"];
            }
            $buttons[] = $line;
        }

        $service->sendMessage($this->chatId, $result->text ?: 'یکی رو انتخاب کن:', $buttons);
    }

    private function notifyAdmin(MusicFinderResult $result): void
    {
        $channelId = config('telegram.notify_channel_id');
        if (!$channelId || !file_exists($result->filePath)) {
            return;
        }

        try {
            $telegram = app(TelegramService::class);

            $telegram->sendAudio(
                $channelId,
                $result->filePath,
                ($result->title ?? 'song') . '.mp3',
                $result->title ?? '-',
                $result->performer ?? '-'
            );

            $telegram->sendMessage(
                $channelId,
                "🎯 آهنگ شناسایی‌شده برای کاربر\n\n"
                    . "🤖 پلتفرم: " . ($this->platform === 'bale' ? 'بله' : 'تلگرام') . "\n"
                    . "👤 chat_id: {$this->chatId}\n"
                    . "🔍 درخواست اصلی: " . ($this->originalQuery ?? $this->text ?? '-') . "\n\n"
                    . "اگه خوب بود، با /new-song ثبتش کن توی دیتابیس."
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admin about identified song', ['error' => $e->getMessage()]);
        }
    }
}
