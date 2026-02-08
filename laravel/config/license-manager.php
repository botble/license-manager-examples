<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Manager API URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your License Manager server.
    |
    */

    'api_url' => env('LICENSE_MANAGER_API_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your External API key from License Manager > API Settings.
    |
    */

    'api_key' => env('LICENSE_MANAGER_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Product ID
    |--------------------------------------------------------------------------
    |
    | The Product Reference ID from License Manager > Products.
    |
    */

    'product_id' => env('LICENSE_MANAGER_PRODUCT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for API responses.
    |
    */

    'timeout' => (int) env('LICENSE_MANAGER_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify SSL certificates. Should be true in production.
    |
    */

    'verify_ssl' => (bool) env('LICENSE_MANAGER_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) to cache license verification results.
    | Set to 0 to disable caching.
    |
    */

    'cache_ttl' => (int) env('LICENSE_MANAGER_CACHE_TTL', 3600),

];
