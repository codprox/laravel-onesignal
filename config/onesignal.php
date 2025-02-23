<?php

return [
    'app_id' => env('ONESIGNAL_APP_ID'),
    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    'default_icon' => env('ONESIGNAL_DEFAULT_ICON', 'https://example.com/icon.png'),
    'cache_ttl' => env('ONESIGNAL_CACHE_TTL', 3600), // 1 heure
    'timeout' => env('ONESIGNAL_TIMEOUT', 10.0),
    'connect_timeout' => env('ONESIGNAL_CONNECT_TIMEOUT', 5.0),
];