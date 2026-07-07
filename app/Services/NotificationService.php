<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\BotUser;
use Illuminate\Support\Facades\Log;

/**
 * ─────────────────────────────────────────────────────────────────────
 *  سرویس نوتیفیکیشن جامع
 * ─────────────────────────────────────────────────────────────────────
 *
 *  این سرویس دو کار انجام می‌دهد:
 *
 *  ۱) ثبت در دیتابیس  → جدول activity_logs (تاریخچه کامل همه رویدادها)
 *  ۲) ارسال به ادمین   → کانال/چت ادمین تلگرام (یا بله)
 *
 *  متدهای آماده برای رویدادهای رایج:
 *  ────────────────────────────────────────────────
 *  • messageReceived()   → پیام جدید از کاربر
 *  • songSent()          → آهنگ با موفقیت ارسال شد
 *  • songSendFailed()    → ارسال آهنگ ناموفق بود
 *  • error()             → خطای عمومی
 *  • userJoined()        → کاربر جدید عضو شد
 *  • userBlocked()       → کاربر ربات را بلاک کرد
 *  • songRequest()       → درخواست آهنگ جدید
 *  • notify()            → متد عمومی برای هر رویداد دلخواه
 *
 *  ⚠️ نکته طراحی:
 *  متدهای خاص (messageReceived و...) مستقیماً log() + sendToTelegramAdmin()
 *  را صدا می‌زنند تا از ارسال دوبل جلوگیری شود.
 *  notify() برای موارد دلخواه و ساده استفاده می‌شود.
 */
class NotificationService
{
    public function __construct(
        protected TelegramService $telegram,
        protected BaleService $bale,
    ) {}

    // ═══════════════════════════════════════════════════
    //  متد عمومی (برای رویدادهای دلخواه)
    // ═══════════════════════════════════════════════════

    /**
     * ثبت یک رویداد در دیتابیس + ارسال نوتیفیکیشن متن ساده به ادمین
     *
     * @param string      $type     نوع رویداد (از ثابت‌های ActivityLog)
     * @param string      $message  پیام (متن ساده)
     * @param array       $context  داده‌های اضافی (user_id, chat_id, ...)
     * @param string      $level    سطح: info, success, warning, error
     * @param string|null $platform telegram | bale
     */
    public function notify(
        string $type,
        string $message,
        array $context = [],
        string $level = ActivityLog::LEVEL_INFO,
        ?string $platform = null,
    ): void {
        // ۱) ذخیره در دیتابیس
        $this->log($type, $message, $context, $level, $platform);

        // ۲) ارسال به ادمین
        $this->sendToAdmin($message, $level);
    }

    // ═══════════════════════════════════════════════════
    //  ثبت در دیتابیس
    // ═══════════════════════════════════════════════════

