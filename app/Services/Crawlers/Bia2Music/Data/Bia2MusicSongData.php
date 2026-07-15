<?php

namespace App\Services\Crawlers\Bia2Music\Data;

class Bia2MusicSongData
{
    public function __construct(
        public string $title,
        public string $artistName,
        public string $sourceUrl,
        public ?string $lyrics = null,
        public ?string $coverPath = null,
        public ?string $downloadLink128 = null,
        public ?string $downloadLink320 = null,
    ) {}
}
