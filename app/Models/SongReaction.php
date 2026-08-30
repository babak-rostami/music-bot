<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SongReaction extends Model
{
    protected $fillable = ['song_id', 'identity_type', 'identity_id', 'type'];

    public function song()
    {
        return $this->belongsTo(Song::class);
    }
}
