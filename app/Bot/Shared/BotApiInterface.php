<?php

namespace App\Bot\Shared;

interface BotApiInterface
{
    public function platform(): string; // 'telegram' | 'bale'

    public function sendMessage(int|string $chatId, string $text, ?array $inlineKeyboard = null, ?string $parseMode = null);

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $inlineKeyboard = null, ?string $parseMode = null);

    public function deleteMessage(int|string $chatId, int $messageId);

    public function sendAudio(int|string $chatId, string $filePath, string $fileName, string $title, string $performer);

    public function sendAudioByFileId(int|string $chatId, string $fileId, string $title, string $performer);

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null);

    public function uploadAudioToCacheChannel(string $filePath, $song): ?array;

    public function getChatMember(string|int $chatId, int $userId): ?array;

    public function forwardMessage(int|string $fromChatId, int $messageId, int|string|null $toChatId = null);

    public function copyMessage(int|string $fromChatId, int $messageId, int|string|null $toChatId = null);

    public function getFile(string $fileId): ?array;

    public function downloadFile(string $filePath, string $destination): bool;
}
