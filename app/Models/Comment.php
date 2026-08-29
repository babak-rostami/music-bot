<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    protected $fillable = [
        'song_id',
        'parent_id',
        'reply_to',
        'name',
        'body',
        'status',
        'likes_count',
        'dislikes_count',
        'replies_count',
    ];

    protected $casts = [
        'status' => 'boolean',
        'likes_count' => 'integer',
        'dislikes_count' => 'integer',
        'replies_count' => 'integer',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replyTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to');
    }

    /**
     * All replies belonging to this top-level comment (flat)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 1);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Always return numeric 0
     */
    public function getLikesCountAttribute($value): int
    {
        return (int) $value;
    }

    public function getDislikesCountAttribute($value): int
    {
        return (int) $value;
    }

    public function getRepliesCountAttribute($value): int
    {
        return (int) $value;
    }

    /**
     * Persian Jalali time (requires morilog/jalali or verta)
     */
    public function getPersianCreatedAtAttribute(): string
    {
        if (function_exists('jdate')) {
            return jdate($this->created_at)->ago();
        }

        // Fallback
        return $this->created_at->diffForHumans();
    }

    /**
     * Update the like/dislike counters.
     * Called after a reaction is added/updated/removed.
     */
    public function updateReactionCounts(int $likes, int $dislikes): void
    {
        $this->update([
            'likes_count' => $likes,
            'dislikes_count' => $dislikes,
        ]);
    }
}
