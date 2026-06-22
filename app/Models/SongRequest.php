<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SongRequest extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'first_name',
        'last_name',
        'chat_id',
        'query',
        'status',
        'admin_note',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'chat_id' => 'integer',
    ];

    /**
     * وضعیت‌های ممکن برای یک درخواست
     */
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_REJECTED   = 'rejected';

    /**
     * اسکوپ: فقط درخواست‌های در انتظار
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * اسکوپ: فقط درخواست‌های یک کاربر خاص
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * نام کامل کاربر (first_name + last_name)
     */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * لینک تلگرام کاربر
     */
    public function getTelegramLinkAttribute(): ?string
    {
        if (!$this->username) {
            return null;
        }
        return 'https://t.me/' . ltrim($this->username, '@');
    }
}
