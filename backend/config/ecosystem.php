<?php

return [

    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    'auth' => [
        'token_ttl_minutes' => (int) env('AUTH_TOKEN_TTL_MINUTES', 60 * 24 * 30),
        // Failed logins against one account (from any IP) before it is temporarily locked.
        'lockout_attempts' => 10,
        'lockout_minutes' => 15,
    ],

    'card' => [
        'hmac_key' => env('CARD_HMAC_KEY'),
        'activation_code_ttl_days' => 90,
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 'media'),
        'url_ttl_minutes' => (int) env('MEDIA_URL_TTL_MINUTES', 60),
        'max_upload_kb' => 5120,
        'max_dimension' => 2048,
        'max_images_per_post' => 4,
    ],

    'cors_allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

];
