<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HSmart ISP Marketing Site Configuration
    |--------------------------------------------------------------------------
    | Single source of truth for brand name, contact placeholders, default
    | currency, supported currency rates, and base plan amounts.
    */

    'brand_name' => 'HSmart ISP',

    // Replace [YOUR NUMBER] / placeholder digits with your live WhatsApp number (E.164 digits without +)
    'whatsapp_number' => env('LANDING_WHATSAPP_NUMBER', '254743606108'),
    'whatsapp_display_placeholder' => '[YOUR NUMBER]',
    'support_email' => env('LANDING_SUPPORT_EMAIL', 'support@hsmart.co.ke'),

    'default_currency' => 'KES',

    // Base monthly plan prices in KES: Starter = KES 400, Business = KES 1,000, Enterprise = null
    'plans_base_kes' => [
        'starter' => 400,
        'business' => 1000,
        'enterprise' => null, // Custom
    ],

    // Dashboard preview card sample figures (base KES equivalents)
    'preview_base_kes' => [
        'pppoe_today' => 12400,
        'hotspot_today' => 6850,
        'total_revenue' => 398835,
        'pending_due' => 34568,
        'toast_amount' => 50,
    ],

    'currencies' => [
        'USD' => ['code' => 'USD', 'symbol' => '$',    'rate_from_kes' => 0.0078, 'decimals' => 0],
        'KES' => ['code' => 'KES', 'symbol' => 'KES ', 'rate_from_kes' => 1.0,    'decimals' => 0],
        'NGN' => ['code' => 'NGN', 'symbol' => 'NGN ', 'rate_from_kes' => 12.5,   'decimals' => 0],
        'GHS' => ['code' => 'GHS', 'symbol' => 'GHS ', 'rate_from_kes' => 0.12,   'decimals' => 0],
        'TZS' => ['code' => 'TZS', 'symbol' => 'TZS ', 'rate_from_kes' => 20.5,   'decimals' => 0],
        'UGX' => ['code' => 'UGX', 'symbol' => 'UGX ', 'rate_from_kes' => 28.5,   'decimals' => 0],
        'ZAR' => ['code' => 'ZAR', 'symbol' => 'R ',   'rate_from_kes' => 0.14,   'decimals' => 0],
        'EUR' => ['code' => 'EUR', 'symbol' => '€',    'rate_from_kes' => 0.0072, 'decimals' => 0],
    ],
];

