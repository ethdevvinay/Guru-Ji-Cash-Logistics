<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AdminPermission;
use App\Enums\AdminPermissionPreset;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'role:admin'])->prefix('api/v1/_test')->group(function (): void {
            Route::get('admin-only', fn () => ApiResponse::success());
            Route::get('pickups', fn () => ApiResponse::success())->middleware('permission:pickups.manage');
            Route::get('devices', fn () => ApiResponse::success())->middleware('permission:devices.manage');
        });
    }

    public function test_exactly_the_three_platform_roles_and_all_admin_permissions_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(['admin', 'collector', 'retailer'], Role::query()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(AdminPermission::values(), Permission::query()->pluck('name')->all());
        $this->assertCount(26, AdminPermission::cases());
    }

    public function test_no_fourth_role_can_ever_be_created(): void
    {
        $this->expectException(LogicException::class);

        Role::create(['name' => 'distributor', 'guard_name' => 'web']);
    }

    public function test_a_new_user_receives_the_role_matching_their_role_column(): void
    {
        $user = User::factory()->collector()->create();

        $this->assertTrue($user->hasRole('collector'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_a_users_role_can_never_change(): void
    {
        $user = User::factory()->retailer()->create();

        $this->expectException(LogicException::class);

        $user->update(['role' => UserRole::Admin]);
    }

    public function test_users_get_a_ulid_public_id_that_is_the_route_key(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUlid($user->public_id));
        $this->assertSame('public_id', $user->getRouteKeyName());
        $this->assertIsInt($user->id);
    }

    public function test_the_role_middleware_allows_only_the_named_role(): void
    {
        Sanctum::actingAs(User::factory()->collector()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertOk();
    }

    public function test_the_role_middleware_rejects_blocked_accounts(): void
    {
        Sanctum::actingAs(User::factory()->admin()->blocked()->create());

        $this->getJson('/api/v1/_test/admin-only')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_a_limited_admin_passes_only_the_permissions_granted(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->givePermissionTo(AdminPermission::PickupsManage->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/_test/pickups')->assertOk();
        $this->getJson('/api/v1/_test/devices')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_super_admin_passes_every_permission_check(): void
    {
        Sanctum::actingAs(User::factory()->admin(super: true)->create());

        $this->getJson('/api/v1/_test/devices')->assertOk();
    }

    public function test_permission_presets_reference_only_real_permissions(): void
    {
        foreach (AdminPermissionPreset::cases() as $preset) {
            $this->assertNotEmpty($preset->permissions());
            foreach ($preset->permissions() as $permission) {
                $this->assertContains($permission->value, AdminPermission::values());
            }
            $this->assertNotContains(AdminPermission::PermissionsManage, $preset->permissions(), 'Only super admins manage permissions');
        }
    }
}
