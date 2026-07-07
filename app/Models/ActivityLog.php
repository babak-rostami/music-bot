<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ─────────────────────────────────────────────────────────
 *  مدل لاگ فعالیت‌ها
 * ─────────────────────────────────────────────────────────
 *
 *  هر رویدادی در سیستم (پیام، ارسال آهنگ، خطا و...) در این جدول ثبت می‌شود.
 *  این جدول توسط NotificationService پر می‌شود.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'type',
        'platform',
        'level',
        'user_id',
        'chat_id',
        'message',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    // ═══════════════════════════════════════════════════
    //  ثابت‌های نوع رویداد
    // ═══════════════════════════════════════════════════

    public const TYPE_MESSAGE_RECEIVED = 'message_received';
    public const TYPE_SONG_SENT        = 'song_sent';
    public const TYPE_SONG_SEND_FAILED = 'song_send_failed';
    public const TYPE_DOWNLOAD_STARTED = 'download_started';
    public const TYPE_ERROR            = 'error';
    public const TYPE_USER_JOINED      = 'user_joined';
    public const TYPE_USER_BLOCKED     = 'user_blocked';
    public const TYPE_SONG_REQUEST     = 'song_request';

    /** همه انواع قابل نمایش + برچسب فارسی */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_MESSAGE_RECEIVED => 'پیام دریافتی',
            self::TYPE_SONG_SENT        => 'آهنگ ارسال شد',
            self::TYPE_SONG_SEND_FAILED => 'ارسال ناموفق',
            self::TYPE_DOWNLOAD_STARTED => 'شروع دانلود',
            self::TYPE_ERROR            => 'خطا',
            self::TYPE_USER_JOINED      => 'کاربر جدید',
            self::TYPE_USER_BLOCKED     => 'بلاک‌شدن کاربر',
            self::TYPE_SONG_REQUEST     => 'درخواست آهنگ',
        ];
    }

    /** آیکون (emoji) هر نوع */
    public static function typeIcons(): array
    {
        return [
            self::TYPE_MESSAGE_RECEIVED => '📨',
            self::TYPE_SONG_SENT        => '🎵',
            self::TYPE_SONG_SEND_FAILED => '❌',
            self::TYPE_DOWNLOAD_STARTED => '⬇️',
            self::TYPE_ERROR            => '❗',
            self::TYPE_USER_JOINED      => '👋',
            self::TYPE_USER_BLOCKED     => '🚫',
            self::TYPE_SONG_REQUEST     => '🔎',
        ];
    }

    // ═══════════════════════════════════════════════════
    //  ثابت‌های سطح
    // ═══════════════════════════════════════════════════

    public const LEVEL_INFO    = 'info';
    public const LEVEL_SUCCESS = 'success';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';

    /** برچسب فارسی هر سطح */
    public static function levelLabels(): array
    {
        return [
            self::LEVEL_INFO    => 'اطلاع',
            self::LEVEL_SUCCESS => 'موفق',
            self::LEVEL_WARNING => 'هشدار',
            self::LEVEL_ERROR   => 'خطا',
        ];
    }

    // ═══════════════════════════════════════════════════
    //  Relationships
    // ═══════════════════════════════════════════════════

    /**
     * کاربر ربات مرتبط با این لاگ (بر اساس platform + user_id)
     */
    public function botUser()
    {
        return $this->belongsTo(
            BotUser::class,
            'user_id',
            'user_id'
        )->where('platform', $this->platform);
    }

    // ═══════════════════════════════════════════════════
    //  Scopes
    // ═══════════════════════════════════════════════════

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeErrors($query)
    {
        return $query->where('level', self::LEVEL_ERROR);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ═══════════════════════════════════════════════════
    //  Accessors
    // ═══════════════════════════════════════════════════

    /** برچسب فارسی نوع رویداد */
    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    /** آیکون نوع رویداد */
    public function getTypeIconAttribute(): string
    {
        return self::typeIcons()[$this->type] ?? '📋';
    }

    /** برچسب فارسی سطح */
    public function getLevelLabelAttribute(): string
    {
        return self::levelLabels()[$this->level] ?? $this->level;
    }

    /** کلاس‌های Tailwind بج (badge) بر اساس سطح */
    public function getLevelBadgeAttribute(): string
    {
        return match ($this->level) {
            self::LEVEL_SUCCESS => 'bg-green-100 text-green-800',
            self::LEVEL_WARNING => 'bg-yellow-100 text-yellow-800',
            self::LEVEL_ERROR   => 'bg-red-100 text-red-800',
            default             => 'bg-blue-100 text-blue-800',
        };
    }

    /** کلاس‌های Tailwind خط سمت چپ کارت بر اساس سطح */
    public function getLevelBorderAttribute(): string
    {
        return match ($this->level) {
            self::LEVEL_SUCCESS => 'border-r-green-500',
            self::LEVEL_WARNING => 'border-r-yellow-500',
            self::LEVEL_ERROR   => 'border-r-red-500',
            default             => 'border-r-blue-500',
        };
    }

    /** برچسب فارسی پلتفرم */
    public function getPlatformLabelAttribute(): string
    {
        return match ($this->platform) {
            'telegram' => 'تلگرام',
            'bale'     => 'بله',
            default    => $this->platform ?? '—',
        };
    }

    /** کلاس badge پلتفرم */
    public function getPlatformBadgeAttribute(): string
    {
        return match ($this->platform) {
            'telegram' => 'bg-sky-100 text-sky-800',
            'bale'     => 'bg-indigo-100 text-indigo-800',
            default    => 'bg-gray-100 text-gray-800',
        };
    }

    /** خلاصه کوتاه پیام برای نمایش */
    public function getShortMessageAttribute(): string
    {
        return \Illuminate\Support\Str::limit($this->message, 80);
    }
}
