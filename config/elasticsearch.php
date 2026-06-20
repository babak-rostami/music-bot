<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection
    |--------------------------------------------------------------------------
    | Elasticsearch server host and port
    */
    'host' => env('ELASTICSEARCH_HOST', 'localhost'),
    'port' => env('ELASTICSEARCH_PORT', '9200'),

    /*
    |--------------------------------------------------------------------------
    | Analysis
    |--------------------------------------------------------------------------
    | This section defines:
    | - How text is broken into tokens
    | - What filters are applied to words
    | - You can add more filters and analyzers here
    */
    'analysis' => [

        'filter' => [

            /*
            |--------------------------------------------------------------------------
            | edge_ngram_autocomplete
            |--------------------------------------------------------------------------
            | Example word: "samsung"
            | Generated tokens:
            |   sa, sam, sams, samsu, ...
            |
            | This allows autocomplete search.
            | User can find results even by typing "sa".
            */
            'edge_ngram_autocomplete' => [
                'type' => 'edge_ngram',
                'min_gram' => 2,
                'max_gram' => 15,
            ],
        ],

        'analyzer' => [

            /*
            |--------------------------------------------------------------------------
            | autocomplete_index
            |--------------------------------------------------------------------------
            | Used when indexing data (saving documents).
            | Good for fields like title or name.
            |
            | Example:
            | title = "Samsung Galaxy"
            | tokens = sa, sam, sams, samsung, ga, gal, ...
            |
            | Perfect for autocomplete functionality.
            */
            'autocomplete_index' => [
                'type' => 'custom',
                'tokenizer' => 'standard', // Splits text into words
                'filter' => [
                    'lowercase',              // Converts text to lowercase
                    'edge_ngram_autocomplete' // Generates ngrams
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | autocomplete_search
            |--------------------------------------------------------------------------
            | Used for user search input.
            |
            | Why tokenizer is needed for search?
            | Because user may search multiple words.
            | Example: "Samsung Galaxy"
            | It will be converted to:
            | "samsung", "galaxy"
            |
            | This analyzer DOES NOT create ngrams.
            | It only normalizes the input.
            */
            'autocomplete_search' => [
                'type' => 'custom',
                'tokenizer' => 'standard',
                'filter' => [
                    'lowercase',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | fulltext
            |--------------------------------------------------------------------------
            | Used for long text fields.
            | N-gram is NOT recommended here because it creates too many tokens.
            |
            | Good for:
            | - description
            | - body
            | - content
            */
            'fulltext' => [
                'type' => 'standard', // Standard analyzer already includes lowercase
            ],
        ],
    ],

];
