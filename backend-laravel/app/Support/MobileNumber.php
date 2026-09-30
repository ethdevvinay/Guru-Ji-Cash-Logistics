<?php

declare(strict_types=1);

namespace App\Support;

final class MobileNumber
{
    /** Normalises an Indian mobile number to +91XXXXXXXXXX, or returns null when the input is not one. */
    public static function normalize(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? '+91'.$digits : null;
    }

    /** "+919812000004" becomes "+91******0004" for logs and audit metadata. */
    public static function mask(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        return strlen($digits) < 4 ? '****' : '+91******'.substr($digits, -4);
    }
}
