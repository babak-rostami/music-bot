<?php

namespace App\Bot\Telegram\Contracts;


interface CommandHandlerInterface
{
    /**
     * بررسی می‌کند که آیا این هندلر باید این پیام را پردازش کند یا نه.
     *
     * @param string $text
     * @return bool
     */
    public function canHandle(string $text): bool;

    /**
     * منطق اصلی کامند را اجرا می‌کند.
     *
     * @param array $message
     * @return void
     */
    public function handle(array $message): void;
}
