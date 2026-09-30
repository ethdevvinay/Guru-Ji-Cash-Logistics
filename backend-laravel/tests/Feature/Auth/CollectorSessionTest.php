<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\CollectorStatus;
use App\Enums\DeviceStatus;
use App\Models\Collector;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class CollectorSessionTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const PASSWORD = 'Collector@Pass1';

    private DeviceKeyPair $keys;

    private Collector $collector;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keys = DeviceKeyPair::generate();
        $this->collector = Collector::factory()
            ->for(User::factory()->collector()->state(['mobile' => '+919812000003', 'password' => self::PASSWORD]))
            ->create(['collector_code' => 'COL-104']);
        $this->device = Device::factory()->active()->withPublicKey($this->keys->publicKeyPem)->for($this->collector->user)->create();
    }

    public function test_logging_in_from_the_approved_phone_gives_a_token_bound_to_it(): void
    {
        $response = $this->signedLogin()->assertOk()->assertJsonPath('data.user.collector.code', 'COL-104');

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertSame($this->device->id, $token->device_id);
        $this->assertSame(['collector'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addHours(14)->getTimestamp(), $token->expires_at->getTimestamp(), 5);
    }

    public function test_logging_in_from_an_unregistered_phone_returns_a_short_lived_registration_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', $this->credentials())
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_REGISTRATION_REQUIRED');

        $token = PersonalAccessToken::findToken($response->json('data.registration_token'));
        $this->assertSame(['device:register'], $token->abilities);
        $this->assertNull($token->device_id);
        $this->assertTrue($token->expires_at->lessThanOrEqualTo(now()->addMinutes(10)));
    }

    public function test_a_login_signed_with_the_wrong_key_is_rejected(): void
    {
        $this->signedLogin(DeviceKeyPair::generate())->assertUnauthorized()->assertJsonPath('code', 'SIGNATURE_INVALID');
    }

    public function test_a_suspended_collector_cannot_log_in(): void
    {
        $this->collector->update(['status' => CollectorStatus::Suspended]);

        $this->signedLogin()->assertForbidden()->assertJsonPath('code', 'COLLECTOR_SUSPENDED');
    }

    public function test_a_retailer_cannot_use_the_collector_app(): void
    {
        RetailerUser::factory()->for(User::factory()->retailer()->state(['mobile' => '+919812000004', 'password' => self::PASSWORD]))->create();

        $this->postJson('/api/v1/auth/login', ['mobile' => '9812000004', 'password' => self::PASSWORD, 'app' => 'collector'])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_signed_requests_with_the_bound_token_are_accepted(): void
    {
        $token = $this->loginToken();

        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $this->device, ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.collector.code', 'COL-104');
    }

    public function test_an_unsigned_request_with_a_collector_token_is_refused(): void
    {
        $token = $this->loginToken();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_token_cannot_be_used_from_another_phone(): void
    {
        $token = $this->loginToken();
        $otherKeys = DeviceKeyPair::generate();
        $otherPhone = Device::factory()->active()->withPublicKey($otherKeys->publicKeyPem)->create();

        $this->signedJson('GET', '/api/v1/auth/me', [], $otherKeys, $otherPhone, ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_revoked_phone_loses_access_immediately(): void
    {
        $token = $this->loginToken();
        $this->device->forceFill(['status' => DeviceStatus::Revoked])->save();

        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $this->device, ['Authorization' => "Bearer {$token}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'DEVICE_NOT_BOUND');
    }

    public function test_a_replayed_request_is_refused(): void
    {
        $token = $this->loginToken();
        $headers = array_merge(
            ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
            $this->deviceSignatureHeaders($this->keys, $this->device->public_id, 'GET', '/api/v1/auth/me', ''),
        );
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('GET', '/api/v1/auth/me', [], [], [], $server, '')->assertOk();
        $this->call('GET', '/api/v1/auth/me', [], [], [], $server, '')->assertUnauthorized()->assertJsonPath('code', 'REPLAY_DETECTED');
    }

    public function test_rotating_keeps_the_phone_binding_and_retires_the_old_token(): void
    {
        $old = $this->loginToken();

        $new = $this->signedJson('POST', '/api/v1/auth/token/rotate', [], $this->keys, $this->device, ['Authorization' => "Bearer {$old}"])
            ->assertOk()
            ->json('data.token');

        $this->assertNull(PersonalAccessToken::findToken($old));
        $this->assertSame($this->device->id, PersonalAccessToken::findToken($new)->device_id);
    }

    public function test_retailer_requests_never_need_signatures(): void
    {
        $member = RetailerUser::factory()->create();
        $token = $member->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    /** @return array<string, string> */
    private function credentials(): array
    {
        return ['mobile' => '9812000003', 'password' => self::PASSWORD, 'app' => 'collector'];
    }

    private function signedLogin(?DeviceKeyPair $keys = null): TestResponse
    {
        return $this->signedJson('POST', '/api/v1/auth/login', $this->credentials(), $keys ?? $this->keys, $this->device);
    }

    private function loginToken(): string
    {
        return (string) $this->signedLogin()->assertOk()->json('data.token');
    }
}
