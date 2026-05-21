<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'xendit' => [
        'secret_key'    => env('XENDIT_SECRET_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        // P1-2: IP whitelist untuk webhook callback Xendit.
        // Set via env XENDIT_WEBHOOK_ALLOWED_IPS sebagai comma-separated list.
        // Kosongkan / hilangkan untuk disable check (tidak direkomendasi di prod).
        // Ref daftar IP terkini: https://docs.xendit.co/xenplatform/webhooks
        'webhook_allowed_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('XENDIT_WEBHOOK_ALLOWED_IPS', ''))
        ))),
    ],

    'rajaongkir' => [
        'api_key'            => env('RAJAONGKIR_API_KEY'),
        'base_url'           => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
        'origin_district_id' => env('RAJAONGKIR_ORIGIN_DISTRICT_ID', '1391'),
    ],

];
