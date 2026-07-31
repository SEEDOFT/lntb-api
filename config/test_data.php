<?php

declare(strict_types=1);

return [
    'seed_with_database' => env(
        'LNTB_SEED_TEST_DATA',
        env('APP_ENV', 'production') === 'local',
    ),
];
