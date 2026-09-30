<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Device;
use App\Services\Auth\DeviceSignatureVerifier;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SignsDeviceRequests
{
    /**
     * @return array<string, string>
     */
    protected function deviceSignatureHeaders(
        DeviceKeyPair $keys,
        string $devicePublicId,
        string $method,
        string $uri,
        string $body,
        ?int $timestamp = null,
        ?string $nonce = null,
    ): array {
        $timestamp ??= now()->getTimestamp();
        $nonce ??= Str::random(32);
        $canonical = DeviceSignatureVerifier::canonicalString($method, $uri, $body, (string) $timestamp, $nonce, $devicePublicId);

        return [
            'X-Device-Id' => $devicePublicId,
            'X-Timestamp' => (string) $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $keys->sign($canonical),
        ];
    }

    /**
     * Sends a JSON request whose method, URI and exact body are signed by the device key.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    protected function signedJson(string $method, string $uri, array $data, DeviceKeyPair $keys, Device $device, array $headers = []): TestResponse
    {
        $body = $data === [] ? '' : (string) json_encode($data);
        $server = $this->transformHeadersToServerVars(array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $this->deviceSignatureHeaders($keys, $device->public_id, $method, $uri, $body),
            $headers,
        ));

        return $this->call($method, $uri, [], [], [], $server, $body);
    }
}
