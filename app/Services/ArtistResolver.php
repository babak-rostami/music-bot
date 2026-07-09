<?php

namespace App\Services;

use App\Models\Artist;
use App\Models\Song;

class ArtistResolver
{
    /**
     * اگر artist_name فقط یک خواننده داشت، میسازدش و به آهنگ وصل میکند.
     * اگر " و " داشت، null برمیگرداند و هیچ کاری نمیکند.
     * کاملا idempotent است.
     */
    public static function attachSingleArtist(Song $song): ?Artist
    {
        $rawName = trim(preg_replace('/\s+/u', ' ', $song->artist_name ?? ''));

        if ($rawName === '') {
            return null;
        }

        // اگر " و " داشت یعنی چند خواننده است، بیخیال
        if (mb_strpos($rawName, ' و ') !== false) {
            return null;
        }

        $name = $rawName;
        $slug = self::fa_slug($name);

        $artist = Artist::firstOrCreate(
            ['name' => $name],
            ['slug' => $slug, 'bio' => null]
        );

        // syncWithoutDetaching = اگر قبلا وصل بود دوباره وصل نمیکند
        $song->artists()->syncWithoutDetaching([$artist->id]);

        return $artist;
    }

    /**
     * اسلاگ فارسی
     * مثلا: محسن چاوشی => محسن-چاوشی
     */
    public static function fa_slug(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/u', '-', $text);
        $text = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text);
        $text = preg_replace('/\-+/u', '-', $text);
        return trim($text, '-');
    }
}
