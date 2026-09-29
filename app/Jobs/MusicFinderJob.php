<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Telegram\Services\TelegramService;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderBotRegistry;
use App\Services\MusicFinder\MusicFinderResult;
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
        public string $platform,
        public int|string $chatId,
        public string $mode,               // text | link | media | select
        public ?string $text = null,
        public ?string $mediaPath = null,
        public ?string $mediaType = null,
        public ?int $sourceMessageId = null,
        public ?string $callbackData = null,
        public ?string $originalQuery = null,
        public ?string $botKey = null,     // فقط برای mode=select — کدوم بات دکمه رو نشون داده بود
    ) {}

    public function handle(): void
    {
        $service = $this->platform === 'bale' ? app(BaleService::class) : app(TelegramService::class);

        // برای «select» حتماً باید همون باتی که دکمه رو نشون داده بود صدا بزنیم؛
        // برای بقیه‌ی حالت‌ها، یه بات جدید به‌صورت یکی‌درمیون انتخاب می‌شه.
        $finder = $this->mode === 'select'
            ? MusicFinderBotRegistry::get($this->botKey ?? '')
            : MusicFinderBotRegistry::next();

        $onQueued = fn() => $service->sendMessage(
            $this->chatId,
            '⏳ همین الان یکی دیگه داره از این سرویس استفاده می‌کنه، چند لحظه صبر کن، نوبتت می‌رسه...'
        );

        try {
            $result = match ($this->mode) {
                'text'   => $finder->sendText($this->platform, $this->text, $onQueued),
                'link'   => $finder->identifyFromLink($this->platform, $this->text, $onQueued),
                'media'  => $finder->sendMedia($this->platform, $this->mediaPath, $this->mediaType, $onQueued),
                'select' => $finder->clickButton($this->platform, $this->sourceMessageId, $this->callbackData, $onQueued),
            };

            $this->deliver($service, $finder->key(), $result);
        } finally {
            if ($this->mediaPath && file_exists($this->mediaPath)) {
                @unlink($this->mediaPath);
            }
            MusicFinderAccess::release($this->platform, $this->chatId);
        }
    }

    private function deliver($service, string $botKey, MusicFinderResult $result): void
    {
        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO,
            MusicFinderResult::TYPE_TELEGRAM_RELAY => $this->deliverAudio($service, $result),
            MusicFinderResult::TYPE_BUTTONS        => $this->deliverButtons($service, $botKey, $result),
            MusicFinderResult::TYPE_ERROR          => $service->sendMessage($this->chatId, $result->text),
        };
    }

    private function deliverAudio($service, MusicFinderResult $result): void
    {
        $displayTitle = trim(($result->title ?? '') . ($result->performer ? ' - ' . $result->performer : ''));
        $caption = $displayTitle !== '' ? "🎧 {$displayTitle}" : null;

        if ($result->type === MusicFinderResult::TYPE_TELEGRAM_RELAY) {
            // بدون دانلود/آپلود — مستقیم از کانال رله سمت سرورهای تلگرام به کاربر کپی می‌شه
            $service->copyMessage($result->relayChannelId, $result->relayMessageId, $this->chatId, $caption);
        } else {
            $fileName = ($result->title ?? 'آهنگ') . '.mp3';
            $service->sendAudio($this->chatId, $result->filePath, $fileName, $result->title ?? 'آهنگ شناسایی‌شده', $result->performer ?? '');
        }

        $displayText = $displayTitle !== '' ? $displayTitle : ($this->originalQuery ?? $this->text);
        MusicFinderUsageService::recordExternalDelivery($this->platform, $this->chatId, $displayText);

        $quotaMessage = MusicFinderUsageService::remainingQuotaMessage($this->platform, $this->chatId);
        if ($quotaMessage) {
            $service->sendMessage($this->chatId, $quotaMessage);
        }

        $this->notifyAdmin($result);

        if ($result->type === MusicFinderResult::TYPE_AUDIO) {
            @unlink($result->filePath);
        }
    }

    private function deliverButtons($service, string $botKey, MusicFinderResult $result): void
    {
        $buttons = [];
        foreach ($result->buttons as $row) {
            $line = [];
            foreach ($row as $button) {
                $token = Str::random(12);
                Cache::put(
                    "music_finder_btn:{$token}",
                    [
                        'message_id' => $result->sourceMessageId,
                        'data'       => $button['callback_data'],
                        'bot_key'    => $botKey,
                    ],
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
        if (!$channelId) {
            return;
        }

        try {
            $telegram = app(TelegramService::class);
            // $caption = "🎯 آهنگ شناسایی‌شده برای کاربر\n\n"
            //     . "🤖 پلتفرم: " . ($this->platform === 'bale' ? 'بله' : 'تلگرام') . "\n"
            //     . "👤 chat_id: {$this->chatId}\n"
            //     . "🔍 درخواست اصلی: " . ($this->originalQuery ?? $this->text ?? '-') . "\n\n"
            //     . "اگه خوب بود، با /new-song ثبتش کن توی دیتابیس.";

            if ($result->type === MusicFinderResult::TYPE_TELEGRAM_RELAY) {
                $telegram->copyMessage($result->relayChannelId, $result->relayMessageId, $channelId);
            } elseif ($result->filePath && file_exists($result->filePath)) {
                $telegram->sendAudio($channelId, $result->filePath, ($result->title ?? 'song') . '.mp3', $result->title ?? '-', $result->performer ?? '-');
            }

            // $telegram->sendMessage($channelId, $caption);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admin about identified song', ['error' => $e->getMessage()]);
        }
    }
}
