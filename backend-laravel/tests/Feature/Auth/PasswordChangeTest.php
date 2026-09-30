<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\RetailerUser;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Retailer@Pass1';

    private const NEW = 'Brand-New-Pass-42';

    public function test_a_forced_password_change_blocks_other_endpoints_until_done(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'ability:retailer,collector', 'password.changed'])
            ->get('/api/v1/_test/protected', fn () => ApiResponse::success());
        [$user, $token] = $this->retailer(mustChange: true);
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/_test/protected', $auth)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/auth/me', $auth)->assertOk()->assertJsonPath('data.must_change_password', true);

        $this->changePassword($auth)->assertOk();

        $this->getJson('/api/v1/_test/protected', $auth)->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_the_current_password_must_be_right(): void
    {
        [, $token] = $this->retailer();

        $this->changePassword(['Authorization' => "Bearer {$token}"], current: 'wrong')
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'current_password');
    }

    public function test_short_new_passwords_are_rejected(): void
    {
        [, $token] = $this->retailer();

        $this->postJson('/api/v1/auth/password/change', [
            'current_password' => self::OLD, 'password' => 'short', 'password_confirmation' => 'short',
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(422)->assertJsonPath('errors.0.field', 'password');
    }

    public function test_changing_the_password_signs_out_every_other_session(): void
    {
        [$user, $token] = $this->retailer();
        $other = $user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->changePassword(['Authorization' => "Bearer {$token}"])->assertOk();

        $this->assertTrue(Hash::check(self::NEW, (string) $user->fresh()->password));
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$other}"])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'AUTH.PASSWORD_CHANGED')->count());
    }

    /**
     * @return array{User, string}
     */
    private function retailer(bool $mustChange = false): array
    {
        $member = RetailerUser::factory()
            ->for(User::factory()->retailer()->state(['password' => self::OLD, 'must_change_password' => $mustChange]))
            ->create();

        return [$member->user, $member->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function changePassword(array $headers, string $current = self::OLD): TestResponse
    {
        return $this->postJson('/api/v1/auth/password/change', [
            'current_password' => $current, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ], $headers);
    }
}
