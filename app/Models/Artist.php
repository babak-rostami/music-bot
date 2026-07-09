<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = ['name', 'slug', 'bio'];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class);
    }
}
