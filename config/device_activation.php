<?php

declare(strict_types=1);

return [
    'ttl_days' => (int) env('DEVICE_ACTIVATION_TTL_DAYS', 30),
    'max_failed_attempts' => (int) env('DEVICE_ACTIVATION_MAX_FAILED_ATTEMPTS', 10),
];
