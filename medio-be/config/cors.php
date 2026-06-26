<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | paths harus cover semua route yang diakses frontend: api/*, auth/*, events, dll.
    | Jangan pakai wildcard origin saat supports_credentials=true.
    |
    */

    // Cover semua path yang diakses frontend (bukan hanya api/*)
    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL'),            // set di Railway env vars
        env('FRONTEND_URL_2'),          // backup domain jika ada
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
        'X-Correlation-ID',
        'Origin',
    ],

    'exposed_headers' => [
        'X-Correlation-ID',
    ],

    'max_age' => 3600,

    'supports_credentials' => true,

];
