<?php

namespace App\Models;

use Babak\Elasticsearch\Traits\ElasticsearchableTrait;
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
}
