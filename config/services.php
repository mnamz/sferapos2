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
        'api_key' => env('MYINVOIS_API_KEY'),
        'queue_delay_hours' => (int) env('MYINVOIS_QUEUE_DELAY_HOURS', 72),
        'cancellation_window_hours' => (int) env('MYINVOIS_CANCELLATION_WINDOW_HOURS', 72),
        'einvoice_claim_url' => env('EINVOICE_CLAIM_URL', 'https://einvoice.myrccornertrading.com'),
        'branch' => env('EINVOICE_BRANCH', ''),
    ],

    'tangent' => [
        'enabled' => filter_var(env('TANGENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('TANGENT_BASE_URL', 'https://staging.synthesis.bz/posmy/v1/api'),
        'username' => env('TANGENT_USERNAME'),
        'password' => env('TANGENT_PASSWORD'),
        'machine_id' => env('TANGENT_MACHINE_ID'),
        'batch_id' => env('TANGENT_BATCH_ID', '1'),
        'gst_registered' => env('TANGENT_GST_REGISTERED'), // null => derive from shop settings
        'lookback_days' => (int) env('TANGENT_LOOKBACK_DAYS', 7),
        'timezone' => env('TANGENT_TIMEZONE', 'Asia/Kuala_Lumpur'),
    ],

];
