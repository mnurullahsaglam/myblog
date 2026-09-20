<?php

declare(strict_types=1);

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'github' => [
        'personal_access_token' => env('GITHUB_PERSONAL_ACCESS_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wakatime' => [
        'app_id' => env('WAKATIME_APP_ID'),
        'app_secret' => env('WAKATIME_APP_SECRET'),
        'redirect' => env('WAKATIME_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/wakatime/callback'),
    ],

    'open_exchange_rates' => [
        'api_key' => env('OPEN_EXCHANGE_RATES_API_KEY'),
    ],

    'metals' => [
        'api_key' => env('METALS_API_KEY', ''),
    ],

    'open_library' => [
        'contact' => env('OPEN_LIBRARY_CONTACT', env('ADMIN_EMAIL', '')),
    ],
];
