<?php

namespace App\Helpers;

use App\Models\Song;

class SongSlugHelper
{
    /**
     * ساخت اسلاگ از روی عنوان
     */
    private static function create(string $title): string
    {
        // 1) تبدیل حروف عربی به فارسی
        $title = str_replace(
            ['ي', 'ك', 'ة', 'ۀ'],
            ['ی', 'ک', 'ه', 'ه'],
            $title
        );

        // 2) حذف نیم‌فاصله (ZWNJ) و تبدیل به فاصله معمولی
        $title = str_replace("\u{200C}", ' ', $title);

        // 3) حذف کاراکترهای غیرمجاز
        // فقط حروف، اعداد، فاصله و خط تیره نگه داشته می‌شود.
        $title = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $title);

        // 4) حذف فاصله‌های اضافی
        $title = trim($title);
        $title = preg_replace('/\s+/u', ' ', $title);

        // 5) تبدیل فاصله‌ها به خط تیره
        $title = str_replace(' ', '-', $title);

        // 6) حذف خط تیره‌های پشت‌سرهم و خط تیره‌های ابتدا و انتها
        $title = preg_replace('/-+/', '-', $title);
        $title = trim($title, '-');

        // 7) مقدار پیش‌فرض برای زمانی که همه‌چیز حذف شده باشد
        if ($title === '') {
            $title = 'song';
        }

        return $title;
    }

    /**
     * ساخت اسلاگ یکتا.
     */
    public static function make(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = self::create($title);

        // اگر خود اسلاگ پایه تکراری نبود، همان را برمی‌گردانیم
        if (! self::slugExists($baseSlug, $ignoreId)) {
            return $baseSlug;
        }

        // در غیر این صورت -1 ، -2 ، -3 ... امتحان می‌کنیم
        $counter = 1;

        do {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        } while (self::slugExists($slug, $ignoreId));

        return $slug;
    }

    /**
     * بررسی وجود اسلاگ در جدول songs
     */
    protected static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Song::where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
