<?php

declare(strict_types=1);

namespace Tests\Feature\Devices;

use App\Enums\AdminPermission;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DeviceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_phone_replaces_the_previous_one_and_ends_its_sessions(): void
    {
        $user = User::factory()->collector()->create();
        $old = Device::factory()->active()->for($user)->create();
        $oldToken = $user->createToken('collector-app', ['collector'], now()->addHour());
        $oldToken->accessToken->forceFill(['device_id' => $old->id])->save();
        $new = Device::factory()->pending()->for($user)->create();

        app(DeviceBindingService::class)->approve($new, User::factory()->admin()->create());

        $this->assertSame('REVOKED', $old->fresh()->status->value);
        $this->assertSame('ACTIVE', $new->fresh()->status->value);
        $this->assertNull(PersonalAccessToken::findToken($oldToken->plainTextToken));
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.APPROVED')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'DEVICE.REVOKED')->count());
    }

    public function test_only_a_phone_waiting_for_approval_can_be_approved(): void
    {
        $device = Device::factory()->active()->create();

        try {
            app(DeviceBindingService::class)->approve($device, null);
            $this->fail('An active phone was approved again.');
        } catch (ApiException $e) {
            $this->assertSame('INVALID_DEVICE_STATE', $e->errorCode);
        }
    }

    public function test_revoking_a_phone_ends_its_sessions(): void
    {
        $device = Device::factory()->active()->create();
        $token = $device->user->createToken('collector-app', ['collector'], now()->addHour());
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        app(DeviceBindingService::class)->revoke($device, null, 'phone lost');

        $this->assertSame('REVOKED', $device->fresh()->status->value);
        $this->assertSame('phone lost', $device->fresh()->revoke_reason);
        $this->assertNull(PersonalAccessToken::findToken($token->plainTextToken));
    }

    public function test_admin_endpoints_need_the_devices_permission(): void
    {
        $device = Device::factory()->pending()->create();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertForbidden();

        Sanctum::actingAs(User::factory()->collector()->create());
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermission::DevicesManage->value);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/approve")->assertOk()->assertJsonPath('data.device.status', 'ACTIVE');
    }

    public function test_revoking_through_the_api_requires_a_reason(): void
    {
        $device = Device::factory()->active()->create();
        Sanctum::actingAs(User::factory()->admin(super: true)->create());

        $this->postJson("/api/v1/admin/devices/{$device->public_id}/revoke")->assertStatus(422)->assertJsonPath('errors.0.field', 'reason');
        $this->postJson("/api/v1/admin/devices/{$device->public_id}/revoke", ['reason' => 'phone stolen'])->assertOk()->assertJsonPath('data.device.status', 'REVOKED');
    }

    public function test_ops_commands_list_approve_and_revoke_phones(): void
    {
        $device = Device::factory()->pending()->create();

        $this->artisan('devices:pending')->expectsOutputToContain($device->public_id)->assertSuccessful();
        $this->artisan('devices:approve', ['device' => $device->public_id])->assertSuccessful();
        $this->assertSame('ACTIVE', $device->fresh()->status->value);
        $this->assertSame('SYSTEM', AuditLog::query()->where('action', 'DEVICE.APPROVED')->sole()->actor_type);

        $this->artisan('devices:revoke', ['device' => $device->public_id, '--reason' => 'test'])->assertSuccessful();
        $this->assertSame('REVOKED', $device->fresh()->status->value);
    }
}
