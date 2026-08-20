<?php

return [
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],
    ],

    // Prefix for every cache key this app writes — keeps hot-path caches
    // (feed rankings, unread counts, sidebar stats) namespaced and easy to flush.
    'prefix' => env('CACHE_PREFIX', 'campusconnect_cache'),

    // How long (seconds) the ranked feed / counts caches stay warm before recompute.
    'ttl' => [
        'feed' => 60,
        'counts' => 30,
        'directory' => 300,
    ],
];
