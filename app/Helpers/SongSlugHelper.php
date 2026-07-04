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
        //    فقط حروف، اعداد، فاصله و خط تیره نگه داشته می‌شود.
        //    نکته مهم: این کار باید قبل از حذف حروف اضافه انجام شود تا علائم نگارشی
        //    چسبیده به کلمات (مثل «و،» یا «(در») جلوی تشخیص درست حروف ربط را نگیرند.
        $title = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $title);

        // 4) حذف حروف ربط / کلمات اضافه
        $stopWords = [
            'و',
            'در',
            'از',
            'به',
            'با',
            'برای',
            'بر',
            'تا',
            'یا',
            'که',
            'این',
            'آن',
            'یک',
            'روی',
            'زیر',
            'اگر',
            'اما',
            'هم',
            'همه',
            'هر',
            'بعد',
            'قبل',
            'بدون',
            'مثل',
            'مانند',
            'بین',
            'داخل',
            'خارج',
            'توی',
            'تو',
        ];

        $words = preg_split('/\s+/u', trim($title)) ?: [];

        $words = array_filter($words, function ($word) use ($stopWords) {
            return $word !== '' && ! in_array($word, $stopWords, true);
        });

        // 5) اتصال کلمات با خط تیره
        $title = implode('-', $words);

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
     *
     * اگر اسلاگ از قبل داخل جدول songs وجود داشت، به ترتیب
     * «-1»، «-2»، «-3» ... به انتهای اسلاگ اضافه می‌کند تا به یک اسلاگ آزاد برسد.
     *
     * مثال:
     *   ali         → موجود است → ali-1
     *   ali-1       → موجود است → ali-2
     *   ali-2       → موجود است → ali-3   ← اگر ali-2 بود، ali-3 می‌سازد
     *
     * @param  string   $title     عنوان آهنگ
     * @param  int|null $ignoreId  آیدی رکوردی که هنگام ویرایش نباید در نظر گرفته شود
     *                             (تا رکورد با خودش تداخل پیدا نکند)
     * @return string
     */
    public static function make(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = self::create($title);

        // اگر خود اسلاگ پایه تکراری نبود، همان را برمی‌گردانیم
        if (! self::slugExists($baseSlug, $ignoreId)) {
            return $baseSlug;
        }

        // در غیر این صورت -1 ، -2 ، -3 ... امتحان می‌کنیم تا به یک اسلاگ آزاد برسیم
        $counter = 1;
        do {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        } while (self::slugExists($slug, $ignoreId));

        return $slug;
    }

    /**
     * بررسی اینکه آیا اسلاگِ داده‌شده قبلاً در جدول songs ثبت شده یا خیر.
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
