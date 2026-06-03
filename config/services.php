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

    'roma' => [
        'url' => env('ROMA_API_URL'),
        'token' => env('ROMA_SYNC_TOKEN'),
        'wa_token' => env('WA_TOKEN'),
        /** URL pública ngrok de roma-api (puerto 3000), misma que ROMA_API_PUBLIC_URL allí */
        'api_public_url' => env('ROMA_API_PUBLIC_URL'),
        'webhook_secret' => env('ROMA_WEBHOOK_SECRET'),
        // Contrato WhatsApp v1: text, image, interactive (kind button|list), template
        'contract_version' => 1,
    ],

];
