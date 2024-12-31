<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Environment
    |--------------------------------------------------------------------------
    |
    | This option controls the environment that the API is running in.
    |
    */
    'app.env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | This option controls the default API version that will be used when a client does not specify one.
    |
    */
    'api_version' => env('API_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Show Exception Environments
    |--------------------------------------------------------------------------
    |
    | This option controls which environments exceptions should be shown in.
    |
    */
    'show_exception_environments' => ['local', 'testing', 'development'],

    /*
    |--------------------------------------------------------------------------
    | Pagination Caching
    |--------------------------------------------------------------------------
    |
    | This option controls whether paginated responses should be cached.
    | When enabled, you can set a cache key prefix and duration.
    |
    */
    'cache_paginated_responses' => env('CACHE_PAGINATED_RESPONSES', false),
    'paginated_cache_prefix' => env('PAGINATED_CACHE_PREFIX', 'laravel_api_paginated_'),
    'paginated_cache_duration' => env('PAGINATED_CACHE_DURATION', 3600), // in seconds

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | This option controls the rate limiting configuration for API routes.
    |
    */
    'rate_limit_max_attempts' => env('API_RATE_LIMIT_MAX_ATTEMPTS', 60),
    'rate_limit_decay_minutes' => env('API_RATE_LIMIT_DECAY_MINUTES', 1),

    /*
    |--------------------------------------------------------------------------
    | Response Structure
    |--------------------------------------------------------------------------
    |
    | This option controls the structure of the API response.
    |
    */
    'response_structure' => [
        'success_key' => 'success',
        'message_key' => 'message',
        'data_key' => 'data',
        'errors_key' => 'errors',
        'error_code_key' => 'error_code',
        'meta_key' => 'meta',
        'links_key' => '_links',
        'include_api_version' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Compression
    |--------------------------------------------------------------------------
    |
    | This option controls whether responses should be compressed.
    | Set to true to enable compression, false to disable.
    |
    */
    'enable_compression' => env('API_RESPONSE_COMPRESSION', true),

    /*
    |--------------------------------------------------------------------------
    | Response Compression Threshold
    |--------------------------------------------------------------------------
    |
    | This option controls the minimum response size required for compression to be applied.
    |
    */
    'compression_threshold' => env('API_RESPONSE_COMPRESSION_THRESHOLD', 1024),

    /*
    |--------------------------------------------------------------------------
    | API Response Logging Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that will be used to write logs for
    | API responses. Set this to 'api' to use a dedicated channel, or to any
    | other channel defined in your logging configuration.
    |
    */
    'log_channel' => 'api',
];
