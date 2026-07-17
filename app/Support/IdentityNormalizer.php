<?php

declare(strict_types=1);

namespace App\Support;

final class IdentityNormalizer
{
    public static function email(?string $value): ?string
    {
        return $value === null ? null : mb_strtolower(trim($value));
    }

    public static function phone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/[\s\-().]/', '', trim($value));
    }

    public static function login(string $value): string
    {
        return str_contains($value, '@') ? (string) self::email($value) : (string) self::phone($value);
    }
}
