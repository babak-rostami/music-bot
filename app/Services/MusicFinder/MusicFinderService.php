<?php

namespace App\Services\MusicFinder;

use danog\MadelineProto\API;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ارتباط و تعامل با یک بات خارجی Music Finder از طریق MadelineProto.
 *
 * هر نمونه از این سرویس به یک بات مشخص متصل است و با استفاده از سشن
 * مشترک MadelineProto، پیام، لینک یا فایل را برای آن بات ارسال می‌کند
 * و پاسخ آن را دریافت و به MusicFinderResult تبدیل می‌کند.
 *
 * این کلاس برای هر بات خارجی یک Lock مستقل دارد تا درخواست‌های هم‌زمان
 * با یک بات باعث قاطی شدن پیام‌ها و پاسخ‌ها نشوند.
 *
 * چرخه کلی یک درخواست:
 *
 * 1. درخواست کاربر وارد MusicFinderService می‌شود.
 * 2. سرویس Lock مربوط به همان Music Finder را می‌گیرد.
 * 3. پیام، لینک یا فایل برای بات خارجی ارسال می‌شود.
 * 4. سرویس با استفاده از شناسه آخرین پیام موجود، نقطه شروع دریافت پاسخ را مشخص می‌کند.
 * 5. تا دریافت یک پیام جدید و معتبر از بات منتظر می‌ماند.
 * 6. پیام‌های میانی مانند «در حال جستجو»، استیکر و پیام‌های نامعتبر نادیده گرفته می‌شوند.
 * 7. پاسخ نهایی به MusicFinderResult تبدیل می‌شود.
 * 8. Lock آزاد می‌شود و درخواست بعدی می‌تواند با همان بات کار کند.
 *
 * Lock فقط تا پایان یک عملیات فعال با Music Finder نگه داشته می‌شود
 * و برای مدت زمانی که کاربر باید روی یک لیست آهنگ کلیک کند باقی نمی‌ماند.
 *
 * برای مثال، اگر درخواست کاربر شامل جستجوی متن باشد و Music Finder
 * یک لیست آهنگ برگرداند، Lock بلافاصله پس از دریافت لیست آزاد می‌شود.
 * اطلاعات لازم مانند callback_data و شناسه پیام Music Finder همراه نتیجه
 * برگردانده می‌شوند تا اگر کاربر چند دقیقه بعد یکی از آهنگ‌ها را انتخاب کرد،
 * درخواست جدیدی ایجاد شود و همان دکمه روی همان پیام قبلی انتخاب شود.
 *
 * در نتیجه، اگر کاربر A لیست آهنگ دریافت کند ولی هنوز انتخابی انجام ندهد،
 * کاربر B می‌تواند بلافاصله درخواست جدیدی ارسال کند.
 *
 * در درخواست‌هایی مانند ارسال ویدیو، Voice یا لینک که Music Finder
 * مستقیماً آهنگ نهایی را پیدا می‌کند، Lock تا پایان همان عملیات نگه داشته
 * می‌شود؛ یعنی تا زمانی که پاسخ نهایی دریافت یا عملیات با خطا/Timeout تمام شود.
 *
 * اگر درخواست دیگری در این مدت برسد، ابتدا برای گرفتن Lock منتظر می‌ماند.
 * مدت انتظار توسط block() مشخص می‌شود و انتظار بی‌نهایت نیست. اگر Lock
 * در این مدت آزاد نشود، درخواست جدید با نتیجه خطا برمی‌گردد.
 *
 * خطاهای عملیات نیز باعث باقی ماندن Lock نمی‌شوند؛ زیرا آزاد کردن Lock
 * در finally انجام می‌شود.
 *
 * محدودیت‌های Telegram مانند FLOOD_WAIT نیز در این کلاس مدیریت می‌شوند.
 * برای محدودیت‌های کوتاه، درخواست پس از صبر کردن مجدداً تلاش می‌شود و
 * برای محدودیت‌های طولانی‌تر یا خطاهای جدی Session، به ادمین هشدار داده
 * می‌شود.
 *
 * پاسخ نهایی Music Finder می‌تواند شامل موارد زیر باشد:
 * - فایل صوتی دانلودشده روی سرور
 * - پیام صوتی رله‌شده در کانال تلگرام، بدون دانلود فایل
 * - لیست دکمه‌های انتخاب آهنگ
 * - پیام خطا
 */
