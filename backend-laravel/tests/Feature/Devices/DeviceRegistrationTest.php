<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Models\AuditLog;
use App\Models\Collector;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Services\Auth\DeviceRegistrationService;
use App\Services\Auth\TokenIssuer;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\DeviceKeyPair;
use Tests\Support\SignsDeviceRequests;
use Tests\TestCase;

final class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase, SignsDeviceRequests;

    private const ANDROID_ID = 'a1b2c3d4e5f60718';

    private User $user;

    private string $registrationToken;

    private DeviceKeyPair $keys;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Collector::factory()->create()->user;
        $this->registrationToken = app(TokenIssuer::class)->issueRegistrationToken($this->user)->plainText;
        $this->keys = DeviceKeyPair::generate();
    }

    public function test_a_collector_registers_a_phone_and_waits_for_approval(): void
    {
        $id = $this->register()->assertCreated()->assertJsonPath('data.device.status', 'PENDING_APPROVAL')->json('data.device.id');

        $device = Device::query()->where('public_id', $id)->sole();
        $this->assertSame(DeviceRegistrationService::fingerprint(self::ANDROID_ID), $device->fingerprint_hash);
        $this->assertStringNotContainsString(self::ANDROID_ID, $device->fingerprint_hash);
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.REGISTERED')->count());
    }

    public function test_only_ec_p256_public_keys_are_accepted(): void
    {
        $this->register(['public_key' => DeviceKeyPair::generate('secp384r1')->publicKeyPem])
            ->assertStatus(422)->assertJsonPath('errors.0.field', 'public_key');

        $this->register(['public_key' => 'not a key'])
            ->assertStatus(422)->assertJsonPath('errors.0.field', 'public_key');
    }

    public function test_only_a_registration_token_can_register_a_phone(): void
    {
        $retailer = RetailerUser::factory()->create()->user->createToken('retailer-app', ['retailer'], now()->addDay())->plainTextToken;
        $collectorSession = $this->user->createToken('collector-app', ['collector'], now()->addHour())->plainTextToken;

        $this->register([], $retailer)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->register([], $collectorSession)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_new_registration_supersedes_an_older_pending_one(): void
    {
        $first = $this->register()->json('data.device.id');
        $second = $this->register(['public_key' => DeviceKeyPair::generate()->publicKeyPem, 'android_id' => 'ffeeddccbbaa0099'])->json('data.device.id');

        $this->assertSame('REVOKED', Device::query()->where('public_id', $first)->sole()->status->value);
        $this->assertSame('PENDING_APPROVAL', Device::query()->where('public_id', $second)->sole()->status->value);
    }

    public function test_a_phone_active_for_another_collector_is_refused(): void
    {
        Device::factory()->active()->create(['fingerprint_hash' => DeviceRegistrationService::fingerprint(self::ANDROID_ID)]);

        $this->register()->assertStatus(409)->assertJsonPath('code', 'DEVICE_ALREADY_BOUND');
    }

    public function test_polling_the_status_requires_proof_of_the_registered_key(): void
    {
        $id = $this->register()->json('data.device.id');

        $this->getJson('/api/v1/auth/device/status', ['Authorization' => "Bearer {$this->registrationToken}", 'X-Device-Id' => $id])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SIGNATURE_INVALID');
    }

    public function test_status_reports_pending_then_hands_over_a_bound_session_after_approval(): void
    {
        $device = Device::query()->where('public_id', $this->register()->json('data.device.id'))->sole();
        $auth = ['Authorization' => "Bearer {$this->registrationToken}"];

        $this->signedJson('GET', '/api/v1/auth/device/status', [], $this->keys, $device, $auth)
            ->assertOk()->assertJsonPath('data.status', 'PENDING_APPROVAL');

        app(DeviceBindingService::class)->approve($device, User::factory()->admin()->create());

        $session = $this->signedJson('GET', '/api/v1/auth/device/status', [], $this->keys, $device, $auth)
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')->json('data.token');

        $this->assertSame($device->id, PersonalAccessToken::findToken($session)->device_id);
        $this->assertNull(PersonalAccessToken::findToken($this->registrationToken));
        $this->signedJson('GET', '/api/v1/auth/me', [], $this->keys, $device->fresh(), ['Authorization' => "Bearer {$session}"])->assertOk();
    }

    public function test_the_first_phone_is_approved_automatically_when_enabled(): void
    {
        app(Settings::class)->set('device_auto_approve_first', true);

        $this->register()->assertCreated()->assertJsonPath('data.device.status', 'ACTIVE');
        $this->assertSame('SYSTEM', AuditLog::query()->where('action', 'DEVICE.APPROVED')->sole()->actor_type);
    }

    public function test_automatic_approval_never_applies_to_a_replacement_phone(): void
    {
        app(Settings::class)->set('device_auto_approve_first', true);
        Device::factory()->active()->for($this->user)->create();

        $this->register()->assertCreated()->assertJsonPath('data.device.status', 'PENDING_APPROVAL');
    }

    public function test_enforced_integrity_refuses_phones_that_cannot_be_verified(): void
    {
        app(Settings::class)->set('integrity_enforcement', 'ENFORCE');

        $this->register()->assertForbidden()->assertJsonPath('code', 'INTEGRITY_CHECK_FAILED');
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = [], ?string $token = null): TestResponse
    {
        return $this->postJson('/api/v1/auth/device/register', array_merge([
            'public_key' => $this->keys->publicKeyPem,
            'android_id' => self::ANDROID_ID,
            'model' => 'Redmi Note 13',
            'manufacturer' => 'Xiaomi',
            'os_version' => '14',
            'app_version' => '1.0.0',
        ], $overrides), [
            'Authorization' => 'Bearer '.($token ?? $this->registrationToken),
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
    }
}
