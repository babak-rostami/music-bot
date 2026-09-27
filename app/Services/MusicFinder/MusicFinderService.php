<?php

namespace App\Services\MusicFinder;

use danog\MadelineProto\API;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MusicFinderService
{
    private const LOCK_KEY = 'music_finder_bot_conversation';
    private const MAX_MEDIA_SIZE_MB = 20;

    public function sendText(string $text, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api, string $peer) use ($text) {
            $lastId = $this->lastMessageId($api, $peer);
            $this->guardedCall(fn() => $api->messages->sendMessage(['peer' => $peer, 'message' => $text]));
            return $this->waitForReply($api, $peer, $lastId);
        }, $onQueued);
    }

    public function sendMedia(string $filePath, string $type, ?\Closure $onQueued = null): MusicFinderResult
    {
        if (!file_exists($filePath)) {
            return MusicFinderResult::error('فایل موردنظر پیدا نشد.');
        }

        $sizeMb = filesize($filePath) / 1024 / 1024;

        if ($sizeMb > self::MAX_MEDIA_SIZE_MB) {
            return MusicFinderResult::error(
                sprintf('رسانه خیلی زیاد! حجم نباید بیشتر از %d مگابایت باشد.', self::MAX_MEDIA_SIZE_MB)
            );
        }

        return $this->withLock(function (API $api, string $peer) use ($filePath, $type) {
            $lastId = $this->lastMessageId($api, $peer);

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
                'peer'  => $peer,
                'media' => [
                    '_'          => 'inputMediaUploadedDocument',
                    'file'       => $api->upload($filePath),
                    'mime_type'  => $mimeType,
                    'attributes' => $attributes,
                ],
            ]));

            // audioOnly=true: هر پیام میانی (متن، دکمه‌ی «آهنگ اشتباه») نادیده گرفته می‌شه تا خود فایل صوتی برسه
            return $this->waitForReply($api, $peer, $lastId, audioOnly: true);
        }, $onQueued);
    }

    public function clickButton(int $sourceMessageId, string $callbackData, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api, string $peer) use ($sourceMessageId, $callbackData) {
            $lastId = $this->lastMessageId($api, $peer);

            $this->guardedCall(fn() => $api->messages->getBotCallbackAnswer([
                'peer'   => $peer,
                'msg_id' => $sourceMessageId,
                'data'   => $callbackData,
            ]));

            return $this->waitForReply($api, $peer, $lastId);
        }, $onQueued);
    }

    public function identifyFromLink(string $url, ?\Closure $onQueued = null): MusicFinderResult
    {
        $first = $this->sendText($url, $onQueued);

        if ($first->type !== MusicFinderResult::TYPE_BUTTONS) {
            return $first;
        }

        $identifyButton = $this->findButtonByText($first->buttons, 'شناسایی موسیقی');

        if (!$identifyButton) {
            return MusicFinderResult::error('گزینه‌ی شناسایی موسیقی پیدا نشد.');
        }

        return $this->clickButton($first->sourceMessageId, $identifyButton['callback_data']);
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
        $lock = Cache::lock(self::LOCK_KEY, 90);

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
            $peer = config('services.music_finder_bot.username');

            return $callback($api, $peer);
        } catch (\Throwable $e) {
            Log::error('MusicFinderService failed', ['error' => $e->getMessage()]);
            return MusicFinderResult::error('ارتباط با سرویس شناسایی آهنگ با خطا مواجه شد، دوباره امتحان کن.');
        } finally {
            $lock->release();
        }
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
                        $this->alertAdmin("⛔️ تلگرام محدودیت نرخ گذاشته (FLOOD_WAIT {$wait} ثانیه). سرویس شناسایی آهنگ موقتاً کند می‌شه.");
                        throw $e;
                    }

                    sleep($wait + 1);

                    if ($attempt++ < $maxRetries) {
                        continue;
                    }
                }

                if (preg_match('/AUTH_KEY_UNREGISTERED|USER_DEACTIVATED|SESSION_REVOKED|AUTH_KEY_PERM_EMPTY/', $message)) {
                    $this->alertAdmin("🚨 سشن MadelineProto قطع شده ({$message})! باید دوباره با madeline/login.php لاگین کنی.");
                }

                throw $e;
            }
        }
    }

    private function respectMinimumSpacing(): void
    {
        $minGap = (float) config('services.music_finder_bot.min_gap_seconds', 3);
        $key = 'music_finder_last_send_at';
        $last = Cache::get($key);

        if ($last && (microtime(true) - $last) < $minGap) {
            usleep((int) (($minGap - (microtime(true) - $last)) * 1_000_000));
        }

        Cache::put($key, microtime(true), 60);
    }

    private function alertAdmin(string $message): void
    {
        if (Cache::has('music_finder_alerted')) {
            return;
        }

        Cache::put('music_finder_alerted', true, now()->addMinutes(30));

        try {
            app(\App\Bot\NotificationService::class)->sendToAdmin($message, \App\Models\ActivityLog::LEVEL_ERROR);
        } catch (\Throwable $e) {
            Log::error('Failed to alert admin about MusicFinder issue', ['error' => $e->getMessage()]);
        }
    }

    private function lastMessageId(API $api, string $peer): int
    {
        $history = $api->messages->getHistory(['peer' => $peer, 'limit' => 1]);
        return $history['messages'][0]['id'] ?? 0;
    }

    // ─────────────────────────────────────────────────

    private function waitForReply(API $api, string $peer, int $afterMessageId, bool $audioOnly = false): MusicFinderResult
    {
        $timeout = (int) config('services.music_finder_bot.timeout', 40);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            try {
                $history = $api->messages->getHistory(['peer' => $peer, 'limit' => 5]);
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
                    continue; // پیام میانی/موقتی — جواب نهایی نیست، صبر کن
                }

                return $this->parseMessage($api, $message);
            }

            usleep(700_000);
        }

        return MusicFinderResult::error('سرویس شناسایی آهنگ جواب نداد، دوباره امتحان کن.');
    }

    /**
     * تشخیص پیام‌های میانی که نباید به‌عنوان جواب نهایی برگردن:
     * ۱) متن وضعیت موقتی (⏳ / در حال ...)
     * ۲) ویدیوی تنها بدون دکمه و بدون صدا (دکمه‌ی شناسایی معمولاً چند لحظه بعدش می‌رسه)
     * ۳) وقتی صریحاً منتظر خود فایل صوتی هستیم (audioOnly)، هر چیزی جز خود آهنگ
     */
    private function shouldSkipMessage(array $message, bool $audioOnly): bool
    {
        $text = $message['message'] ?? '';
        $mediaType = $message['media']['_'] ?? null;
        $isAudio = $mediaType === 'messageMediaDocument' && $this->documentIsAudio($message['media']['document'] ?? null);
        $hasButtons = !empty($this->extractCallbackButtons($message['reply_markup'] ?? null));

        if (str_contains($text, '⏳') || str_contains($text, 'در حال')) {
            return true;
        }

        if ($mediaType === 'messageMediaDocument' && !$isAudio && !$hasButtons) {
            return true;
        }

        if ($audioOnly && !$isAudio) {
            return true;
        }

        return false;
    }

    private function documentIsAudio(?array $document): bool
    {
        foreach ($document['attributes'] ?? [] as $attribute) {
            if (($attribute['_'] ?? null) === 'documentAttributeAudio') {
                return true;
            }
        }

        return false;
    }

    private function parseMessage(API $api, array $message): MusicFinderResult
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

                $tempDir = storage_path('app/private/music-finder/' . Str::uuid());
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $filePath = $api->downloadToDir($message['media'], $tempDir);

                return MusicFinderResult::audio(
                    filePath: $filePath,
                    title: $audioAttr['title'] ?? null,
                    performer: $audioAttr['performer'] ?? null,
                );
            }
        }

        $inlineKeyboard = $this->extractCallbackButtons($message['reply_markup'] ?? null);

        if (!empty($inlineKeyboard)) {
            return MusicFinderResult::buttons(
                text: $message['message'] ?: 'یکی رو انتخاب کن:',
                buttons: $inlineKeyboard,
                sourceMessageId: $message['id'],
            );
        }

        if ($mediaType !== null) {
            return MusicFinderResult::error('چیزی از این محتوا پیدا نشد.');
        }

        return MusicFinderResult::error($this->sanitizeErrorText($message['message'] ?: 'خطای نامشخص از سرویس شناسایی آهنگ.'));
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

    /**
     * اگه متن خطا لینک/منشن بات مقصد رو توش داشته باشه (leak هویت اون بات)،
     * به‌جاش یه پیام عمومی و تمیز برمی‌گردونیم؛ در غیر این صورت متن اصلی دست‌نخورده می‌مونه.
     */
    private function sanitizeErrorText(string $text): string
    {
        $hasLeak = (bool) preg_match('/\[[^\]]+\]\(https?:\/\/[^\)]+\)/u', $text)
            || (bool) preg_match('/@[A-Za-z0-9_]{4,}/u', $text)
            || (bool) preg_match('/https?:\/\/\S+/u', $text);

        if (!$hasLeak) {
            return $text;
        }

        return 'متأسفانه این مورد در دسترس نیست.';
    }
}
