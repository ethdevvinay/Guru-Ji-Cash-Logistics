<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Validation\ValidationException;

final class DevicePublicKey
{
    /** Accepts only a PEM EC public key on the P-256 curve, as produced by the Android Keystore. */
    public static function assertValidP256(string $pem): void
    {
        $key = openssl_pkey_get_public($pem);

        if ($key === false) {
            throw ValidationException::withMessages(['public_key' => 'The public key is not a valid PEM public key.']);
        }

        $details = openssl_pkey_get_details($key);

        if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? null) !== 'prime256v1') {
            throw ValidationException::withMessages(['public_key' => 'The public key must be an EC P-256 key.']);
        }
    }
}