    public function log(
        string $type,
        string $message,
        array $context = [],
        string $level = ActivityLog::LEVEL_INFO,
        ?string $platform = null,
    ): void {
        try {
            ActivityLog::create([
                'type'     => $type,
                'platform' => $platform,
                'level'    => $level,
                'user_id'  => $context['user_id'] ?? null,
                'chat_id'  => (string) ($context['chat_id'] ?? ''),
                'message'  => $message,
                'data'     => $context,
            ]);
        } catch (\Throwable $e) {
            Log::error('NotificationService: ذخیره لاگ ناموفق', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════
    //  ارسال به ادمین
    // ═══════════════════════════════════════════════════

    /**
     * ارسال پیام متن ساده به چت/کانال ادمین
     *
     * اولویت: admin_chat_id (تلگرام) → cache_channel_id
     */
    public function sendToAdmin(string $message, string $level = ActivityLog::LEVEL_INFO): void
    {
        if (!config('telegram.notifications_enabled', true)) {
            return;
        }

        $emoji = match ($level) {
            ActivityLog::LEVEL_SUCCESS => '✅',
            ActivityLog::LEVEL_WARNING => '⚠️',
            ActivityLog::LEVEL_ERROR   => '🔴',
            default                    => 'ℹ️',
        };

        $this->sendToTelegramAdmin("{$emoji} {$message}");

        if (config('telegram.notify_bale_too', false)) {
            $this->sendToBaleAdmin("{$emoji} {$message}");
        }
    }

    /**
     * ارسال پیام HTML به چت ادمین تلگرام
     */
    public function sendToTelegramAdmin(string $htmlText): void
    {
        if (!config('telegram.notifications_enabled', true)) {
            return;
        }

        $chatId = config('telegram.admin_chat_id') ?? config('telegram.cache_channel_id');

        if (!$chatId) {
            return;
        }

        try {
            $this->telegram->sendMessage($chatId, $htmlText, null, 'HTML');
        } catch (\Throwable $e) {
            Log::warning('NotificationService: ارسال به ادمین تلگرام ناموفق', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ارسال پیام به چت ادمین بله
     */
    public function sendToBaleAdmin(string $htmlText): void
    {
        $chatId = config('telegram.bale_admin_chat_id') ?? config('telegram.bale_cache_channel_id');

        if (!$chatId) {
            return;
        }

        try {
            $this->bale->sendMessage($chatId, $htmlText, null, 'HTML');
        } catch (\Throwable $e) {
            Log::warning('NotificationService: ارسال به ادمین بله ناموفق', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════
    // ════════ متدهای آماده برای رویدادهای رایج ════════
    // ═══════════════════════════════════════════════════
    //  هر متد: log() برای دیتابیس + sendToTelegramAdmin() برای ادمین
    // ═══════════════════════════════════════════════════

    /**
     * 📨 پیام جدید از کاربر دریافت شد
     */
    public function messageReceived(array $message, string $platform): void
    {
        $from = $message['from'] ?? [];
        $text = $message['text'] ?? '(بدون متن)';

        $name  = $from['first_name'] ?? 'نامشخص';
        $uname = $from['username'] ?? null;
        $uid   = $from['id'] ?? '-';

        // نسخه HTML برای ادمین
        $html  = "📨 <b>پیام جدید</b>\n\n";
        $html .= "👤 <b>کاربر:</b> " . e($name);
        if ($uname) {
            $html .= " (<code>@{$uname}</code>)";
        }
        $html .= "\n🤖 <b>پلتفرم:</b> {$this->platformName($platform)}";
        $html .= "\n🆔 <b>شناسه:</b> <code>{$uid}</code>";
        $html .= "\n💬 <b>متن:</b>\n" . e($text);

        // نسخه ساده برای دیتابیس
        $plain = "پیام از {$name}" . ($uname ? " (@{$uname})" : '') . " [{$this->platformName($platform)}]: {$text}";

        $this->log(
            type: ActivityLog::TYPE_MESSAGE_RECEIVED,
            message: $plain,
            context: [
                'user_id' => $from['id'] ?? null,
                'chat_id' => $message['chat']['id'] ?? null,
                'text'    => $text,
            ],
            level: ActivityLog::LEVEL_INFO,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * 🎵 آهنگ با موفقیت ارسال شد
     */
    public function songSent($song, int|string $chatId, string $quality, string $platform, ?int $userId = null): void
    {
        $html  = "🎵 <b>آهنگ ارسال شد</b>\n\n";
        $html .= "🎧 <b>عنوان:</b> " . e($song->title) . "\n";
        $html .= "🎤 <b>خواننده:</b> " . e($song->artist_name) . "\n";
        $html .= "🎚 <b>کیفیت:</b> {$quality}\n";
        $html .= "🤖 <b>پلتفرم:</b> {$this->platformName($platform)}\n";
        $html .= "📥 <b>chat_id:</b> <code>{$chatId}</code>";

        $plain = "آهنگ «{$song->title} - {$song->artist_name}» با کیفیت {$quality} ارسال شد [{$this->platformName($platform)}]";

        $this->log(
            type: ActivityLog::TYPE_SONG_SENT,
            message: $plain,
            context: [
                'song_id' => $song->id,
                'chat_id' => $chatId,
                'quality' => $quality,
                'user_id' => $userId,
            ],
            level: ActivityLog::LEVEL_SUCCESS,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * ❌ ارسال آهنگ ناموفق بود
     */
    public function songSendFailed($song, int|string $chatId, string $quality, string $error, string $platform, ?int $userId = null): void
    {
        $html  = "❌ <b>ارسال آهنگ ناموفق</b>\n\n";
        $html .= "🎧 <b>عنوان:</b> " . e($song->title) . "\n";
        $html .= "🎚 <b>کیفیت:</b> {$quality}\n";
        $html .= "🤖 <b>پلتفرم:</b> {$this->platformName($platform)}\n";
        $html .= "📥 <b>chat_id:</b> <code>{$chatId}</code>\n";
        $html .= "⚠️ <b>خطا:</b> " . e($error);

        $plain = "ارسال آهنگ «{$song->title}» ناموفق: {$error} [{$this->platformName($platform)}]";

        $this->log(
            type: ActivityLog::TYPE_SONG_SEND_FAILED,
            message: $plain,
            context: [
                'song_id' => $song->id,
                'chat_id' => $chatId,
                'quality' => $quality,
                'error'   => $error,
                'user_id' => $userId,
            ],
            level: ActivityLog::LEVEL_ERROR,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * ❗ خطای عمومی رخ داد
     *
     * @param string      $context  کجا/چی خطا داد (مثلاً "جاب دانلود آهنگ")
     * @param string      $error    متن خطا
     * @param string|null $platform telegram | bale | null
     */
    public function error(string $context, string $error, ?string $platform = null, array $extra = []): void
    {
        $html  = "❗ <b>خطا رخ داد</b>\n\n";
        $html .= "📝 <b>بخش:</b> " . e($context) . "\n";
        $html .= "⚠️ <b>خطا:</b> " . e($error);

        if ($platform) {
            $html .= "\n🤖 <b>پلتفرم:</b> {$this->platformName($platform)}";
        }

        $plain = "خطا در «{$context}»: {$error}";

        $this->log(
            type: ActivityLog::TYPE_ERROR,
            message: $plain,
            context: array_merge(['context' => $context, 'error' => $error], $extra),
            level: ActivityLog::LEVEL_ERROR,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * 👋 کاربر جدید عضو ربات شد
     */
    public function userJoined(BotUser $botUser, string $platform): void
    {
        $telegramCount = BotUser::telegram()->count();
        $baleCount     = BotUser::bale()->count();

        $html  = "👋 <b>کاربر جدید</b>\n\n";
        $html .= "👤 <b>نام:</b> " . e($botUser->full_name) . "\n";
        if ($botUser->username) {
            $html .= "🔑 <b>یوزرنیم:</b> @{$botUser->username}\n";
        }
        $html .= "🆔 <b>شناسه:</b> <code>{$botUser->user_id}</code>\n";
        $html .= "🤖 <b>پلتفرم:</b> {$this->platformName($platform)}\n";
        $html .= "📊 <b>آمار کل:</b>\n";
        $html .= "├ تلگرام: <b>{$telegramCount}</b> نفر\n";
        $html .= "└ بله: <b>{$baleCount}</b> نفر";

        $plain = "کاربر جدید: {$botUser->full_name} ({$botUser->user_id}) [{$this->platformName($platform)}]";

        $this->log(
            type: ActivityLog::TYPE_USER_JOINED,
            message: $plain,
            context: ['user_id' => $botUser->user_id],
            level: ActivityLog::LEVEL_SUCCESS,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * 🚫 کاربر ربات را بلاک کرد (از روی خطاهای مکرر ارسال)
     */
    public function userBlocked(BotUser $botUser, string $platform): void
    {
        $html  = "🚫 <b>کاربر ربات را بلاک کرد</b>\n\n";
        $html .= "👤 <b>نام:</b> " . e($botUser->full_name) . "\n";
        $html .= "🆔 <b>شناسه:</b> <code>{$botUser->user_id}</code>\n";
        $html .= "🔢 <b>تعداد خطای ارسال:</b> {$botUser->failed_send_count}\n";
        $html .= "🤖 <b>پلتفرم:</b> {$this->platformName($platform)}";

        $plain = "کاربر بلاک کرد: {$botUser->full_name} ({$botUser->user_id}) بعد از {$botUser->failed_send_count} خطا";

        $this->log(
            type: ActivityLog::TYPE_USER_BLOCKED,
            message: $plain,
            context: [
                'user_id'      => $botUser->user_id,
                'failed_count' => $botUser->failed_send_count,
            ],
            level: ActivityLog::LEVEL_WARNING,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    /**
     * 🎵 درخواست آهنگ جدید
     */
    public function songRequest(string $query, array $from, int|string $chatId, string $platform): void
    {
        $name  = $from['first_name'] ?? 'نامشخص';
        $uname = $from['username'] ?? null;

        $html  = "🎵 <b>درخواست آهنگ جدید</b>\n\n";
        $html .= "👤 <b>کاربر:</b> " . e($name);
        if ($uname) {
            $html .= " (<code>@{$uname}</code>)";
        }
        $html .= "\n🤖 <b>پلتفرم:</b> {$this->platformName($platform)}";
        $html .= "\n🔍 <b>جستجو:</b> " . e($query);

        $plain = "درخواست آهنگ از {$name}: «{$query}» [{$this->platformName($platform)}]";

        $this->log(
            type: ActivityLog::TYPE_SONG_REQUEST,
            message: $plain,
            context: [
                'user_id' => $from['id'] ?? null,
                'chat_id' => $chatId,
                'query'   => $query,
            ],
            level: ActivityLog::LEVEL_INFO,
            platform: $platform,
        );

        $this->sendToTelegramAdmin($html);
    }

    // ═══════════════════════════════════════════════════
    //  Helper
    // ═══════════════════════════════════════════════════

    private function platformName(string $platform): string
    {
        return match ($platform) {
            'telegram' => 'تلگرام',
            'bale'     => 'بله',
            default    => $platform,
        };
    }
}
