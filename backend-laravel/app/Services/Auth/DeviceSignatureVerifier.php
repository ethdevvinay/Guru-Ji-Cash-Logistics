<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Verifies that a request was signed by a registered phone's Keystore key (spec §18.4)
 * and that it has not been seen before.
 */
final class DeviceSignatureVerifier
{
    public const MAX_CLOCK_SKEW_SECONDS = 120;

    public const NONCE_TTL_SECONDS = 600;

    public function __construct(private readonly AuditLogger $audit) {}

    public static function canonicalString(string $method, string $requestUri, string $body, string $timestamp, string $nonce, string $devicePublicId): string
    {
        return implode("\n", [strtoupper($method), $requestUri, hash('sha256', $body), $timestamp, $nonce, $devicePublicId]);
    }

    public function verify(Request $request, Device $device): void
    {
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = base64_decode((string) $request->header('X-Signature', ''), true);

        if (! ctype_digit($timestamp) || preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) !== 1 || $signature === false || $signature === '') {
            throw self::invalid('The request signature is missing or malformed.');
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) {
            throw self::invalid("The phone's clock is wrong. Turn on automatic date and time in the phone settings, then try again.");
        }

        $canonical = self::canonicalString(
            $request->getMethod(),
            $request->getRequestUri(),
            (string) $request->getContent(),
            $timestamp,
            $nonce,
            $device->public_id,
        );

        if (openssl_verify($canonical, $signature, $device->public_key_pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw self::invalid('The request signature is not valid for this phone.');
        }

        try {
            DB::table('request_nonces')->insert([
                'device_id' => $device->id,
                'nonce' => $nonce,
                'expires_at' => now()->addSeconds(self::NONCE_TTL_SECONDS),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->audit->record('SECURITY.REPLAY_DETECTED', $device, null, null, ['nonce' => $nonce]);

            throw new ApiException('REPLAY_DETECTED', 'This request was already processed.', 401);
        }
    }

    private static function invalid(string $message): ApiException
    {
        return new ApiException('SIGNATURE_INVALID', $message, 401);
    }
}
