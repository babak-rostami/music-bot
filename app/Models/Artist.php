<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Artist extends Model
{
    protected $fillable = ['name', 'slug', 'bio'];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class);
    }

    /**
     * اسکوپ جستجوی هوشمند در نام، اسلاگ و بیوگرافی خواننده
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $search = trim($search);

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('bio', 'like', "%{$search}%");
        });
    }
}
