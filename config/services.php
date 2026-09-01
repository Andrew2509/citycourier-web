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

    'komerce_payment' => [
        'api_key'      => env('KOMERCE_PAYMENT_API_KEY'),
        'env'          => env('KOMERCE_PAYMENT_ENV', 'sandbox'),
        'callback_key' => env('KOMERCE_PAYMENT_CALLBACK_KEY'),
        'base_url'     => env('KOMERCE_PAYMENT_ENV', 'sandbox') === 'production'
            ? 'https://api.collaborator.komerce.id/user'
            : 'https://api-sandbox.collaborator.komerce.id/user',
    ],

    // DANA integration. Saat ini memakai mode mock (tanpa kredensial resmi).
    'dana' => [
        'authorization_url' => env('DANA_AUTHORIZATION_URL', 'https://sandbox.dana.id/authorization'),
        'client_id'         => env('DANA_CLIENT_ID', 'mock-client-id'),
        'client_secret'     => env('DANA_CLIENT_SECRET', ''),
        'callback_url'      => env('DANA_CALLBACK_URL', env('APP_URL', 'http://localhost') . '/api/courier/dana/callback'),
        'mode'              => env('DANA_MODE', 'mock'),
    ],

];
