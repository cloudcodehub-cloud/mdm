<?php

namespace App\Support;

final class SensitiveValue
{
    public static function maskSsn(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) >= 4) {
            return '•••-••-'.substr($digits, -4);
        }

        return 'On file';
    }
}
