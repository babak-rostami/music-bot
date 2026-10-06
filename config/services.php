<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ytdlp' => [
        'cookies_path' => env('YTDLP_COOKIES_PATH'),
        'pot_base_url' => env('YTDLP_POT_BASE_URL'),
    ],

    'madeline' => [
        'api_id'   => env('MADELINE_API_ID'),
        'api_hash' => env('MADELINE_API_HASH'),
    ],

    'music_finder_bots' => [
        ['key' => 'primary',   'username' => env('MUSIC_FINDER_BOT_USERNAME')],
        ['key' => 'secondary', 'username' => env('MUSIC_FINDER_BOT_2_USERNAME')],
    ],

    'music_finder_bot' => [
        'timeout'                    => env('MUSIC_FINDER_TIMEOUT', 40),
        'enabled_for_all'            => env('MUSIC_FINDER_ENABLED_FOR_ALL', false),
        'min_gap_seconds'            => env('MUSIC_FINDER_MIN_GAP_SECONDS', 3),
        'free_requests_limit'        => env('MUSIC_FINDER_FREE_LIMIT', 3),
        'free_requests_window_hours' => env('MUSIC_FINDER_FREE_WINDOW_HOURS', 12),
        'relay_channel_id'           => env('MUSIC_FINDER_RELAY_CHANNEL_ID'),
    ],

    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox'     => env('ZARINPAL_SANDBOX', false),
    ],

    'payment' => [
        'default_gateway'        => env('PAYMENT_DEFAULT_GATEWAY', 'zarinpal'),
        'created_expiry_hours'   => env('PAYMENT_CREATED_EXPIRY_HOURS', 24),
        'pending_expiry_hours'   => env('PAYMENT_PENDING_EXPIRY_HOURS', 24),
        'cleanup_retention_days' => env('PAYMENT_CLEANUP_RETENTION_DAYS', 90),
    ],

];
