<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HSmart Platforms ISP Hub Connector Configuration
    |--------------------------------------------------------------------------
    | Connection parameters provided by the HSmart Platforms Super Admin.
    */

    'enabled' => env('HUB_ENABLED', true),

    'url' => rtrim(env('HUB_URL', 'http://127.0.0.1:8000'), '/'),

    'isp_id' => env('HUB_ISP_ID', 1),

    'key' => env('HUB_KEY', ''),

    'secret' => env('HUB_SECRET', ''),

    'timeout' => (int) env('HUB_TIMEOUT', 10),

    'max_retries' => (int) env('HUB_MAX_RETRIES', 3),
];

