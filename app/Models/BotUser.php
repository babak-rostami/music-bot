<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * ─────────────────────────────────────────────────────────
 *  مدل کاربران ربات
 * ─────────────────────────────────────────────────────────
 *
 *  هر ردیف = عضویت یک کاربر در یک پلتفرم مشخص.
 *
 *  تلگرام و بله دو سیستم جدا هستند و آیدی‌هاشان مرتبط نیست.
 *  اگر کسی در هر دو ربات عضو باشد، دو ردیف ساخته می‌شود:
 *      - ردیف ۱: platform = telegram , user_id = <آیدی تلگرام>
 *      - ردیف ۲: platform = bale     , user_id = <آیدی بله>
 *
 *  کلید یکتا: (platform, user_id)
 *
 *  ستون failed_send_count برای تشخیص بلاک شدن است:
 *  هر بار ارسال ناموفق، یکی اضافه می‌شود و وقتی از آستانه عبور کند
 *  → is_blocked = true
 */
class BotUser extends Model
{
    use HasFactory;

    protected $table = 'bot_users';

    // ═══════════════════════════════════════════════════
    //  ثابت‌های پلتفرم
    // ═══════════════════════════════════════════════════

    public const PLATFORM_TELEGRAM = 'telegram';
    public const PLATFORM_BALE     = 'bale';

    protected $fillable = [
        'platform',
        'user_id',
        'username',
        'first_name',
        'last_name',
        'failed_send_count',
        'is_blocked',
        'blocked_at',
        'last_activity_at',
    ];

    protected $casts = [
        'platform'          => 'string',
        'failed_send_count' => 'integer',
        'is_blocked'        => 'boolean',
        'blocked_at'        => 'datetime',
        'last_activity_at'  => 'datetime',
    ];

    // ═══════════════════════════════════════════════════
    //  Scopes
    // ═══════════════════════════════════════════════════

    /** فقط اعضای تلگرام */
    public function scopeTelegram(Builder $query): Builder
    {
        return $query->where('platform', self::PLATFORM_TELEGRAM);
    }

    /** فقط اعضای بله */
    public function scopeBale(Builder $query): Builder
    {
        return $query->where('platform', self::PLATFORM_BALE);
    }

    /** فقط یک پلتفرم مشخص (با بررسی خالی نبودن پارامتر) */
    public function scopeOfPlatform(Builder $query, ?string $platform): Builder
    {
        if (empty($platform)) {
            return $query;
        }

        if (in_array($platform, [self::PLATFORM_TELEGRAM, self::PLATFORM_BALE])) {
            return $query->where('platform', $platform);
        }

        return $query;
    }

    /** اسکوپ جایگزین برای هماهنگی با نام‌گذاری‌های مختلف فیلتر پلتفرم */
    public function scopePlatform(Builder $query, ?string $platform): Builder
    {
        return $this->scopeOfPlatform($query, $platform);
    }

    /** کاربران فعال (بلاک نشده) */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blocked', false);
    }

    /** کاربران بلاک شده */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('is_blocked', true);
    }

    /**
     * اسکوپ فیلتر وضعیت دسترسی بر اساس پارامتر ورودی فرم (all, active, blocked)
     */
    public function scopeBlockedStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'blocked' => $query->blocked(),
            'active'  => $query->active(),
            default   => $query,
        };
    }

    /**
     * اسکوپ جستجوی پیشرفته متنی (در شناسه پلتفرم، نام کاربری، نام و نام خانوادگی)
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $search = trim($search);

        return $query->where(function (Builder $q) use ($search) {
            $q->where('user_id', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"]);
        });
    }

    // ═══════════════════════════════════════════════════
    //  Accessors
    // ═══════════════════════════════════════════════════

    /** نام کامل کاربر */
    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : 'بدون‌نام';
    }

    /** نام فارسی پلتفرم */
    public function getPlatformLabelAttribute(): string
    {
        return match ($this->platform) {
            self::PLATFORM_TELEGRAM => 'تلگرام',
            self::PLATFORM_BALE     => 'بله',
            default                 => $this->platform,
        };
    }

    /** نام فارسی پلتفرم (نام مستعار جهت هماهنگی کامل) */
    public function getPlatformNameAttribute(): string
    {
        return $this->platform_label;
    }

    /**
     * لینک پروفایل (در صورت وجود نام کاربری در تلگرام یا بله)
     */
    public function getProfileUrlAttribute(): ?string
    {
        if (!$this->username) {
            return null;
        }

        $cleanUsername = ltrim($this->username, '@');

        return match ($this->platform) {
            self::PLATFORM_TELEGRAM => "https://t.me/{$cleanUsername}",
            self::PLATFORM_BALE     => "https://ble.ir/{$cleanUsername}",
            default                 => null,
        };
    }
}
