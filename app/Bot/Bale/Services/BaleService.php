<?php

namespace App\Bot\Bale\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * سرویس ارتباط با API بله
 * دقیقاً مشابه TelegramService با همان ساختار متدها
 */
class BaleService
{
    protected string $apiUrl;
    private string $caption;

    public function __construct()
    {
        // ✅ تصحیح شد: tapi.bale.ai (نه api.bale.ai/v1/bots/)
        $this->apiUrl = 'https://tapi.bale.ai/bot' . config('telegram.bale_bot_token');
        $this->caption = "ربات آهنگ یاب : @download_music_bot";
    }

    /**
     * ارسال پیام متنی
     *
     * @param int|string $chatId
     * @param string $text
     * @param array|null $inlineKeyboard
     * @param string|null $parseMode فقط 'HTML' پشتیبانی می‌شود
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?array $inlineKeyboard = null,
        ?string $parseMode = null
    ) {
        $payload = [
            'chat_id' => (string) $chatId,
            'text'    => $text,
        ];

        if ($parseMode === 'HTML') {
            $payload['parse_mode'] = 'HTML';
        }

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        }

        return Http::post($this->apiUrl . '/sendMessage', $payload);
    }

    /**
     * ویرایش پیام متنی
     */
    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?array $inlineKeyboard = null,
        ?string $parseMode = null
    ) {
        $payload = [
            'chat_id'    => (string) $chatId,
            'message_id' => $messageId,
            'text'       => $text,
        ];

        if ($parseMode === 'HTML') {
            $payload['parse_mode'] = 'HTML';
        }

        if ($inlineKeyboard !== null) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]);
        }

        return Http::post($this->apiUrl . '/editMessageText', $payload);
    }

    /**
     * حذف پیام
     */
    public function deleteMessage(int|string $chatId, int $messageId)
    {
        return Http::post($this->apiUrl . '/deleteMessage', [
            'chat_id'    => (string) $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * ارسال فایل صوتی با آپلود multipart
     */
    public function sendAudio(int|string $chatId, string $filePath, string $fileName, string $title, string $performer)
    {
        return Http::attach('audio', fopen($filePath, 'r'), $fileName)
            ->post($this->apiUrl . '/sendAudio', [
                'chat_id'   => (string) $chatId,
                'title'     => $title,
                'performer' => $performer,
                'caption'   => $this->caption,
            ]);
    }

    /**
     * پاسخ به callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null)
    {
        return Http::post($this->apiUrl . '/answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
        ]);
    }

    /**
     * ارسال صوت با file_id (برای کش)
     */
    public function sendAudioByFileId(int|string $chatId, string $fileId, string $title, string $performer)
    {
        return Http::post($this->apiUrl . '/sendAudio', [
            'chat_id'   => (string) $chatId,
            'audio'     => $fileId,
            'title'     => $title,
            'performer' => $performer,
            'caption'   => $this->caption,
        ]);
    }

    /**
     * آپلود صوت به کانال کش بله
     */
    public function uploadAudioToCacheChannel(string $filePath, $song): ?array
    {
        $cacheChannelId = config('telegram.bale_cache_channel_id');

        if (!$cacheChannelId) {
            return null;
        }

        $response = Http::attach('audio', fopen($filePath, 'r'), basename($filePath))
            ->post($this->apiUrl . '/sendAudio', [
                'chat_id'   => (string) $cacheChannelId,
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
     * بررسی عضویت کاربر در کانال/گروه
     */
    public function getChatMember(string|int $chatId, int $userId): ?array
    {
        $response = Http::post($this->apiUrl . '/getChatMember', [
            'chat_id' => (string) $chatId,
            'user_id' => $userId,
        ]);

        if (!$response->successful()) {
            Log::warning('Bale getChatMember failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => $response->json('description'),
            ]);
            return null;
        }

        return $response->json('result');
    }

    /**
     * فوروارد کردن پیام در بله
     */
    public function forwardMessage(
        int|string $fromChatId,
        int $messageId,
        int|string|null $toChatId = null
    ) {
        // استفاده از کانال کش بله
        $toChatId = $toChatId ?? config('telegram.bale_cache_channel_id');

        $payload = [
            'chat_id'      => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ];

        // با آدرس API بله ارسال می‌شود ($this->baleApiUrl)
        return Http::post($this->apiUrl . '/forwardMessage', $payload);
    }

    /**
     * کپی کردن پیام در بله (بدون برچسب فوروارد)
     */
    public function copyMessage(
        int|string $fromChatId,
        int $messageId,
        int|string|null $toChatId = null
    ) {
        $toChatId = $toChatId ?? config('telegram.bale_cache_channel_id');

        $payload = [
            'chat_id'      => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ];

        return Http::post($this->apiUrl . '/copyMessage', $payload);
    }

    public function getFile(string $fileId): ?array
    {
        $response = Http::post($this->apiUrl . '/getFile', ['file_id' => $fileId]);

        if (!$response->successful()) {
            Log::warning('Bale getFile failed', ['file_id' => $fileId, 'body' => $response->json('description')]);
            return null;
        }

        return $response->json('result');
    }

    public function downloadFile(string $filePath, string $destination): bool
    {
        // ⚠️ الگوی آدرس فایل بله رو دقیق از مستنداتشون چک کن؛ اینجا با همون الگوی api رو کپی کردم (tapi.bale.ai/file/bot<token>/...)
        $url = 'https://tapi.bale.ai/file/bot' . config('telegram.bale_bot_token') . '/' . $filePath;
        $stream = fopen($destination, 'w');
        $response = Http::timeout(120)->withOptions(['sink' => $stream])->get($url);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $response->successful();
    }
}
