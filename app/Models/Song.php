<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    protected $fillable = [
        'title',
        'artist_name',
        'source_url',
        'lyrics',
        'downloads_count',
        'telegram_file_id_128',
        'telegram_file_id_320',
        'download_link_128',
        'download_link_320',
    ];
}
