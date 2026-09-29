<?php

namespace App\Services\MusicFinder;

use danog\MadelineProto\API;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * سرویس صحبت با یک بات آهنگ‌یاب مشخص (از طریق MadelineProto).
 *
 * هر نمونه از این کلاس به یک بات خاص وصله (botKey + peerUsername).
 * از new مستقیم استفاده نکن — همیشه از MusicFinderBotRegistry بگیرش.
 *
 * قفل و فاصله‌ی زمانی per-bot هست (نه سراسری)، تا وقتی چند تا بات داریم،
 * واقعاً بتونن مستقل و موازی درخواست پردازش کنن.
 */
class MusicFinderService
{
    private const MAX_MEDIA_SIZE_MB = 20;
    private const NOT_FOUND_MESSAGE = "🔎 نتونستم پیداش کنم\nیه آهنگ دیگه رو امتحان کن";

    public function __construct(
        private readonly string $botKey,
        private readonly string $peerUsername,
    ) {}

    public function key(): string
    {
        return $this->botKey;
    }

    public function sendText(string $forPlatform, string $text, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api) use ($forPlatform, $text) {
            $lastId = $this->lastMessageId($api);
            $this->guardedCall(fn() => $api->messages->sendMessage(['peer' => $this->peerUsername, 'message' => $text]));
            return $this->waitForReply($api, $forPlatform, $lastId);
        }, $onQueued);
    }

    public function sendMedia(string $forPlatform, string $filePath, string $type, ?\Closure $onQueued = null): MusicFinderResult
    {
        if (!file_exists($filePath)) {
            return MusicFinderResult::error('فایل موردنظر پیدا نشد.');
        }

        $sizeMb = filesize($filePath) / 1024 / 1024;
        if ($sizeMb > self::MAX_MEDIA_SIZE_MB) {
            return MusicFinderResult::error(sprintf('رسانه خیلی زیاد! حجم نباید بیشتر از %d مگابایت باشد.', self::MAX_MEDIA_SIZE_MB));
        }

        return $this->withLock(function (API $api) use ($forPlatform, $filePath, $type) {
            $lastId = $this->lastMessageId($api);

            $attributes = match ($type) {
                'voice' => [['_' => 'documentAttributeAudio', 'voice' => true]],
                'video' => [['_' => 'documentAttributeVideo']],
                default => [['_' => 'documentAttributeAudio']],
            };
            $mimeType = match ($type) {
                'voice' => 'audio/ogg',
                'video' => 'video/mp4',
                default => 'audio/mpeg',
            };

            $this->guardedCall(fn() => $api->messages->sendMedia([
                'peer'  => $this->peerUsername,
                'media' => [
                    '_'          => 'inputMediaUploadedDocument',
                    'file'       => $api->upload($filePath),
                    'mime_type'  => $mimeType,
                    'attributes' => $attributes,
                ],
            ]));

            // audioOnly=true: هر پیام میانی (استیکر، عکس اطلاعات، متن) نادیده گرفته می‌شه تا خود آهنگ برسه
            return $this->waitForReply($api, $forPlatform, $lastId, audioOnly: true);
        }, $onQueued);
    }

    public function clickButton(string $forPlatform, int $sourceMessageId, string $callbackData, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api) use ($forPlatform, $sourceMessageId, $callbackData) {
            $lastId = $this->lastMessageId($api);

            $this->guardedCall(function () use ($api, $sourceMessageId, $callbackData) {
                try {
                    $api->messages->getBotCallbackAnswer([
                        'peer'   => $this->peerUsername,
                        'msg_id' => $sourceMessageId,
                        'data'   => $callbackData,
                    ]);
                } catch (\Throwable $e) {
                    // بعضی بات‌ها به کلیک دکمه answerCallbackQuery نمی‌زنن، ولی کلیک رو پردازش می‌کنن.
                    // تلگرام توی این حالت BOT_RESPONSE_TIMEOUT می‌ده، که شکست نیست: کلیک رسیده،
                    // پس ادامه می‌دیم و منتظر پیام آهنگ می‌مونیم.
                    if (str_contains($e->getMessage(), 'BOT_RESPONSE_TIMEOUT')) {
                        return null;
                    }

                    throw $e;
                }
            });

            return $this->waitForReply($api, $forPlatform, $lastId);
        }, $onQueued);
    }

    /**
     * سناریوی لینک: لینک رو می‌فرسته و منتظر می‌مونه. این متد به‌صورت خودکار
     * هم حالتی که بات بعد از ویدیو خودش آهنگ رو بفرسته، هم حالتی که نیاز به
     * کلیک روی دکمه‌ی «شناسایی موسیقی» داره رو پشتیبانی می‌کنه — چون منطق
     * رد کردن پیام‌های میانی (waitForReply/shouldSkipMessage) هر دو حالت رو
     * به‌صورت عمومی درست تشخیص می‌ده، نیازی به کد جدا برای هر بات نیست.
     */
    public function identifyFromLink(string $forPlatform, string $url, ?\Closure $onQueued = null): MusicFinderResult
    {
        $first = $this->sendText($forPlatform, $url, $onQueued);

        if ($first->type !== MusicFinderResult::TYPE_BUTTONS) {
            return $first; // یا مستقیم آهنگ/رله برگشت، یا خطا — کار تمومه
        }

        $identifyButton = $this->findButtonByText($first->buttons, 'شناسایی موسیقی');
        if (!$identifyButton) {
            return MusicFinderResult::error(self::NOT_FOUND_MESSAGE);
        }

        return $this->clickButton($forPlatform, $first->sourceMessageId, $identifyButton['callback_data']);
    }

    // ─────────────────────────────────────────────────

    private function findButtonByText(array $rows, string $needle): ?array
    {
        foreach ($rows as $row) {
            foreach ($row as $button) {
                if (str_contains($button['text'] ?? '', $needle)) {
                    return $button;
                }
            }
        }
        return null;
    }

    private function withLock(\Closure $callback, ?\Closure $onQueued = null): MusicFinderResult
    {
        $lock = Cache::lock($this->lockKey(), 90);

        if (!$lock->get()) {
            if (is_callable($onQueued)) {
                $onQueued();
            }
            try {
                $lock->block(60);
            } catch (LockTimeoutException) {
                return MusicFinderResult::error('سرویس شلوغه، چند لحظه دیگه دوباره امتحان کن.');
            }
        }

        try {
            $api = MadelineConnection::get();
            return $callback($api);
        } catch (\Throwable $e) {
            Log::error('MusicFinderService failed', ['bot' => $this->botKey, 'error' => $e->getMessage()]);
            return MusicFinderResult::error('ارتباط با سرویس شناسایی آهنگ با خطا مواجه شد، دوباره امتحان کن.');
        } finally {
            $lock->release();
        }
    }

    private function lockKey(): string
    {
        return "music_finder_lock:{$this->botKey}";
    }

    private function guardedCall(\Closure $call, int $maxRetries = 1)
    {
        $this->respectMinimumSpacing();
        $attempt = 0;

        while (true) {
            try {
                return $call();
            } catch (\Throwable $e) {
                $message = $e->getMessage();

                if (preg_match('/FLOOD_WAIT_(\d+)/', $message, $m)) {
                    $wait = (int) $m[1];
                    if ($wait > 55) {
                        $this->alertAdmin("⛔️ [{$this->botKey}] تلگرام محدودیت نرخ گذاشته (FLOOD_WAIT {$wait} ثانیه).");
                        throw $e;
                    }
                    sleep($wait + 1);
                    if ($attempt++ < $maxRetries) {
                        continue;
                    }
                }

                if (preg_match('/AUTH_KEY_UNREGISTERED|USER_DEACTIVATED|SESSION_REVOKED|AUTH_KEY_PERM_EMPTY/', $message)) {
                    $this->alertAdmin("🚨 [{$this->botKey}] سشن MadelineProto قطع شده ({$message})! باید دوباره لاگین کنی.");
                }

                throw $e;
            }
        }
    }

    private function respectMinimumSpacing(): void
    {
        $minGap = (float) config('services.music_finder_bot.min_gap_seconds', 3);
        $key = "music_finder_last_send_at:{$this->botKey}";
        $last = Cache::get($key);

        if ($last && (microtime(true) - $last) < $minGap) {
            usleep((int) (($minGap - (microtime(true) - $last)) * 1_000_000));
        }

        Cache::put($key, microtime(true), 60);
    }

    private function alertAdmin(string $message): void
    {
        $alertKey = "music_finder_alerted:{$this->botKey}";
        if (Cache::has($alertKey)) {
            return;
        }
        Cache::put($alertKey, true, now()->addMinutes(30));

        try {
            app(\App\Bot\NotificationService::class)->sendToAdmin($message, \App\Models\ActivityLog::LEVEL_ERROR);
        } catch (\Throwable $e) {
            Log::error('Failed to alert admin about MusicFinder issue', ['error' => $e->getMessage()]);
        }
    }

    private function lastMessageId(API $api): int
    {
        $history = $api->messages->getHistory(['peer' => $this->peerUsername, 'limit' => 1]);
        return $history['messages'][0]['id'] ?? 0;
    }

    // ─────────────────────────────────────────────────
    // دریافت و تفسیر پاسخ
    // ─────────────────────────────────────────────────

    private function waitForReply(API $api, string $forPlatform, int $afterMessageId, bool $audioOnly = false): MusicFinderResult
    {
        $timeout = (int) config('services.music_finder_bot.timeout', 40);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            try {
                $history = $api->messages->getHistory(['peer' => $this->peerUsername, 'limit' => 5]);
            } catch (\Throwable $e) {
                if (preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $m)) {
                    sleep(min((int) $m[1] + 1, 10));
                    continue;
                }
                throw $e;
            }

            $messages = $history['messages'] ?? [];

            foreach (array_reverse($messages) as $message) {
                $messageId = $message['id'] ?? 0;
                if ($messageId <= $afterMessageId) {
                    continue;
                }
                if ($message['out'] ?? false) {
                    continue;
                }
                if ($this->shouldSkipMessage($message, $audioOnly)) {
                    continue;
                }

                return $this->parseMessage($api, $forPlatform, $message);
            }

            usleep(700_000);
        }

        return MusicFinderResult::error('سرویس شناسایی آهنگ جواب نداد، دوباره امتحان کن.');
    }

    /**
     * پیام‌هایی که «پاسخ نهایی» نیستن و باید نادیده گرفته بشن:
     * استیکر (نشونه‌ی «در حال جستجو»)، متن وضعیت موقتی، عکس/ویدیوی میانی بدون دکمه،
     * و در حالت audioOnly هر چیزی جز خود فایل صوتی.
     */
    private function shouldSkipMessage(array $message, bool $audioOnly): bool
    {
        $text = $message['message'] ?? '';
        $mediaType = $message['media']['_'] ?? null;
        $document = $mediaType === 'messageMediaDocument' ? ($message['media']['document'] ?? null) : null;
        $isAudio = $document && $this->documentIsAudio($document);
        $isSticker = $document && $this->documentIsSticker($document);
        $hasButtons = !empty($this->extractCallbackButtons($message['reply_markup'] ?? null));

        if ($isSticker) {
            return true;
        }

        if (str_contains($text, '⏳') || str_contains($text, 'در حال')) {
            return true;
        }

        if ($mediaType === 'messageMediaPhoto') {
            return true; // عکس اطلاعات میانی
        }

        if ($mediaType === 'messageMediaDocument' && !$isAudio && !$hasButtons) {
            return true; // ویدیوی میانی بدون دکمه
        }

        if ($audioOnly && !$isAudio) {
            return true;
        }

        return false;
    }

    private function documentIsAudio(array $document): bool
    {
        foreach ($document['attributes'] ?? [] as $attribute) {
            if (($attribute['_'] ?? null) === 'documentAttributeAudio') {
                return true;
            }
        }
        return false;
    }

    private function documentIsSticker(array $document): bool
    {
        foreach ($document['attributes'] ?? [] as $attribute) {
            if (($attribute['_'] ?? null) === 'documentAttributeSticker') {
                return true;
            }
        }
        return false;
    }

    private function parseMessage(API $api, string $forPlatform, array $message): MusicFinderResult
    {
        $mediaType = $message['media']['_'] ?? null;

        if ($mediaType === 'messageMediaDocument') {
            $document = $message['media']['document'] ?? null;

            if ($document && $this->documentIsAudio($document)) {
                $audioAttr = null;
                foreach ($document['attributes'] as $attribute) {
                    if (($attribute['_'] ?? null) === 'documentAttributeAudio') {
                        $audioAttr = $attribute;
                        break;
                    }
                }

                if ($forPlatform === 'telegram') {
                    $relay = $this->relayToTelegramChannel($api, $message, $audioAttr);
                    if ($relay) {
                        return $relay;
                    }
                    // اگه رله ناموفق بود یا تنظیم نشده، می‌ریم سراغ دانلود معمولی (زیر)
                }

                return $this->downloadAsLocalFile($api, $message['media'], $audioAttr);
            }
        }

        $inlineKeyboard = $this->extractCallbackButtons($message['reply_markup'] ?? null);
        if (!empty($inlineKeyboard)) {
            return MusicFinderResult::buttons(
                text: '🎵 این آهنگ‌ها رو پیدا کردم، یکی رو انتخاب کن:',
                buttons: $inlineKeyboard,
                sourceMessageId: $message['id'],
            );
        }

        // هر پاسخ متنی نهایی دیگه‌ای (نتونستم پیدا کنم، خطای اینستاگرام، هر چیز دیگه)
        // همیشه همین پیام تمیز خودمون رو برمی‌گردونیم — نه متن خام بات مقصد.
        return MusicFinderResult::error(self::NOT_FOUND_MESSAGE);
    }

    private function downloadAsLocalFile(API $api, array $media, ?array $audioAttr): MusicFinderResult
    {
        $tempDir = storage_path('app/private/music-finder/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filePath = $api->downloadToDir($media, $tempDir);

        return MusicFinderResult::audio(
            filePath: $filePath,
            title: $audioAttr['title'] ?? null,
            performer: $audioAttr['performer'] ?? null,
        );
    }

    /**
     * پیام آهنگ رو مستقیم (سمت سرورهای تلگرام، بدون رد شدن از سرور خودمون)
     * به یه کانال رله فوروارد می‌کنه که هم اکانت MadelineProto هم بات خودمون
     * توش ادمینن؛ بعداً با copyMessage از همون کانال به کاربر می‌رسونتش —
     * بدون هیچ دانلود/آپلود دستی.
     *
     * ⚠️ نیاز به تنظیم اولیه: اکانت MadelineProto باید عضو/ادمین همون کانال
     * (MUSIC_FINDER_RELAY_CHANNEL_ID) باشه. اگه تنظیم نشده بود یا فوروارد
     * شکست خورد، این متد null برمی‌گردونه و فراخوان به دانلود معمولی
     * برمی‌گرده — یعنی فیچر gracefully غیرفعال می‌شه، هیچی خراب نمی‌کنه.
     */
    private function relayToTelegramChannel(API $api, array $message, ?array $audioAttr): ?MusicFinderResult
    {
        $relayChannelId = config('services.music_finder_bot.relay_channel_id');
        if (!$relayChannelId) {
            return null;
        }

        try {
            $forwarded = $api->messages->forwardMessages([
                'from_peer'           => $this->peerUsername,
                'id'                  => [$message['id']],
                'to_peer'             => $relayChannelId,
                'drop_author'         => true,
                'drop_media_captions' => true,
                'random_id'           => [random_int(PHP_INT_MIN, PHP_INT_MAX)],
            ]);

            $newMessageId = $this->extractForwardedMessageId($forwarded);
            if (!$newMessageId) {
                Log::warning('Could not extract forwarded message id for relay', ['bot' => $this->botKey]);
                return null;
            }

            return MusicFinderResult::telegramRelay(
                relayChannelId: $relayChannelId,
                relayMessageId: $newMessageId,
                title: $audioAttr['title'] ?? null,
                performer: $audioAttr['performer'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning('Telegram relay forward failed, falling back to download', ['bot' => $this->botKey, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractForwardedMessageId(array $updates): ?int
    {
        foreach ($updates['updates'] ?? [] as $update) {
            if (in_array($update['_'] ?? null, ['updateNewChannelMessage', 'updateNewMessage'], true)) {
                return $update['message']['id'] ?? null;
            }
        }
        return null;
    }

    private function extractCallbackButtons(?array $replyMarkup): array
    {
        if (!$replyMarkup || ($replyMarkup['_'] ?? null) !== 'replyInlineMarkup') {
            return [];
        }

        $buttons = [];
        foreach ($replyMarkup['rows'] ?? [] as $row) {
            $line = [];
            foreach ($row['buttons'] ?? [] as $button) {
                if (($button['_'] ?? null) !== 'keyboardButtonCallback') {
                    continue;
                }
                $line[] = [
                    'text'          => $button['text'] ?? '',
                    'callback_data' => $this->extractCallbackData($button['data'] ?? null),
                ];
            }
            if (!empty($line)) {
                $buttons[] = $line;
            }
        }
        return $buttons;
    }

    private function extractCallbackData(mixed $rawData): string
    {
        if (is_string($rawData)) {
            return $rawData;
        }
        if (is_object($rawData) && isset($rawData->bytes) && is_string($rawData->bytes)) {
            return $rawData->bytes;
        }
        if ($rawData instanceof \Stringable) {
            return (string) $rawData;
        }
        if (is_array($rawData) && isset($rawData['bytes'])) {
            return $rawData['bytes'];
        }
        Log::warning('Unexpected callback data shape from MadelineProto', ['type' => get_debug_type($rawData)]);
        return '';
    }
}
