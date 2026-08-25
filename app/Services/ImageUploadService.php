<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\AutoEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;
use Throwable;

/**
 * سرویس آپلود و پردازش تصویر — سازگار با Intervention Image v4
 *
 * جریان کار: UploadedFile → decode → resize → encode (webp/jpg/png) → Storage::disk()
 * خروجی همیشه «مسیر نسبی» است (مثل covers/2026/07/ab12cd.webp) نه URL کامل.
 *
 * @see https://image.intervention.io/v4
 */
class ImageUploadService
{
    protected ImageManagerInterface $manager;

    /** @var array<string,mixed> */
    protected array $defaults = [
        'disk'          => null,     // null => config('images.disk')
        'width'         => 400,      // عرض نهایی (px) — ارتفاع خودکار
        'height'        => null,     // اگر همراه width بیاید، داخل کادر جا می‌شود (بدون کراپ)
        'format'        => 'webp',   // webp | jpg | png | null (حفظ فرمت اصلی)
        'quality'       => 90,       // 1..100 (روی png بی‌اثر است)
        'upsize'        => false,    // اجازه بزرگ‌کردن تصویر کوچک‌تر از width
        'crop'          => false,    // true + width + height => برش دقیق (cover)
        'orient'        => true,     // اصلاح چرخش بر اساس EXIF
        'strip'         => true,     // حذف متادیتای EXIF از خروجی (حجم کمتر + حریم خصوصی)
        'filename'      => null,     // نام دلخواه بدون پسوند
        'preserve_name' => false,    // نام اصلی فایل (slug) + رشته تصادفی
        'date_folders'  => true,     // زیرپوشه Y/m
        'keep_path'     => true,     // در replace: همان مسیر قبلی بازنویسی شود
        'unique'        => true,     // اگر فایل هم‌نام بود، -2 -3 ... اضافه شود
    ];

    public function __construct(?ImageManagerInterface $manager = null)
    {
        $this->manager = $manager ?? new ImageManager(
            config('images.driver', 'gd') === 'imagick' ? new ImagickDriver() : new GdDriver()
        );

        $this->defaults['disk'] = config('images.disk', 'covers');
    }

    // ---------------------------------------------------------------------
    // API عمومی
    // ---------------------------------------------------------------------

    /**
     * آپلود یک تصویر جدید.
     *
     * @param  string  $directory  پوشه مقصد روی دیسک، مثل covers
     * @param  array<string,mixed>  $options
     * @return string مسیر نسبی ذخیره‌شده (همین را در $song->cover_path بگذار)
     */
    public function upload(UploadedFile $file, string $directory = 'covers', array $options = []): string
    {
        $options = array_merge($this->defaults, $options);

        $binary = $this->process($file, $options);
        $path   = $this->buildPath($file, $directory, $options);

        $this->put($path, $binary, $options['disk']);

        return $path;
    }

    /**
     * جایگزینی تصویر روی «همان مسیر قبلی».
     * اگر مسیر قبلی وجود نداشته باشد یا پسوندش پشتیبانی نشود،
     * فایل جدید ساخته و فایل قدیمی حذف می‌شود.
     *
     * @param  array<string,mixed>  $options
     * @return string مسیر نهایی (معمولاً همان $oldPath)
     */
    public function replace(
        UploadedFile $file,
        ?string $oldPath,
        string $directory = 'covers',
        array $options = []
    ): string {
        $options = array_merge($this->defaults, $options);

        $isRemotePath = $oldPath && preg_match('/^http:\/\/|^https:\/\//i', $oldPath);

        if ($oldPath && ! $isRemotePath && $options['keep_path']) {
            $oldExtension = $this->normalizeFormat(pathinfo($oldPath, PATHINFO_EXTENSION));

            if ($oldExtension !== null) {
                $options['format'] = $oldExtension;

                $this->put($oldPath, $this->process($file, $options), $options['disk']);

                return $oldPath;
            }
        }

        $newPath = $this->upload($file, $directory, $options);

        if ($oldPath && ! $isRemotePath && $oldPath !== $newPath) {
            $this->delete($oldPath, $options['disk']);
        }

        return $newPath;
    }


