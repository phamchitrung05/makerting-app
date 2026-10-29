<?php

return [
    'name' => 'Core',

    /*
    |--------------------------------------------------------------------------
    | SafeRemoteFetcher — giới hạn tải URL từ xa chống SSRF
    |--------------------------------------------------------------------------
    */
    'remote_fetch' => [
        'timeout' => env('CORE_FETCH_TIMEOUT', 15),
        'max_bytes' => env('CORE_FETCH_MAX_BYTES', 5 * 1024 * 1024),
        'user_agent' => env('CORE_FETCH_USER_AGENT', 'MakertingApp-SafeFetcher/1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotency — thời gian khóa và TTL của khóa chống trùng request
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'lock_seconds' => env('CORE_IDEMPOTENCY_LOCK', 60),
        'ttl_seconds' => env('CORE_IDEMPOTENCY_TTL', 86400),
    ],
];
