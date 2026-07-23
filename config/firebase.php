<?php

declare(strict_types=1);

return [
    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [
        'app' => [
            'credentials' => env(
                'FIREBASE_CREDENTIALS',
                env('GOOGLE_APPLICATION_CREDENTIALS')
            ),
            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),
            'http_client_options' => [
                'timeout' => (float) env('FIREBASE_HTTP_CLIENT_TIMEOUT', 10),
            ],
        ],
    ],
];
