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

    'bkash' => [
        'base_url'   => env('BKASH_BASE_URL'),
        'username'   => env('BKASH_USERNAME'),
        'password'   => env('BKASH_PASSWORD'),
        'app_key'    => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'ipn_secret' => env('BKASH_IPN_SECRET'), // shared secret for HMAC-SHA256 webhook signature
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8095') . '/auth/google/callback'),
    ],

    'wireguard' => [
        'interface_name' => env('WIREGUARD_INTERFACE', 'wg0'),
        'server_public_key' => env('WG_SERVER_PUBLIC_KEY', env('WIREGUARD_SERVER_PUBLIC_KEY', 'DuZQaTGDe95eAhT6+OFgFaI+rsP/n6iaIxjWSvtTRx4=')),
        'server_endpoint' => env('WG_ENDPOINT', env('WIREGUARD_SERVER_ENDPOINT', '213.199.49.216:51820')),
        'gateway_ip' => env('WIREGUARD_GATEWAY_IP', '10.10.0.1'),
    ],

    'radius' => [
        'server_ip' => env('RADIUS_SERVER_IP', '10.10.0.1'),
        'auth_port' => env('RADIUS_AUTH_PORT', 1812),
        'acct_port' => env('RADIUS_ACCT_PORT', 1813),
        'coa_port' => env('RADIUS_COA_PORT', 3799),
        'secret' => env('RADIUS_SHARED_SECRET', 'hsmart_rad_secret'),
    ],
];
