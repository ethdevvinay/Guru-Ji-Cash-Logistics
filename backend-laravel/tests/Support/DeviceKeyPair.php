<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** An EC P-256 key pair standing in for a phone's Android Keystore key. */
final class DeviceKeyPair
{
    private function __construct(
        public readonly string $privateKeyPem,
        public readonly string $publicKeyPem,
    ) {}

    public static function generate(string $curve = 'prime256v1'): self
    {
        $options = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => $curve];
        $config = self::opensslConfig();
        if ($config !== null) {
            $options['config'] = $config;
        }

        $key = openssl_pkey_new($options);
        if ($key === false) {
            throw new RuntimeException('Could not generate an EC key: '.openssl_error_string());
        }

        openssl_pkey_export($key, $private, null, $options);
        $details = openssl_pkey_get_details($key);

        return new self((string) $private, (string) $details['key']);
    }

    public function sign(string $data): string
    {
        openssl_sign($data, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);

        return base64_encode((string) $signature);
    }

    /** Windows PHP builds need an explicit openssl.cnf to generate keys. */
    private static function opensslConfig(): ?string
    {
        if (getenv('OPENSSL_CONF') !== false || PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $candidate = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        return is_file($candidate) ? $candidate : null;
    }
}
