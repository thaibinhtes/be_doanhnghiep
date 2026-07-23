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

    'recaptcha' => [
        'enabled' => filter_var(env('CAPCHA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'site_key' => env('CAPCHA_SITE_KEY'),
        'project_id' => env('CAPCHA_PROJECT_ID'),
        'api_key' => env('CAPCHA_API_KEY'),
        'action' => env('CAPCHA_ACTION', 'LOGIN'),
        'min_score' => (float) env('CAPCHA_MIN_SCORE', 0.5),
    ],

];
