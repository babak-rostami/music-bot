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

        if ($rawName === '' || mb_strpos($rawName, ' و ') !== false) {
            return null;
        }

        $name = $rawName;
        $slug = self::fa_slug($name);

        // ابتدا چک کن ببین آیا هنرمندی با این نام یا این اسلاگ وجود دارد؟
        $artist = Artist::where('name', $name)
            ->orWhere('slug', $slug)
            ->first();

        if (!$artist) {
            // اگر نبود، حالا بساز
            $artist = Artist::create([
                'name' => $name,
                'slug' => $slug,
                'bio' => null
            ]);
        }

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
