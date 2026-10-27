<?php

return [
    // Downstream services the gateway forwards to (internal Docker network only).
    'services' => [
        'todo' => env('TODO_SERVICE_URL', 'http://todo:8000'),
        'calendar' => env('CALENDAR_SERVICE_URL', 'http://calendar:8000'),
        'wallit' => env('WALLIT_SERVICE_URL', 'http://wallit:8000'),
    ],

    // Short-lived RS256 JWT the gateway signs for every forwarded request.
    'jwt' => [
        'private_key' => env('JWT_PRIVATE_KEY_PATH', storage_path('keys/jwt-private.pem')),
        'public_key' => env('JWT_PUBLIC_KEY_PATH', storage_path('keys/jwt-public.pem')),
        'issuer' => env('JWT_ISSUER', 'famexus-gateway'),
        'ttl' => (int) env('JWT_TTL_SECONDS', 60),
    ],
];
