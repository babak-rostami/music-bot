<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MusicFinderRequest extends Model
{
    public const SOURCE_DATABASE = 'database';
    public const SOURCE_EXTERNAL = 'external_service';

    protected $fillable = ['platform', 'user_id', 'source', 'song_id', 'query_text'];

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function getPlatformLabelAttribute(): string
    {
        return $this->platform === 'bale' ? 'بله' : 'تلگرام';
    }

    public function getSourceLabelAttribute(): string
    {
        return $this->source === self::SOURCE_EXTERNAL ? 'سرویس خارجی' : 'دیتابیس خودمون';
    }
}
