<?php

namespace App\Models;

use Babak\Elasticsearch\Traits\ElasticsearchableTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'artist_name',
        'source_url',
        'lyrics',
        'downloads_count',
        'telegram_file_id_128',
        'telegram_file_id_320',
        'bale_file_id_128',
        'bale_file_id_320',
        'download_link_128',
        'download_link_320',
        'aparat_link',
        'youtube_link',
        'spotify_link',
        'soundcloud_link',
        'cover_path',
        'show_in_saz_site',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'downloads_count' => 'integer',
        ];
    }

    use ElasticsearchableTrait;

    protected static function elasticsearchProperties(): array
    {
        return [
            'title' => [
                'type' => 'text',
                'analyzer' => 'autocomplete_index',
                'search_analyzer' => 'autocomplete_search',
            ],
            'artist_name' => [
                'type' => 'text',
                'analyzer' => 'autocomplete_index',
                'search_analyzer' => 'autocomplete_search',
            ],
            'lyrics' => [
                'type' => 'text',
                'analyzer' => 'fulltext',
                'search_analyzer' => 'fulltext',
            ],
        ];
    }
    protected static function elasticsearchFields(): array
    {
        return ['title', 'artist_name', 'lyrics'];
    }

    public static function elasticsearchIndex(): string
    {
        return 'songs';
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('artist_name', 'LIKE', "%{$term}%")
                ->orWhere('source_url', 'LIKE', "%{$term}%");
        });
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class);
    }

    public function downloadLinks(): HasMany
    {
        return $this->hasMany(SongDownloadLink::class);
    }

    /**
     * URL کامل کاور — در ویو فقط بنویس: {{ $song->cover_url }}
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->cover_path) {
                return asset('images/default-cover.webp');
            }

            $base = config('filesystems.disks.' . config('images.disk') . '.url');

            return rtrim((string) $base, '/') . '/' . ltrim($this->cover_path, '/');
        });
    }
}
