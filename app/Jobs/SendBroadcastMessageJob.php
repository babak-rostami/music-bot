<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\BotUserService;
use App\Bot\Telegram\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly int|string $fromChatId,
        public readonly int $fromMessageId,
        public readonly int|string $toChatId,
        public readonly string $platform, // telegram | bale
    ) {}

    public function handle(TelegramService $telegram, BaleService $bale, BotUserService $botUserService): void
    {
        $service  = $this->platform === 'bale' ? $bale : $telegram;
        $response = $service->copyMessage($this->fromChatId, $this->fromMessageId, $this->toChatId);

        if ($response->successful()) {
            $botUserService->recordSuccessfulSend((int) $this->toChatId, $this->platform);
        } else {
            $botUserService->recordFailedSend((int) $this->toChatId, $this->platform);
        }
    }
}
