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
        'token' => env('POSTMARK_TOKEN'),
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

    'tms_receipts' => [
        'endpoint' => env('TMS_ENDPOINT', 'https://tms.1utama.com.my/POS/POSService.svc/SendReceipts'),
        'authorization' => env('TMS_AUTHORIZATION_TOKEN'),
        'is_test' => env('TMS_IS_TEST', false),
    ],

    'myinvois' => [
        'enabled' => filter_var(env('MYINVOIS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('MYINVOIS_BASE_URL', 'https://myinvois.myrccornertrading.com'),
        'queue_delay_hours' => env('MYINVOIS_QUEUE_DELAY_HOURS', 72),
    ],

];
