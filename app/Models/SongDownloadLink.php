<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongDownloadLink extends Model
{
    protected $fillable = [
        'song_id',
        'provider',
        'quality',
        'download_url',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
