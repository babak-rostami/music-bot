<?php

namespace App\Models;

use Babak\Elasticsearch\Traits\ElasticsearchableTrait;
use Illuminate\Database\Eloquent\Model;

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
}
