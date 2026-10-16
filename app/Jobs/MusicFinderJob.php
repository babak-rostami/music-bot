<?php

namespace App\Jobs;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Shared\BotApiInterface;
use App\Bot\Telegram\Services\TelegramService;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderBotRegistry;
use App\Services\MusicFinder\MusicFinderResult;
use App\Services\MusicFinder\MusicFinderUsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * پردازش جستجوی پیشرفته Music Finder در پس‌زمینه با استفاده از صف.
 *
 * این Job بعد از دریافت درخواست کاربر توسط Webhook اجرا می‌شود تا عملیات
 * زمان‌بر Music Finder خارج از چرخه پاسخ HTTP انجام شود و Webhook بتواند
 * سریع پاسخ دهد.
 *
 * بر اساس mode، یکی از عملیات زیر انجام می‌شود:
 *
 * - text:
 *   ارسال متن جستجو به Music Finder
 *
 * - link:
 *   ارسال لینک و شناسایی خودکار موسیقی
 *
 * - media:
 *   ارسال فایل صوتی، Voice یا Video برای شناسایی
 *
 * - select:
 *   انتخاب یکی از دکمه‌های قبلی Music Finder با استفاده از
 *   sourceMessageId و callbackData
 *
 * بات Music Finder نیز بر اساس نوع درخواست انتخاب می‌شود:
 *
 * - در mode=select همان باتی استفاده می‌شود که لیست آهنگ را قبلاً
 *   ایجاد کرده است؛ چون callback_data متعلق به همان بات و همان پیام است.
 *
 * - در سایر حالت‌ها بات با Round Robin از MusicFinderBotRegistry انتخاب می‌شود.
 *
 * پس از دریافت نتیجه، این Job آن را به یکی از مسیرهای زیر تحویل می‌دهد:
 *
 * - ارسال فایل صوتی
 * - کپی مستقیم آهنگ از کانال Relay در Telegram
 * - نمایش دکمه‌های انتخاب به کاربر
 * - نمایش پیام خطا
 *
 * در صورت تحویل موفق آهنگ از Music Finder خارجی، مصرف سهمیه کاربر نیز
 * ثبت می‌شود و در صورت وجود ظرفیت باقی‌مانده، مقدار سهمیه به کاربر نمایش داده
 * می‌شود.
 *
 * فایل‌های موقت ورودی و خروجی نیز پس از پایان عملیات پاکسازی می‌شوند.
 *
 * آزاد کردن رزرو کاربر و پاکسازی فایل ورودی داخل finally انجام می‌شود تا
 * حتی در صورت خطا یا Exception نیز این وضعیت‌ها باقی نمانند.
 */
class MusicFinderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    /**
     * اطلاعات لازم برای اجرای یک درخواست Music Finder را در Job ذخیره می‌کند.
     *
     * mode مشخص می‌کند چه نوع عملیاتی باید انجام شود.
     *  در حالت select، botKey، sourceMessageId و callbackData برای ادامه دادن انتخاب کاربر
     * از پیام قبلی Music Finder استفاده می‌شوند.
     *
     * برای mode=media، دانلود فایل عمداً قبل از Dispatch شدن Job انجام نمی‌شود
     * (ممکنه بسته به حجم فایل چند ثانیه تا چند دقیقه طول بکشه و وب‌هوک ورودی رو
     * معطل نگه داره)؛ به‌جاش mediaFileId (file_id خام تلگرام/بله) پاس داده می‌شود
     * و خود Job، داخل صف، دانلودش می‌کند و mediaPath را پر می‌کند.
     */
    public function __construct(
        public string $platform,
        public int|string $chatId,
        public string $mode,               // text | link | media | select
        public ?string $text = null,
        public ?string $mediaPath = null,
        public ?string $mediaType = null,
        public ?int $sourceMessageId = null,
        public ?string $callbackData = null,
        public ?string $originalQuery = null,
        public ?string $botKey = null,     // فقط برای mode=select — کدوم بات دکمه رو نشون داده بود
        public ?string $mediaFileId = null, // فقط برای mode=media — file_id خامی که باید داخل Job دانلود بشه
    ) {}

    /**
     * اجرای اصلی Job و هماهنگی کل فرآیند جستجوی پیشرفته.
     *
     * ابتدا Service مربوط به پلتفرم کاربر انتخاب می‌شود و سپس Music Finder
     * مناسب انتخاب می‌شود.
     *
     * برای درخواست‌های جدید، بات با Round Robin انتخاب می‌شود؛
     * اما در حالت select همان باتی انتخاب می‌شود که دکمه انتخاب را قبلاً ایجاد کرده است.
     *
     * سپس بر اساس mode عملیات مناسب روی MusicFinderService اجرا شده و نتیجه
     * دریافت‌شده برای کاربر ارسال می‌شود.
     *
     * در پایان، چه عملیات موفق باشد و چه با خطا مواجه شود:
     *
     * - فایل ورودی موقت حذف می‌شود.
     * - رزرو فعال کاربر آزاد می‌شود.
     *
     * این دو عملیات در finally قرار گرفته‌اند تا Exception باعث باقی ماندن
     * وضعیت Busy یا فایل موقت نشود.
     */
    public function handle(): void
    {
        $service = $this->platform === 'bale' ? app(BaleService::class) : app(TelegramService::class);

        try {
            // برای mode=media، فایل عمداً قبل از Dispatch دانلود نشده (دلیلش توی
            // داکیومنت سازنده‌ی کلاس توضیح داده شده) — اینجا، داخل صف، دانلودش می‌کنیم.
            if ($this->mode === 'media' && $this->mediaFileId && !$this->mediaPath) {
                $this->mediaPath = $this->downloadMediaFile($service);

                if (!$this->mediaPath) {
                    $service->sendMessage($this->chatId, '❌ دریافت فایل ناموفق بود.');
                    return;
                }
            }

            // برای «select» حتماً باید همون باتی که دکمه رو نشون داده بود صدا بزنیم؛
            // برای بقیه‌ی حالت‌ها، یه بات جدید به‌صورت یکی‌درمیون انتخاب می‌شه.
            $finder = $this->mode === 'select'
                ? MusicFinderBotRegistry::get($this->botKey ?? '')
                : MusicFinderBotRegistry::next();

            // اگه یه نفر دیگه در حال استفاده از سرویس باشه بهش میگه صبر کن
            $onQueued = fn() => $service->sendMessage(
                $this->chatId,
                '⏳ سرویس مشغوله، چند لحظه صبر کن تا نوبتت برسه...'
            );

            $result = match ($this->mode) {
                'text'   => $finder->sendText($this->platform, $this->text, $onQueued),
                'link'   => $finder->identifyFromLink($this->platform, $this->text, $onQueued),
                'media'  => $finder->sendMedia($this->platform, $this->mediaPath, $this->mediaType, $onQueued),
                'select' => $finder->clickButton($this->platform, $this->sourceMessageId, $this->callbackData, $onQueued),
            };

            $this->deliver($service, $finder->key(), $result);
        } finally {
            if ($this->mediaPath && file_exists($this->mediaPath)) {
                @unlink($this->mediaPath);
            }
            MusicFinderAccess::release($this->platform, $this->chatId);
        }
    }

    /**
     * فایل صوتی/ویدیویی/Voice ارسالی کاربر را با file_id از تلگرام/بله دانلود
     * و مسیر محلی فایل را برمی‌گرداند.
     *
     * این دانلود عمداً داخل Job (توی صف) انجام می‌شود، نه قبل از Dispatch شدن
     * Job، تا پاسخ وب‌هوک ورودی معطل دانلود فایل نماند — توضیح کامل‌ترش توی
     * داکیومنت سازنده‌ی کلاس هست.
     *
     * در صورت شکست دانلود، فایل نیمه‌کاره و پوشه‌ی موقت (در صورت وجود) هم
     * پاک می‌شوند تا روی دیسک باقی نمانند.
     */
    private function downloadMediaFile(BotApiInterface $service): ?string
    {
        $file = $service->getFile($this->mediaFileId);
        if (!$file || empty($file['file_path'])) {
            return null;
        }

        $tempDir = storage_path('app/private/music-finder-incoming/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ext = match ($this->mediaType) {
            'voice' => 'ogg',
            'video' => 'mp4',
            default => 'mp3',
        };
        $localPath = "{$tempDir}/incoming.{$ext}";

        if ($service->downloadFile($file['file_path'], $localPath)) {
            return $localPath;
        }

        if (file_exists($localPath)) {
            @unlink($localPath);
        }
        @rmdir($tempDir);

        return null;
    }

    /**
     * نتیجه دریافت‌شده از Music Finder را به مسیر تحویل مناسب هدایت می‌کند.
     *
     * بر اساس نوع MusicFinderResult:
     *
     * - audio و telegram_relay → ارسال آهنگ
     * - buttons → ساخت و ارسال دکمه‌های انتخاب
     * - error → ارسال پیام خطا
     *
     * این متد فقط وظیفه انتخاب مسیر را دارد و جزئیات هر نوع تحویل در متد
     * مربوط به همان نوع انجام می‌شود.
     */
    private function deliver($service, string $botKey, MusicFinderResult $result): void
    {
        match ($result->type) {
            MusicFinderResult::TYPE_AUDIO,
            MusicFinderResult::TYPE_TELEGRAM_RELAY => $this->deliverAudio($service, $result),
            MusicFinderResult::TYPE_BUTTONS        => $this->deliverButtons($service, $botKey, $result),
            MusicFinderResult::TYPE_ERROR          => $service->sendMessage($this->chatId, $result->text),
        };
    }

    /**
     * آهنگ پیدا‌شده را به کاربر تحویل می‌دهد.
     *
     * اگر نتیجه از نوع telegram_relay باشد، فایل مستقیماً از کانال Relay
     * به کاربر Copy می‌شود و نیازی به دانلود و آپلود مجدد فایل نیست.
     *
     * در غیر این صورت، فایل موقت ذخیره‌شده روی سرور مستقیماً برای کاربر
     * ارسال می‌شود.
     *
     * پس از تحویل موفق:
     *
     * - مصرف سهمیه جستجوی خارجی ثبت می‌شود.
     * - در صورت باقی‌ماندن سهمیه، پیام سهمیه برای کاربر ارسال می‌شود.
     * - آهنگ برای بررسی و ثبت احتمالی در دیتابیس به کانال ادمین ارسال می‌شود.
     * - فایل محلی نتیجه در صورت وجود حذف می‌شود.
     */
    private function deliverAudio($service, MusicFinderResult $result): void
    {
        $displayTitle = trim(($result->title ?? '') . ($result->performer ? ' - ' . $result->performer : ''));
        $caption = $displayTitle !== '' ? "🎧 {$displayTitle}" : null;

        if ($result->type === MusicFinderResult::TYPE_TELEGRAM_RELAY) {
            // بدون دانلود/آپلود — مستقیم از کانال رله سمت سرورهای تلگرام به کاربر کپی می‌شه
            $service->copyMessage($result->relayChannelId, $result->relayMessageId, $this->chatId, $caption);
        } else {
            $fileName = ($result->title ?? 'آهنگ') . '.mp3';
            $service->sendAudio($this->chatId, $result->filePath, $fileName, $result->title ?? 'آهنگ شناسایی‌شده', $result->performer ?? '');
        }

        $displayText = $displayTitle !== '' ? $displayTitle : ($this->originalQuery ?? $this->text);
        MusicFinderUsageService::recordExternalDelivery($this->platform, $this->chatId, $displayText);

        $quotaMessage = MusicFinderUsageService::remainingQuotaMessage($this->platform, $this->chatId);
        if ($quotaMessage) {
            $service->sendMessage($this->chatId, $quotaMessage);
        }

        $this->notifyAdmin($result);

        if ($result->type === MusicFinderResult::TYPE_AUDIO) {
            @unlink($result->filePath);
        }
    }

    /**
     * دکمه‌های دریافت‌شده از Music Finder را برای کاربر آماده و ارسال می‌کند.
     *
     * callback_data واقعی Music Finder مستقیماً برای کاربر ارسال نمی‌شود.
     * برای هر دکمه یک Token کوتاه و تصادفی ساخته شده و callback_data واقعی
     * به‌صورت موقت در Cache ذخیره می‌شود.
     *
     * Token به همراه شناسه پیام Music Finder و botKey ذخیره می‌شود تا وقتی
     * کاربر بعداً روی دکمه کلیک کرد، سیستم بتواند:
     *
     * - پیام اصلی Music Finder را پیدا کند.
     * - callback_data واقعی را بازیابی کند.
     * - همان Music Finder را دوباره انتخاب کند.
     * - همان دکمه را روی پیام قبلی اجرا کند.
     *
     * اطلاعات دکمه‌ها به مدت محدود در Cache نگهداری می‌شوند تا از باقی ماندن
     * داده‌های موقت برای مدت طولانی جلوگیری شود.
     */
    private function deliverButtons($service, string $botKey, MusicFinderResult $result): void
    {
        $buttons = [];
        foreach ($result->buttons as $row) {
            $line = [];
            foreach ($row as $button) {
                $token = Str::random(12);
                Cache::put(
                    "music_finder_btn:{$token}",
                    [
                        'message_id' => $result->sourceMessageId,
                        'data'       => $button['callback_data'],
                        'bot_key'    => $botKey,
                    ],
                    now()->addMinutes(10)
                );
                $line[] = ['text' => $button['text'], 'callback_data' => "mf_pick_{$token}"];
            }
            $buttons[] = $line;
        }

        $service->sendMessage($this->chatId, $result->text ?: 'یکی رو انتخاب کن:', $buttons);
    }

    /**
     * آهنگ شناسایی‌شده را برای بررسی ادمین ارسال می‌کند.
     *
     * هدف این اطلاع‌رسانی این است که آهنگ‌های مناسب بتوانند توسط ادمین
     * بررسی و در صورت نیاز با /new-song در دیتابیس ثبت شوند.
     *
     * اگر نتیجه از نوع Telegram Relay باشد، پیام مستقیماً از کانال Relay
     * برای کانال ادمین Copy می‌شود.
     *
     * در غیر این صورت، فایل محلی آهنگ برای کانال ادمین ارسال می‌شود.
     *
     * خطا در اطلاع‌رسانی به ادمین نباید باعث شکست جستجوی کاربر شود؛ بنابراین
     * Exception در این بخش گرفته و فقط در Log ثبت می‌شود.
     */
    private function notifyAdmin(MusicFinderResult $result): void
    {
        $channelId = config('telegram.notify_channel_id');
        if (!$channelId) {
            return;
        }

        try {
            $telegram = app(TelegramService::class);
            // $caption = "🎯 آهنگ شناسایی‌شده برای کاربر\n\n"
            //     . "🤖 پلتفرم: " . ($this->platform === 'bale' ? 'بله' : 'تلگرام') . "\n"
            //     . "👤 chat_id: {$this->chatId}\n"
            //     . "🔍 درخواست اصلی: " . ($this->originalQuery ?? $this->text ?? '-') . "\n\n"
            //     . "اگه خوب بود، با /new-song ثبتش کن توی دیتابیس.";

            if ($result->type === MusicFinderResult::TYPE_TELEGRAM_RELAY) {
                $telegram->copyMessage($result->relayChannelId, $result->relayMessageId, $channelId);
            } elseif ($result->filePath && file_exists($result->filePath)) {
                $telegram->sendAudio($channelId, $result->filePath, ($result->title ?? 'song') . '.mp3', $result->title ?? '-', $result->performer ?? '-');
            }

            // $telegram->sendMessage($channelId, $caption);
        } catch (\Throwable $e) {
            Log::warning('Failed to notify admin about identified song', ['error' => $e->getMessage()]);
        }
    }
}
