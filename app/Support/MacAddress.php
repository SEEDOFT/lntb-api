<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class MacAddress
{
    public static function normalize(string $value): string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $value));
        if (strlen($hex) !== 12) {
            throw new InvalidArgumentException('The MAC address is invalid.');
        }

        return implode(':', str_split($hex, 2));
    }
}