class MusicFinderService
{
    private const MAX_MEDIA_SIZE_MB = 20;
    private const NOT_FOUND_MESSAGE = "🔎 نتونستم پیداش کنم\nیه آهنگ دیگه رو امتحان کن";

    public function __construct(
        private readonly string $botKey,
        private readonly string $peerUsername,
    ) {}

    public function key(): string
    {
        return $this->botKey;
    }

    /**
     * یک متن یا لینک را برای بات Music Finder ارسال کرده و تا دریافت نتیجه
     * نهایی منتظر می‌ماند.
     *
     * این عملیات داخل Lock مربوط به همان بات انجام می‌شود تا در زمان انتظار
     * برای پاسخ، درخواست دیگری با همان بات وارد مکالمه نشود.
     *
     * قبل از ارسال، شناسه آخرین پیام موجود در گفت‌وگو ذخیره می‌شود تا هنگام
     * دریافت پاسخ فقط پیام‌هایی که بعد از شروع این درخواست ایجاد شده‌اند
     * بررسی شوند.
     *
     * اگر Music Finder یک لیست آهنگ برگرداند، نتیجه شامل دکمه‌ها و اطلاعات
     * لازم برای ادامه انتخاب کاربر خواهد بود و پس از برگرداندن نتیجه Lock
     * آزاد می‌شود.
     */
    public function sendText(string $forPlatform, string $text, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api) use ($forPlatform, $text) {
            $lastId = $this->lastMessageId($api);
            $this->guardedCall(fn() => $api->messages->sendMessage(['peer' => $this->peerUsername, 'message' => $text]));
            return $this->waitForReply($api, $forPlatform, $lastId);
        }, $onQueued);
    }

    /**
     * یک فایل صوتی، ویدیو یا Voice را برای شناسایی به بات Music Finder ارسال می‌کند.
     *
     * ابتدا وجود فایل و محدودیت حجم بررسی می‌شود. سپس درخواست داخل Lock مربوط
     * به همان بات اجرا شده و فایل برای Music Finder ارسال می‌شود.
     *
     * تا دریافت نتیجه نهایی، Lock حفظ می‌شود؛ زیرا در این مدت پاسخ Music Finder
     * متعلق به همین درخواست است و نباید درخواست دیگری با همان بات هم‌زمان شود.
     *
     * پیام‌های میانی نادیده گرفته می‌شوند و پس از دریافت فایل صوتی نهایی،
     * نتیجه به MusicFinderResult تبدیل شده و Lock آزاد می‌شود.
     */
    public function sendMedia(string $forPlatform, string $filePath, string $type, ?\Closure $onQueued = null): MusicFinderResult
    {
        if (!file_exists($filePath)) {
            return MusicFinderResult::error('فایل موردنظر پیدا نشد.');
        }

        $sizeMb = filesize($filePath) / 1024 / 1024;
        if ($sizeMb > self::MAX_MEDIA_SIZE_MB) {
            return MusicFinderResult::error(sprintf('رسانه خیلی زیاد! حجم نباید بیشتر از %d مگابایت باشد.', self::MAX_MEDIA_SIZE_MB));
        }

        return $this->withLock(function (API $api) use ($forPlatform, $filePath, $type) {
            $lastId = $this->lastMessageId($api);

            $attributes = match ($type) {
                'voice' => [['_' => 'documentAttributeAudio', 'voice' => true]],
                'video' => [['_' => 'documentAttributeVideo']],
                default => [['_' => 'documentAttributeAudio']],
            };
            $mimeType = match ($type) {
                'voice' => 'audio/ogg',
                'video' => 'video/mp4',
                default => 'audio/mpeg',
            };

            $this->guardedCall(fn() => $api->messages->sendMedia([
                'peer'  => $this->peerUsername,
                'media' => [
                    '_'          => 'inputMediaUploadedDocument',
                    'file'       => $api->upload($filePath),
                    'mime_type'  => $mimeType,
                    'attributes' => $attributes,
                ],
            ]));

            // audioOnly=true: هر پیام میانی (استیکر، عکس اطلاعات، متن) نادیده گرفته می‌شه تا خود آهنگ برسه
            return $this->waitForReply($api, $forPlatform, $lastId, audioOnly: true);
        }, $onQueued);
    }

    /**
     * یک Callback Button از پیام قبلی Music Finder را انتخاب می‌کند.
     *
     * این متد زمانی استفاده می‌شود که کاربر قبلاً یک لیست آهنگ دریافت کرده
     * و بعداً یکی از گزینه‌ها را انتخاب کرده است.
     *
     * برخلاف مرحله اول جستجو، سرویس در فاصله بین نمایش لیست و کلیک کاربر
     * Lock را نگه نمی‌دارد. وقتی کاربر کلیک می‌کند، این متد به‌عنوان یک
     * درخواست جدید Lock را می‌گیرد.
     *
     * شناسه پیام قبلی Music Finder و callback_data دکمه از نتیجه مرحله قبل
     * همراه درخواست نگه داشته می‌شوند تا دقیقاً همان دکمه روی همان پیام انتخاب شود؛
     * حتی اگر در فاصله بین این دو مرحله، درخواست‌های دیگری برای Music Finder
     * انجام شده باشند.
     *
     * خطای BOT_RESPONSE_TIMEOUT لزوماً به معنی ناموفق بودن کلیک نیست؛
     * ممکن است Music Finder کلیک را دریافت کرده باشد اما پاسخی برای Callback
     * ارسال نکرده باشد. در این حالت سرویس به انتظار برای نتیجه ادامه می‌دهد.
     */
    public function clickButton(string $forPlatform, int $sourceMessageId, string $callbackData, ?\Closure $onQueued = null): MusicFinderResult
    {
        return $this->withLock(function (API $api) use ($forPlatform, $sourceMessageId, $callbackData) {
            $lastId = $this->lastMessageId($api);

            $this->guardedCall(function () use ($api, $sourceMessageId, $callbackData) {
                try {
                    $api->messages->getBotCallbackAnswer([
                        'peer'   => $this->peerUsername,
                        'msg_id' => $sourceMessageId,
                        'data'   => $callbackData,
                    ]);
                } catch (\Throwable $e) {
                    // بعضی بات‌ها به کلیک دکمه answerCallbackQuery نمی‌زنن، ولی کلیک رو پردازش می‌کنن.
                    // تلگرام توی این حالت BOT_RESPONSE_TIMEOUT می‌ده، که شکست نیست: کلیک رسیده،
                    // پس ادامه می‌دیم و منتظر پیام آهنگ می‌مونیم.
                    if (str_contains($e->getMessage(), 'BOT_RESPONSE_TIMEOUT')) {
                        return null;
                    }

                    throw $e;
                }
            });

            return $this->waitForReply($api, $forPlatform, $lastId);
        }, $onQueued);
    }

    /**
     * فرایند شناسایی موسیقی از یک لینک را به‌صورت کامل اجرا می‌کند.
     *
     * لینک ابتدا برای Music Finder ارسال می‌شود. اگر بات دکمه‌ای مانند
     * «شناسایی موسیقی» برگرداند، سرویس آن دکمه را به‌صورت خودکار انتخاب می‌کند
     * و سپس منتظر نتیجه نهایی می‌ماند.
     *
     * تمام این مراحل در قالب یک عملیات فعال و تحت یک Lock انجام می‌شوند،
     * چون کاربر در این فرایند نیازی به انتخاب مرحله‌ای ندارد و Music Finder
     * باید همان درخواست را تا رسیدن به آهنگ نهایی ادامه دهد.
     *
     * در صورت نبودن دکمه موردنظر، نتیجه عدم یافتن آهنگ برگردانده می‌شود.
     */
    public function identifyFromLink(string $forPlatform, string $url, ?\Closure $onQueued = null): MusicFinderResult
    {
        $first = $this->sendText($forPlatform, $url, $onQueued);

        if ($first->type !== MusicFinderResult::TYPE_BUTTONS) {
            return $first; // یا مستقیم آهنگ/رله برگشت، یا خطا — کار تمومه
        }

        $identifyButton = $this->findButtonByText($first->buttons, 'شناسایی موسیقی');
        if (!$identifyButton) {
            return MusicFinderResult::error(self::NOT_FOUND_MESSAGE);
        }

        return $this->clickButton($forPlatform, $first->sourceMessageId, $identifyButton['callback_data']);
    }

    // ─────────────────────────────────────────────────

    /**
     * اولین دکمه‌ای را که متن آن شامل عبارت موردنظر باشد پیدا می‌کند.
     */
    private function findButtonByText(array $rows, string $needle): ?array
    {
        foreach ($rows as $row) {
            foreach ($row as $button) {
                if (str_contains($button['text'] ?? '', $needle)) {
                    return $button;
                }
            }
        }
        return null;
    }

    /**
     * یک عملیات فعال با Music Finder را با Lock اختصاصی همان بات اجرا می‌کند.
     *
     * هدف Lock جلوگیری از قاطی شدن درخواست‌ها و پاسخ‌های چند کاربر است؛
     * چون چند درخواست هم‌زمان از یک Session تلگرام و یک گفت‌وگو با Music Finder
     * استفاده می‌کنند.
     *
     * اگر Lock آزاد باشد، عملیات بلافاصله اجرا می‌شود.
     *
     * اگر درخواست دیگری در حال استفاده از Music Finder باشد، درخواست فعلی
     * در صورت وجود callback مربوط به وضعیت صف، آن callback را اجرا کرده و
     * سپس برای آزاد شدن Lock منتظر می‌ماند.
     *
     * انتظار برای Lock محدود است و در صورت تمام شدن زمان انتظار، درخواست
     * با نتیجه خطا برمی‌گردد و برای همیشه در حالت انتظار باقی نمی‌ماند.
     *
     * پس از گرفتن Lock، اتصال MadelineProto ایجاد شده و عملیات اجرا می‌شود.
     *
     * Lock در بلوک finally آزاد می‌شود؛ بنابراین چه عملیات با موفقیت تمام شود،
     * چه Exception یا خطای غیرمنتظره رخ دهد، Lock باقی نمی‌ماند.
     *
     * Lock به ازای botKey ساخته می‌شود؛ بنابراین فقط درخواست‌هایی که با یک
     * Music Finder یکسان کار می‌کنند نسبت به یکدیگر منتظر می‌مانند و
     * Music Finderهای مختلف می‌توانند هم‌زمان استفاده شوند.
     */
    private function withLock(\Closure $callback, ?\Closure $onQueued = null): MusicFinderResult
    {
        $lock = Cache::lock($this->lockKey(), 90);

        if (!$lock->get()) {
            if (is_callable($onQueued)) {
                $onQueued();
            }
            try {
                $lock->block(60);
            } catch (LockTimeoutException) {
                return MusicFinderResult::error('سرویس شلوغه، چند لحظه دیگه دوباره امتحان کن.');
            }
        }

        try {
            $api = MadelineConnection::get();
            return $callback($api);
        } catch (\Throwable $e) {
            Log::error('MusicFinderService failed', ['bot' => $this->botKey, 'error' => $e->getMessage()]);
            return MusicFinderResult::error('ارتباط با سرویس شناسایی آهنگ با خطا مواجه شد، دوباره امتحان کن.');
        } finally {
            $lock->release();
        }
    }

    private function lockKey(): string
    {
        return "music_finder_lock:{$this->botKey}";
    }

    /**
     * یک درخواست MadelineProto را با مدیریت محدودیت‌ها و خطاهای Session اجرا می‌کند.
     *
     * در صورت دریافت FLOOD_WAIT کوتاه، اجرای درخواست برای مدت لازم متوقف و
     * سپس مجدداً تلاش می‌شود. برای محدودیت‌های طولانی‌تر، به ادمین هشدار داده
     * شده و خطا به فراخواننده منتقل می‌شود.
     *
     * خطاهای مربوط به نامعتبر شدن یا لغو Session نیز به ادمین اطلاع داده می‌شوند.
     */
    private function guardedCall(\Closure $call, int $maxRetries = 1)
    {
        $this->respectMinimumSpacing();
        $attempt = 0;

        while (true) {
            try {
                return $call();
            } catch (\Throwable $e) {
                $message = $e->getMessage();

                if (preg_match('/FLOOD_WAIT_(\d+)/', $message, $m)) {
                    $wait = (int) $m[1];
                    if ($wait > 55) {
                        $this->alertAdmin("⛔️ [{$this->botKey}] تلگرام محدودیت نرخ گذاشته (FLOOD_WAIT {$wait} ثانیه).");
                        throw $e;
                    }
                    sleep($wait + 1);
                    if ($attempt++ < $maxRetries) {
                        continue;
                    }
                }

                if (preg_match('/AUTH_KEY_UNREGISTERED|USER_DEACTIVATED|SESSION_REVOKED|AUTH_KEY_PERM_EMPTY/', $message)) {
                    $this->alertAdmin("🚨 [{$this->botKey}] سشن MadelineProto قطع شده ({$message})! باید دوباره لاگین کنی.");
                }

                throw $e;
            }
        }
    }

    /**
     * بین درخواست‌های متوالی به هر بات حداقل فاصله تعیین‌شده را رعایت می‌کند.
     */
    private function respectMinimumSpacing(): void
    {
        $minGap = (float) config('services.music_finder_bot.min_gap_seconds', 3);
        $key = "music_finder_last_send_at:{$this->botKey}";
        $last = Cache::get($key);

        if ($last && (microtime(true) - $last) < $minGap) {
            usleep((int) (($minGap - (microtime(true) - $last)) * 1_000_000));
        }

        Cache::put($key, microtime(true), 60);
    }

    /**
     * یک هشدار را با جلوگیری از ارسال هشدارهای تکراری به ادمین ارسال می‌کند.
     */
    private function alertAdmin(string $message): void
    {
        $alertKey = "music_finder_alerted:{$this->botKey}";
        if (Cache::has($alertKey)) {
            return;
        }
        Cache::put($alertKey, true, now()->addMinutes(30));

        try {
            app(\App\Bot\NotificationService::class)->sendToAdmin($message, \App\Models\ActivityLog::LEVEL_ERROR);
        } catch (\Throwable $e) {
            Log::error('Failed to alert admin about MusicFinder issue', ['error' => $e->getMessage()]);
        }
    }


    /**
     * آخرین پیام موجود در گفت‌وگو با بات را به‌عنوان نقطه شروع دریافت پاسخ مشخص می‌کند.
     */
    private function lastMessageId(API $api): int
    {
        $history = $api->messages->getHistory(['peer' => $this->peerUsername, 'limit' => 1]);
        return $history['messages'][0]['id'] ?? 0;
    }

    // ─────────────────────────────────────────────────
    // دریافت و تفسیر پاسخ
    // ─────────────────────────────────────────────────

    /**
     * تا دریافت یک پاسخ معتبر از Music Finder منتظر می‌ماند.
     *
     * این متد منتظر یک پیام مشخص از سمت بات نمی‌ماند؛ بلکه تاریخچه گفت‌وگو
     * را به‌صورت دوره‌ای بررسی می‌کند و با استفاده از afterMessageId فقط
     * پیام‌هایی را در نظر می‌گیرد که بعد از شروع درخواست ایجاد شده‌اند.
     *
     * بنابراین اگر در فاصله بین درخواست‌های مختلف پیام‌های قدیمی یا پیام‌های
     * مربوط به درخواست قبلی در گفت‌وگو وجود داشته باشند، وارد نتیجه درخواست
     * فعلی نمی‌شوند.
     *
     * پیام‌های خروجی خود Session و پیام‌های میانی مانند وضعیت جستجو،
     * Sticker، عکس و فایل‌های غیرصوتی نامعتبر نادیده گرفته می‌شوند.
     *
     * در حالت audioOnly فقط زمانی نتیجه برگردانده می‌شود که پیام معتبر
     * شامل فایل صوتی باشد.
     *
     * بررسی پاسخ تا رسیدن به Timeout ادامه پیدا می‌کند. اگر در این مدت
     * پاسخ معتبر دریافت نشود، نتیجه خطا برگردانده می‌شود.
     */
    private function waitForReply(API $api, string $forPlatform, int $afterMessageId, bool $audioOnly = false): MusicFinderResult
    {
        $timeout = (int) config('services.music_finder_bot.timeout', 40);
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            try {
                $history = $api->messages->getHistory(['peer' => $this->peerUsername, 'limit' => 5]);
            } catch (\Throwable $e) {
                if (preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $m)) {
                    sleep(min((int) $m[1] + 1, 10));
                    continue;
                }
                throw $e;
            }

            $messages = $history['messages'] ?? [];

            foreach (array_reverse($messages) as $message) {
                $messageId = $message['id'] ?? 0;
                if ($messageId <= $afterMessageId) {
                    continue;
                }
                if ($message['out'] ?? false) {
                    continue;
                }
                if ($this->shouldSkipMessage($message, $audioOnly)) {
                    continue;
                }

                return $this->parseMessage($api, $forPlatform, $message);
            }

            usleep(700_000);
        }

        return MusicFinderResult::error('سرویس شناسایی آهنگ جواب نداد، دوباره امتحان کن.');
    }

    /**
     * مشخص می‌کند یک پیام باید به‌عنوان پیام میانی نادیده گرفته شود یا خیر.
     *
     * پیام‌هایی مانند Sticker، وضعیت جستجو، عکس‌های میانی و فایل‌های غیرصوتی
     * بدون دکمه از نتیجه نهایی حذف می‌شوند.
     */
    private function shouldSkipMessage(array $message, bool $audioOnly): bool
    {
        $text = $message['message'] ?? '';
        $mediaType = $message['media']['_'] ?? null;
        $document = $mediaType === 'messageMediaDocument' ? ($message['media']['document'] ?? null) : null;
        $isAudio = $document && $this->documentIsAudio($document);
        $isSticker = $document && $this->documentIsSticker($document);
        $hasButtons = !empty($this->extractCallbackButtons($message['reply_markup'] ?? null));

        if ($isSticker) {
            return true;
        }

        if (str_contains($text, '⏳') || str_contains($text, 'در حال')) {
            return true;
        }

        if ($mediaType === 'messageMediaPhoto') {
            return true; // عکس اطلاعات میانی
        }

        if ($mediaType === 'messageMediaDocument' && !$isAudio && !$hasButtons) {
            return true; // ویدیوی میانی بدون دکمه
        }

        if ($audioOnly && !$isAudio) {
            return true;
        }

        return false;
    }

    /**
     * بررسی می‌کند آیا Document دارای Attribute مربوط به فایل صوتی است.
     */
    private function documentIsAudio(array $document): bool
    {
        foreach ($document['attributes'] ?? [] as $attribute) {
            if (($attribute['_'] ?? null) === 'documentAttributeAudio') {
                return true;
            }
        }
        return false;
    }

    /**
     * بررسی می‌کند آیا Document دارای Attribute مربوط به Sticker است.
     */
    private function documentIsSticker(array $document): bool
    {
        foreach ($document['attributes'] ?? [] as $attribute) {
            if (($attribute['_'] ?? null) === 'documentAttributeSticker') {
                return true;
            }
        }
        return false;
    }

    /**
     * یک پیام خام دریافت‌شده از MadelineProto را به MusicFinderResult تبدیل می‌کند.
     *
     * اگر پیام شامل فایل صوتی باشد، ابتدا در مقصد تلگرام تلاش می‌شود فایل
     * مستقیماً از پیام Music Finder به کانال رله منتقل شود تا نیازی به دانلود
     * فایل روی سرور نباشد.
     *
     * اگر رله فعال نباشد یا انتقال مستقیم با خطا مواجه شود، فایل به‌صورت
     * محلی دانلود می‌شود و مسیر فایل در نتیجه برگردانده می‌شود.
     *
     * اگر پیام شامل دکمه‌های Callback باشد، دکمه‌ها همراه callback_data و
     * شناسه پیام منبع برگردانده می‌شوند تا کاربر بتواند بعداً یک گزینه را
     * انتخاب کند و درخواست انتخاب را از همان پیام ادامه دهیم.
     *
     * اگر پیام هیچ نتیجه قابل‌استفاده‌ای نداشته باشد، به‌جای برگرداندن متن
     * خام Music Finder، نتیجه عمومی عدم یافتن آهنگ برگردانده می‌شود.
     */
    private function parseMessage(API $api, string $forPlatform, array $message): MusicFinderResult
    {
        $mediaType = $message['media']['_'] ?? null;

        if ($mediaType === 'messageMediaDocument') {
            $document = $message['media']['document'] ?? null;

            if ($document && $this->documentIsAudio($document)) {
                $audioAttr = null;
                foreach ($document['attributes'] as $attribute) {
                    if (($attribute['_'] ?? null) === 'documentAttributeAudio') {
                        $audioAttr = $attribute;
                        break;
                    }
                }

                if ($forPlatform === 'telegram') {
                    $relay = $this->relayToTelegramChannel($api, $message, $audioAttr);
                    if ($relay) {
                        return $relay;
                    }
                    // اگه رله ناموفق بود یا تنظیم نشده، می‌ریم سراغ دانلود معمولی (زیر)
                }

                return $this->downloadAsLocalFile($api, $message['media'], $audioAttr);
            }
        }

        $inlineKeyboard = $this->extractCallbackButtons($message['reply_markup'] ?? null);
        if (!empty($inlineKeyboard)) {
            return MusicFinderResult::buttons(
                text: '🎵 این آهنگ‌ها رو پیدا کردم، یکی رو انتخاب کن:',
                buttons: $inlineKeyboard,
                sourceMessageId: $message['id'],
            );
        }

        // هر پاسخ متنی نهایی دیگه‌ای (نتونستم پیدا کنم، خطای اینستاگرام، هر چیز دیگه)
        // همیشه همین پیام تمیز خودمون رو برمی‌گردونیم — نه متن خام بات مقصد.
        return MusicFinderResult::error(self::NOT_FOUND_MESSAGE);
    }

    /**
     * فایل صوتی را از تلگرام در یک مسیر موقت دانلود کرده و نتیجه فایل محلی را برمی‌گرداند.
     */
    private function downloadAsLocalFile(API $api, array $media, ?array $audioAttr): MusicFinderResult
    {
        $tempDir = storage_path('app/private/music-finder/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $filePath = $api->downloadToDir($media, $tempDir);

        return MusicFinderResult::audio(
            filePath: $filePath,
            title: $audioAttr['title'] ?? null,
            performer: $audioAttr['performer'] ?? null,
        );
    }

    /**
     * پیام صوتی را بدون دانلود فایل، مستقیماً به کانال رله تلگرام منتقل می‌کند.
     *
     * این روش برای کاهش مصرف منابع سرور استفاده می‌شود.
     * در صورت غیرفعال بودن رله یا بروز خطا، null برگردانده می‌شود تا فراخواننده
     * بتواند به روش دانلود محلی برگردد.
     */
    private function relayToTelegramChannel(API $api, array $message, ?array $audioAttr): ?MusicFinderResult
    {
        $relayChannelId = config('services.music_finder_bot.relay_channel_id');
        if (!$relayChannelId) {
            return null;
        }

        try {
            $forwarded = $api->messages->forwardMessages([
                'from_peer'           => $this->peerUsername,
                'id'                  => [$message['id']],
                'to_peer'             => $relayChannelId,
                'drop_author'         => true,
                'drop_media_captions' => true,
                'random_id'           => [random_int(PHP_INT_MIN, PHP_INT_MAX)],
            ]);

            $newMessageId = $this->extractForwardedMessageId($forwarded);
            if (!$newMessageId) {
                Log::warning('Could not extract forwarded message id for relay', ['bot' => $this->botKey]);
                return null;
            }

            return MusicFinderResult::telegramRelay(
                relayChannelId: $relayChannelId,
                relayMessageId: $newMessageId,
                title: $audioAttr['title'] ?? null,
                performer: $audioAttr['performer'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::warning('Telegram relay forward failed, falling back to download', ['bot' => $this->botKey, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * شناسه پیام ایجادشده در کانال رله را از پاسخ خام MadelineProto استخراج می‌کند.
     */
    private function extractForwardedMessageId(array $updates): ?int
    {
        foreach ($updates['updates'] ?? [] as $update) {
            if (in_array($update['_'] ?? null, ['updateNewChannelMessage', 'updateNewMessage'], true)) {
                return $update['message']['id'] ?? null;
            }
        }
        return null;
    }

    /**
     * دکمه‌های Callback قابل‌استفاده از ساختار خام Inline Keyboard را استخراج می‌کند.
     *
     * دکمه‌هایی که Callback قابل‌پردازش ندارند نادیده گرفته می‌شوند.
     */
    private function extractCallbackButtons(?array $replyMarkup): array
    {
        if (!$replyMarkup || ($replyMarkup['_'] ?? null) !== 'replyInlineMarkup') {
            return [];
        }

        $buttons = [];
        foreach ($replyMarkup['rows'] ?? [] as $row) {
            $line = [];
            foreach ($row['buttons'] ?? [] as $button) {
                if (($button['_'] ?? null) !== 'keyboardButtonCallback') {
                    continue;
                }
                $line[] = [
                    'text'          => $button['text'] ?? '',
                    'callback_data' => $this->extractCallbackData($button['data'] ?? null),
                ];
            }
            if (!empty($line)) {
                $buttons[] = $line;
            }
        }
        return $buttons;
    }

    /**
     * callback_data را از فرمت‌های مختلفی که ممکن است MadelineProto برگرداند
     * به یک رشته قابل‌استفاده تبدیل می‌کند.
     */
    private function extractCallbackData(mixed $rawData): string
    {
        if (is_string($rawData)) {
            return $rawData;
        }
        if (is_object($rawData) && isset($rawData->bytes) && is_string($rawData->bytes)) {
            return $rawData->bytes;
        }
        if ($rawData instanceof \Stringable) {
            return (string) $rawData;
        }
        if (is_array($rawData) && isset($rawData['bytes'])) {
            return $rawData['bytes'];
        }
        Log::warning('Unexpected callback data shape from MadelineProto', ['type' => get_debug_type($rawData)]);
        return '';
    }
}
