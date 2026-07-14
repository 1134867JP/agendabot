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

    'claude' => [
        'key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
    ],

    'evolution' => [
        'url' => env('EVOLUTION_API_URL'),
        'key' => env('EVOLUTION_API_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'asaas' => [
        'key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'),
        'sandbox' => env('ASAAS_SANDBOX', true),
        'webhook_secret' => env('ASAAS_WEBHOOK_SECRET'),
    ],

    'error_alerts' => [
        'email' => env('ERROR_ALERT_EMAIL'),
        'throttle_minutes' => env('ERROR_ALERT_THROTTLE_MINUTES', 15),
    ],

    'monitor' => [
        'token' => env('MONITOR_API_TOKEN'),
    ],

];
