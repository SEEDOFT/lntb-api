<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Firebase Project
    |--------------------------------------------------------------------------
    |
    | This option determines which configured Firebase project is resolved
    | when the application requests a Firebase service without explicitly
    | providing a project name.
    |
    */

    'default' => env('FIREBASE_PROJECT', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Projects
    |--------------------------------------------------------------------------
    |
    | Here you may configure the Firebase projects used by the application.
    | Credentials may reference a service-account JSON file or use Google
    | Application Default Credentials from the environment.
    |
    */

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
