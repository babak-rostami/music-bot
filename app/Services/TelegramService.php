<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = 'https://api.telegram.org/bot' . config('telegram.bot_token');
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

        return Http::post($this->apiUrl . '/sendMessage', $payload);
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

        return Http::post($this->apiUrl . '/editMessageText', $payload);
    }

    /**
     * یک پیام را از چت حذف می‌کند.
     */
    public function deleteMessage(int|string $chatId, int $messageId)
    {
        return Http::post($this->apiUrl . '/deleteMessage', [
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
            ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null)
    {
        return Http::post($this->apiUrl . '/answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
        ]);
    }

    public function sendAudioByFileId(int|string $chatId, string $fileId, string $title, string $performer)
    {
        return Http::post($this->apiUrl . '/sendAudio', [
            'chat_id'   => $chatId,
            'audio'     => $fileId,
            'title'     => $title,
            'performer' => $performer,
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
        $response = Http::post($this->apiUrl . '/getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        if (!$response->successful()) {
            Log::warning('Telegram getChatMember failed', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'status'  => $response->status(),
                'body'    => $response->json('description'),
            ]);
            return null;
        }

        return $response->json('result');
    }
}
