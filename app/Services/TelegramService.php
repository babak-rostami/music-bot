<?php

// app/Services/TelegramService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $apiUrl;

    public function __construct()
    {
        // بهتره توکن رو در فایل کانفیگ بذاریم ولی برای سادگی فعلا اینجاست
        $this->apiUrl = 'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN');
    }

    public function sendMessage(int|string $chatId, string $text, ?array $inlineKeyboard = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard
            ]);
        }

        return Http::post($this->apiUrl . '/sendMessage', $payload);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $inlineKeyboard = null)
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ];

        if ($inlineKeyboard !== null) { // مهم: حتی اگر آرایه خالی باشد باید ارسال شود
            $payload['reply_markup'] = json_encode([
                'inline_keyboard' => $inlineKeyboard
            ]);
        }

        return Http::post($this->apiUrl . '/editMessageText', $payload);
    }

    /**
     * یک پیام را از چت حذف می‌کند.
     *
     * @param int|string $chatId
     * @param int $messageId
     * @return \Illuminate\Http\Client\Response
     */
    public function deleteMessage(int|string $chatId, int $messageId)
    {
        return Http::post($this->apiUrl . '/deleteMessage', [
            'chat_id' => $chatId,
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
            'text' => $text,
        ]);
    }
}
