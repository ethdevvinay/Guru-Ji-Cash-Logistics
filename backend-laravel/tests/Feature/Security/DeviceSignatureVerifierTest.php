<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Services\Auth\DeviceSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class DeviceSignatureVerifierTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const URI = '/api/v1/collector/duty/punch-in?source=app';

    private const BODY = '{"odometer_km":1200}';

    private DeviceKeyPair $keys;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keys = DeviceKeyPair::generate();
        $this->device = Device::factory()->active()->withPublicKey($this->keys->publicKeyPem)->create();
    }

    public function test_a_correctly_signed_request_is_accepted_and_its_nonce_is_consumed(): void
    {
        $request = $this->request(self::URI, self::BODY, $this->headers());

        app(DeviceSignatureVerifier::class)->verify($request, $this->device);

        $this->assertDatabaseHas('request_nonces', ['device_id' => $this->device->id, 'nonce' => $request->header('X-Nonce')]);
    }

    public function test_a_changed_body_is_rejected(): void
    {
        $this->assertRejected($this->request(self::URI, '{"odometer_km":9999}', $this->headers()), 'SIGNATURE_INVALID');
    }

    public function test_a_changed_uri_is_rejected(): void
    {
        $this->assertRejected($this->request('/api/v1/collector/duty/punch-out', self::BODY, $this->headers()), 'SIGNATURE_INVALID');
    }

    public function test_a_signature_from_another_key_is_rejected(): void
    {
        $headers = $this->deviceSignatureHeaders(DeviceKeyPair::generate(), $this->device->public_id, 'POST', self::URI, self::BODY);

        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');
    }

    public function test_a_phone_clock_more_than_two_minutes_off_gets_a_clear_message(): void
    {
        $headers = $this->headers(timestamp: now()->subSeconds(121)->getTimestamp());

        $exception = $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');

        $this->assertStringContainsString('date and time', $exception->getMessage());
    }

    public function test_a_phone_clock_just_under_two_minutes_off_is_tolerated(): void
    {
        $headers = $this->headers(timestamp: now()->subSeconds(119)->getTimestamp());

        app(DeviceSignatureVerifier::class)->verify($this->request(self::URI, self::BODY, $headers), $this->device);

        $this->assertDatabaseCount('request_nonces', 1);
    }

    public function test_a_replayed_nonce_is_rejected_and_audited(): void
    {
        $headers = $this->headers();
        app(DeviceSignatureVerifier::class)->verify($this->request(self::URI, self::BODY, $headers), $this->device);

        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'REPLAY_DETECTED');
        $this->assertSame(1, AuditLog::query()->where('action', 'SECURITY.REPLAY_DETECTED')->count());
    }

    public function test_missing_or_malformed_headers_are_rejected(): void
    {
        $headers = $this->headers();
        unset($headers['X-Signature']);
        $this->assertRejected($this->request(self::URI, self::BODY, $headers), 'SIGNATURE_INVALID');

        $this->assertRejected($this->request(self::URI, self::BODY, $this->headers(nonce: 'short')), 'SIGNATURE_INVALID');
    }

    public function test_expired_nonces_are_pruned(): void
    {
        DB::table('request_nonces')->insert([
            ['device_id' => $this->device->id, 'nonce' => str_repeat('a', 32), 'expires_at' => now()->subMinute()],
            ['device_id' => $this->device->id, 'nonce' => str_repeat('b', 32), 'expires_at' => now()->addMinutes(5)],
        ]);

        $this->artisan('security:prune-nonces')->assertSuccessful();

        $this->assertSame([str_repeat('b', 32)], DB::table('request_nonces')->pluck('nonce')->all());
    }

    /**
     * @return array<string, string>
     */
    private function headers(?int $timestamp = null, ?string $nonce = null): array
    {
        return $this->deviceSignatureHeaders($this->keys, $this->device->public_id, 'POST', self::URI, self::BODY, $timestamp, $nonce);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function request(string $uri, string $body, array $headers): Request
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return Request::create($uri, 'POST', [], [], [], $server, $body);
    }

    private function assertRejected(Request $request, string $code): ApiException
    {
        try {
            app(DeviceSignatureVerifier::class)->verify($request, $this->device);
        } catch (ApiException $e) {
            $this->assertSame($code, $e->errorCode);
            $this->assertSame(401, $e->status);

            return $e;
        }

        $this->fail("Expected the request to be rejected with {$code}.");
    }
}