    /** حذف یک فایل (بی‌صدا؛ خطا فقط لاگ می‌شود). */
    public function delete(?string $path, ?string $disk = null): bool
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://'])) {
            return false;
        }

        try {
            return $this->disk($disk)->delete($path);
        } catch (Throwable $e) {
            Log::warning('[ImageUploadService] delete failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** URL عمومی یک مسیر ذخیره‌شده. */
    public function url(?string $path, ?string $disk = null): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = $disk ?? $this->defaults['disk'];
        $base = config("filesystems.disks.{$disk}.url");

        return $base
            ? rtrim((string) $base, '/') . '/' . ltrim($path, '/')
            : $this->disk($disk)->url($path);
    }

    public function exists(?string $path, ?string $disk = null): bool
    {
        return $path ? $this->disk($disk)->exists($path) : false;
    }

    // ---------------------------------------------------------------------
    // داخلی
    // ---------------------------------------------------------------------

    /**
     * پردازش تصویر و برگرداندن باینری نهایی.
     *
     * @param  array<string,mixed>  $options
     */
    protected function process(UploadedFile $file, array $options): string
    {
        // v4: read() حذف شده — از decode()/decodePath() استفاده می‌شود
        $image = $this->manager->decodePath($file->getRealPath());

        if ($options['orient']) {
            $image = $image->orient();
        }

        $width  = $options['width'] ?: null;
        $height = $options['height'] ?: null;

        if ($width || $height) {
            $image = match (true) {
                // برش دقیق به width×height
                (bool) ($options['crop'] && $width && $height) => $image->cover($width, $height),
                // نسبت حفظ می‌شود، بزرگ‌نمایی مجاز
                (bool) $options['upsize'] => $image->scale($width, $height),
                // نسبت حفظ می‌شود، فقط کوچک می‌کند
                default => $image->scaleDown($width, $height),
            };
        }

        return $image->encode($this->encoder($options))->toString();
    }

    /**
     * ساخت Encoder مناسب.
     *
     * توجه v4:
     *  - PngEncoder اصلاً پارامتر quality ندارد (interlaced/indexed دارد)
     *  - WebpEncoder(quality, strip)
     *  - JpegEncoder(quality, progressive, strip)
     *
     * @param  array<string,mixed>  $options
     */
    protected function encoder(array $options): EncoderInterface
    {
        $quality = max(1, min(100, (int) $options['quality']));
        $strip   = (bool) $options['strip'];

        return match ($this->normalizeFormat($options['format'])) {
            'webp' => new WebpEncoder(quality: $quality, strip: $strip),
            'jpg'  => new JpegEncoder(quality: $quality, progressive: true, strip: $strip),
            'png'  => new PngEncoder(interlaced: false, indexed: false),
            // فرمت اصلی حفظ می‌شود
            default => new AutoEncoder(quality: $quality),
        };
    }

    /**
     * ساخت مسیر نسبی یکتا.
     *
     * @param  array<string,mixed>  $options
     */
    protected function buildPath(UploadedFile $file, string $directory, array $options): string
    {
        $extension = $this->normalizeFormat($options['format'])
            ?? strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $name = match (true) {
            (bool) $options['filename'] => $this->sanitizeFilename((string) $options['filename']),
            (bool) $options['preserve_name'] => $this->sanitizeFilename(
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            ) . '-' . Str::lower(Str::random(6)),
            default => Str::lower(Str::random(24)),
        };

        $segments = [];

        // پوشه می‌تواند خالی باشد (یعنی مستقیم در ریشه‌ی دیسک)
        if (trim($directory, '/') !== '') {
            $segments[] = trim($directory, '/');
        }

        if ($options['date_folders']) {
            $segments[] = date('Y');   // 2026
            $segments[] = date('m');   // 07
        }

        $prefix = implode('/', $segments);
        $path   = $prefix === '' ? "{$name}.{$extension}" : "{$prefix}/{$name}.{$extension}";

        // اگر نام دستی داده شده و فایل هم‌نام وجود دارد، پسوند عددی اضافه کن
        // تا کاور آهنگ دیگری بازنویسی نشود.
        if ($options['unique'] ?? true) {
            $path = $this->uniquePath($prefix, $name, $extension, $options['disk']);
        }

        return $path;
    }

    /**
     * تبدیل هر رشته (فارسی/انگلیسی) به نام فایل امن.
     * اگر بعد از slug چیزی باقی نماند (مثلاً عنوان تماماً فارسی بود)،
     * یک رشته تصادفی برمی‌گرداند تا نام فایل خالی نشود.
     */
    protected function sanitizeFilename(string $value): string
    {
        $slug = Str::slug($value);

        if ($slug === '') {
            // Str::slug کاراکترهای غیرلاتین را حذف می‌کند؛ برای عنوان فارسی
            // از ترنسلیتریشن ساده استفاده می‌کنیم و در نهایت fallback تصادفی.
            $slug = Str::slug(Str::ascii($value));
        }

        $slug = Str::limit($slug, 80, '');

        return $slug !== '' ? $slug : Str::lower(Str::random(16));
    }

    /** پیدا کردن مسیری که روی دیسک وجود ندارد: name.webp → name-2.webp → name-3.webp */
    protected function uniquePath(string $prefix, string $name, string $extension, ?string $disk): string
    {
        $build = fn(string $filename): string => $prefix === ''
            ? "{$filename}.{$extension}"
            : "{$prefix}/{$filename}.{$extension}";

        $path = $build($name);

        try {
            $counter = 2;

            while ($this->disk($disk)->fileExists($path) && $counter <= 100) {
                $path = $build("{$name}-{$counter}");
                $counter++;
            }
        } catch (Throwable $e) {
            // اگر دیسک اجازه exists نداد، برای جلوگیری از بازنویسی، تصادفی اضافه کن
            Log::warning('[ImageUploadService] uniqueness check failed', ['error' => $e->getMessage()]);

            $path = $build($name . '-' . Str::lower(Str::random(6)));
        }

        return $path;
    }

    protected function put(string $path, string $contents, ?string $disk = null): void
    {
        $filesystem = $this->disk($disk);
        $diskName   = $disk ?? $this->defaults['disk'];

        // بعضی سرورهای FTP نمی‌توانند مسیر تودرتو را خودکار بسازند
        // (خطای «creating parent directory failed»).
        // پس پوشه‌ها را قدم‌به‌قدم و دستی می‌سازیم.
        $this->ensureDirectory(dirname($path), $diskName);

        try {
            $result = $filesystem->put($path, $contents);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf(
                    'آپلود تصویر روی دیسک [%s] ناموفق بود: %s — %s',
                    $diskName,
                    $path,
                    $e->getMessage()
                ),
                previous: $e
            );
        }

        if ($result === false) {
            throw new RuntimeException(
                sprintf('آپلود تصویر روی دیسک [%s] ناموفق بود: %s', $diskName, $path)
            );
        }
    }

    /**
     * ساخت پوشه‌ها به‌صورت پله‌پله.
     *
     * چرا پله‌پله؟ چون درایور FTP برای ساخت مسیر تودرتو (`a/b/c`) به دستور
     * `MKD` بازگشتی نیاز دارد که خیلی از سرورهای FTP پشتیبانی نمی‌کنند.
     * با ساخت `a` سپس `a/b` سپس `a/b/c` این مشکل حل می‌شود.
     */
    protected function ensureDirectory(string $directory, string $disk): void
    {
        $directory = trim($directory, '/.');

        if ($directory === '') {
            return;
        }

        $filesystem = $this->disk($disk);
        $current    = '';

        foreach (explode('/', $directory) as $segment) {
            $current = $current === '' ? $segment : "{$current}/{$segment}";

            try {
                if ($filesystem->directoryExists($current)) {
                    continue;
                }

                $filesystem->makeDirectory($current);
            } catch (Throwable $e) {
                // ممکن است پوشه از قبل باشد یا سرور اجازه‌ی chmod ندهد.
                // اگر واقعاً مشکل جدی باشد، put() بعدی خطا می‌دهد.
                Log::debug('[ImageUploadService] makeDirectory skipped', [
                    'directory' => $current,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    protected function disk(?string $disk = null): Filesystem
    {
        return Storage::disk($disk ?? $this->defaults['disk']);
    }

    /** نرمال‌سازی نام فرمت؛ برای فرمت ناشناخته null برمی‌گرداند. */
    protected function normalizeFormat(?string $format): ?string
    {
        return match (strtolower(trim((string) $format))) {
            'webp'        => 'webp',
            'jpg', 'jpeg' => 'jpg',
            'png'         => 'png',
            default       => null,
        };
    }
}
