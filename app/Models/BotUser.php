<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    public function scopeTelegram($query)
    {
        return $query->where('platform', self::PLATFORM_TELEGRAM);
    }

    /** فقط اعضای بله */
    public function scopeBale($query)
    {
        return $query->where('platform', self::PLATFORM_BALE);
    }

    /** فقط یک پلتفرم مشخص */
    public function scopeOfPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /** کاربران فعال (بلاک نشده) */
    public function scopeActive($query)
    {
        return $query->where('is_blocked', false);
    }

    /** کاربران بلاک شده */
    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
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
}
