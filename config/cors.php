<?php

$origins = array_filter(array_map('trim', explode(',', (string) env('FRONTEND_URLS', env('FRONTEND_URL', 'http://127.0.0.1:5173,http://127.0.0.1:5174')))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => array_values(array_filter([
        env('FRONTEND_ORIGIN_PATTERN', '#^http://(127\.0\.0\.1|localhost)(:\d+)?$#'),
    ])),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
