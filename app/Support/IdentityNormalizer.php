<?php

declare(strict_types=1);

namespace App\Support;

final class IdentityNormalizer
{
    public static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    public static function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/[\s\-().]/', '', trim($value));
    }
}
