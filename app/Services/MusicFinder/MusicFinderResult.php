<?php

namespace App\Services\MusicFinder;

class MusicFinderResult
{
    public const TYPE_AUDIO   = 'audio';
    public const TYPE_BUTTONS = 'buttons';
    public const TYPE_ERROR   = 'error';

    private function __construct(
        public readonly string $type,
        public readonly ?string $filePath = null,
        public readonly ?string $title = null,
        public readonly ?string $performer = null,
        public readonly ?string $text = null,
        public readonly array $buttons = [],
        public readonly ?int $sourceMessageId = null,
    ) {}

    public static function audio(string $filePath, ?string $title, ?string $performer): self
    {
        return new self(type: self::TYPE_AUDIO, filePath: $filePath, title: $title, performer: $performer);
    }

    public static function buttons(string $text, array $buttons, int $sourceMessageId): self
    {
        return new self(type: self::TYPE_BUTTONS, text: $text, buttons: $buttons, sourceMessageId: $sourceMessageId);
    }

    public static function error(string $text): self
    {
        return new self(type: self::TYPE_ERROR, text: $text);
    }
}
