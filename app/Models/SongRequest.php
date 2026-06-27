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

    // ─── Scopes ───────────────────────────────────────────────
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }



    // ─── Helpers ──────────────────────────────────────────────
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'در انتظار',
            'processing' => 'در حال آپلود',
            'completed'  => 'آپلود شد',
            'rejected'   => 'رد شده',
            default      => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'    => 'yellow',
            'processing' => 'blue',
            'completed'  => 'green',
            'rejected'   => 'red',
            default      => 'gray',
        };
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: ($this->username ?? 'ناشناس');
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
