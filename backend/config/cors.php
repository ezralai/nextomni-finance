<?php

$configuredOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))));

$defaultOrigins = [
    env('FRONTEND_URL', 'http://localhost:3000'),
    'http://localhost:3000',
    'http://127.0.0.1:3000',
];

$allowedOrigins = array_values(array_unique(array_filter(array_merge($defaultOrigins, $configuredOrigins))));

$configuredPatterns = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))));

$defaultPatterns = [
    '{^https:\/\/nextomni-finance(-[a-z0-9-]+)?\.vercel\.app$}i',
];

$allowedPatterns = array_values(array_unique(array_filter(array_merge($defaultPatterns, $configuredPatterns))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => $allowedPatterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
