<?php

namespace App\Bot\Telegram\Services;

use App\Bot\Shared\BotApiInterface;
use App\Bot\Shared\Concerns\HasRetryableHttp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService implements BotApiInterface
{
    use HasRetryableHttp;
    protected string $apiUrl;
    private string $caption;


    public function __construct()
    {
        $this->apiUrl = 'https://api.telegram.org/bot' . config('telegram.bot_token');
        $this->caption = "ربات آهنگ یاب : @download_music_jangi_bot";
    }

    public function platform(): string
    {
        return 'telegram';
    }

    /**
     * ارسال پیام متنی
     *
     * @param int|string $chatId
     * @param string $text
     * @param array|null $inlineKeyboard  آرایه دکمه‌ها (مثلاً [['text' => '...', 'callback_data' => '...']])
     * @param string|null $parseMode     'HTML' | 'MarkdownV2' | null (plain text)
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $inlineKeyboard = null,
        ?string $parseMode = null
    ) {
        $payload = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        }

        return $this->http()->post($this->apiUrl . '/sendMessage', $payload);
    }

    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $inlineKeyboard = null,
        ?string $parseMode = null
    ) {
        $payload = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
        ];

        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        }

        return $this->http()->post($this->apiUrl . '/editMessageText', $payload);
    }

    /**
     * یک پیام را از چت حذف می‌کند.
     */
    public function deleteMessage(int|string $chatId, int $messageId)
    {
        return $this->http()->post($this->apiUrl . '/deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }
    public function sendAudio(int|string $chatId, string $filePath, string $fileName, string $title, string $performer)
    {
        return Http::attach('audio', fopen($filePath, 'r'), $fileName)
            ->post($this->apiUrl . '/sendAudio', [
                'chat_id'   => $chatId,
                'title'     => $title,
                'performer' => $performer,
                'caption'   => $this->caption,
            ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false)
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        if ($showAlert) {
            $payload['show_alert'] = true;
        }

        return $this->http()->post($this->apiUrl . '/answerCallbackQuery', $payload);
    }

    public function sendAudioByFileId(int|string $chatId, string $fileId, string $title, string $performer)
    {
        return $this->http()->post($this->apiUrl . '/sendAudio', [
            'chat_id'   => $chatId,
            'audio'     => $fileId,
            'title'     => $title,
            'performer' => $performer,
            'caption'   => $this->caption,
        ]);
    }

    public function uploadAudioToCacheChannel(string $filePath, $song): ?array
    {
        $cacheChannelId = config('telegram.cache_channel_id');

        if (!$cacheChannelId) {
            return null;
        }

        // فقط فایل صوتی رو می‌فرستیم به کانال کش
        // متن آهنگ رو جداگانه فقط به کاربر می‌فرستیم (نه به کانال کش)
        // اینجوری کانال کش فقط audio message داره و شلوغ نمیشه
        $response = Http::attach('audio', fopen($filePath, 'r'), basename($filePath))
            ->post($this->apiUrl . '/sendAudio', [
                'chat_id'   => $cacheChannelId,
                'title'     => $song->title,
                'performer' => $song->artist_name,
                'caption'   => $this->caption,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $message = $response->json('result');

        return [
            'file_id' => $message['audio']['file_id'] ?? null,
        ];
    }

    /**
     * بررسی عضویت یک کاربر در یک چت (کانال/گروه)
     */
    public function getChatMember(string|int $chatId, int $userId): ?array
    {
        $response = $this->http()->post($this->apiUrl . '/getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        if ($response->successful()) {
            return $response->json('result');
        }

        $description = (string) $response->json('description', '');

        // این یکی یعنی کاربر فقط هنوز عضو نشده — کاملاً عادیه، هر کاربر جدیدی این جواب رو می‌گیره
        $isExpectedNotMember = str_contains($description, 'user not found')
            || str_contains($description, 'PARTICIPANT_ID_INVALID')
            || str_contains($description, 'not participant');

        if (!$isExpectedNotMember) {
            Log::warning('Bale getChatMember failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => $description,
            ]);
        }

        return null;
    }

    /**
     * فوروارد کردن پیام در تلگرام
     */
    public function forwardMessage(
        int|string $fromChatId,
        int $messageId,
        int|string|null $toChatId = null
    ) {
        // اگر چت مقصد مشخص نشده بود، از کانال کش کانفیگ استفاده کن
        $toChatId = $toChatId ?? config('telegram.cache_channel_id');

        $payload = [
            'chat_id'      => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ];

        return $this->http()->post($this->apiUrl . '/forwardMessage', $payload);
    }

    /**
     * کپی کردن پیام در تلگرام (بدون برچسب فوروارد)
     */
    public function copyMessage(
        int|string $fromChatId,
        int $messageId,
        int|string|null $toChatId = null,
        ?string $caption = null
    ) {
        $toChatId = $toChatId ?? config('telegram.cache_channel_id');

        $payload = [
            'chat_id'      => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->http()->post($this->apiUrl . '/copyMessage', $payload);
    }

    public function getFile(string $fileId): ?array
    {
        $response = $this->http()->post($this->apiUrl . '/getFile', ['file_id' => $fileId]);

        if (!$response->successful()) {
            Log::warning('Telegram getFile failed', ['file_id' => $fileId, 'body' => $response->json('description')]);
            return null;
        }

        return $response->json('result');
    }

    public function downloadFile(string $filePath, string $destination): bool
    {
        $url = 'https://api.telegram.org/file/bot' . config('telegram.bot_token') . '/' . $filePath;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $stream = fopen($destination, 'w'); // هر تلاش، فایل کاملاً از نو باز می‌شه

            try {
                $response = Http::timeout(120)->withOptions(['sink' => $stream])->get($url);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($attempt === 3) {
                    return false;
                }
                sleep(2);
                continue;
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($response->successful()) {
                return true;
            }

            if ($attempt === 3) {
                return false;
            }
            sleep(2);
        }

        return false;
    }

    public function setMyCommands(array $commands, int|string|null $scopeChatId = null): bool
    {
        $payload = ['commands' => $commands];

        if ($scopeChatId !== null) {
            $payload['scope'] = [
                'type'    => 'chat',
                'chat_id' => $scopeChatId,
            ];
        }

        $response = $this->http()->post($this->apiUrl . '/setMyCommands', $payload);

        return $response->successful();
    }
}
