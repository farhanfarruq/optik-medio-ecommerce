<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | P1-3 (Phase 2): allowed_methods dan allowed_headers di-explicit-list.
    | Sebelumnya `['*']` yang melawan rekomendasi MDN ketika
    | supports_credentials = true (browser modern reject sebagian preflight).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Allowlist development origins (dev/test only).
        // Di production, hapus 4 baris berikut atau pastikan FRONTEND_URL sudah benar.
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

    // Cache preflight response selama 1 jam (3600 detik) — kurangi
    // overhead OPTIONS request berulang. Di dev/test, browser tetap revalidate.
    'max_age' => 3600,

    'supports_credentials' => true,

];
