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

    // Optional: set the secret keys in .env instead of Admin → Settings → Shop.
    // Keys in .env take priority and can't be read or changed from the dashboard.
    'paystack' => [
        'test_secret_key' => env('PAYSTACK_TEST_SECRET_KEY'),
        'test_public_key' => env('PAYSTACK_TEST_PUBLIC_KEY'),
        'live_secret_key' => env('PAYSTACK_LIVE_SECRET_KEY'),
        'live_public_key' => env('PAYSTACK_LIVE_PUBLIC_KEY'),
        // "test" or "live". When set here, the mode switch in the dashboard is locked.
        'mode' => env('PAYSTACK_MODE'),
        'base_url' => 'https://api.paystack.co',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
