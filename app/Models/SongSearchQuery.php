<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SongSearchQuery extends Model
{
    protected $table = 'song_search_queries';

    protected $fillable = [
        'query',
        'count',
        'last_searched_at',
    ];

    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'last_searched_at' => 'datetime',
        ];
    }

    /**
     * محبوب‌ترین جستجوها
     */
    public function scopePopular(Builder $query, int $limit = 20): Builder
    {
        return $query
            ->orderByDesc('count')
            ->orderByDesc('last_searched_at')
            ->limit($limit);
    }

    /**
     * جدیدترین جستجوها
     */
    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query
            ->orderByDesc('last_searched_at')
            ->limit($limit);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
