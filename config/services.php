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
        'webhook_secret' => env('POSTMARK_WEBHOOK_SECRET'),
    ],

    'postcodes_io' => [
        'base_url' => env('POSTCODES_IO_BASE_URL', 'https://api.postcodes.io'),
    ],

    'datathistle' => [
        'access_token' => env('DATATHISTLE_ACCESS_TOKEN', env('DATA_THISTLE_API_TOKEN')),
        'base_url' => env('DATATHISTLE_BASE_URL', 'https://api.datathistle.com/v1'),
    ],

    'ticketmaster' => [
        'api_key' => env('TICKETMASTER_API_KEY'),
        'base_url' => env('TICKETMASTER_BASE_URL', 'https://app.ticketmaster.com/discovery/v2'),
        'feed_url' => env('TICKETMASTER_FEED_URL', 'https://app.ticketmaster.com/discovery-feed/v2/events.json'),
    ],

    'billetto' => [
        'api_key' => env('BILLETTO_API_KEY'),
        'api_secret' => env('BILLETTO_API_SECRET'),
        'webhook_secret' => env('BILLETTO_WEBHOOK_SECRET'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    ],

    'browsershot' => [
        // Set BROWSERSHOT_ENABLED=true once puppeteer is installed (npm install)
        // and spatie/browsershot is installed (composer require spatie/browsershot).
        // Used as a fallback in ProbeExternalWebsiteJob for JS-rendered sites.
        'enabled'     => env('BROWSERSHOT_ENABLED', false),
        'node_binary' => env('BROWSERSHOT_NODE_BINARY', 'node'),
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

];
