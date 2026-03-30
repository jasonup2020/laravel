<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limit Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for different API endpoints. These values can be
    | overridden in individual routes or route groups.
    |
    */

    'rate_limit' => [
        // Default rate limit for all API endpoints
        'default' => env('API_RATE_LIMIT_DEFAULT', 60),

        // Specific rate limits for different endpoint types
        'login' => env('API_RATE_LIMIT_LOGIN', 5),
        'register' => env('API_RATE_LIMIT_REGISTER', 10),
        'password_reset' => env('API_RATE_LIMIT_PASSWORD_RESET', 3),
        'upload' => env('API_RATE_LIMIT_UPLOAD', 10),
        'export' => env('API_RATE_LIMIT_EXPORT', 5),

        // VIP user rate limit multiplier
        'vip_multiplier' => env('API_RATE_LIMIT_VIP_MULTIPLIER', 2),
    ],

];
