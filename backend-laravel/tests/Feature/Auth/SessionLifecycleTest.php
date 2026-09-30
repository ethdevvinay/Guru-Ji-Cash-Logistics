<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_the_profile_of_the_token_owner(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.id', $owner->user->public_id)
            ->assertJsonPath('data.shop.name', $owner->retailer->shop_name)
            ->assertJsonMissingPath('data.password');
    }

    public function test_logout_revokes_the_token(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertOk();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_an_expired_session_says_token_expired(): void
    {
        $owner = RetailerUser::factory()->create();
        $token = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30))->plainTextToken;

        $this->travel(31)->days();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TOKEN_EXPIRED');
    }

    public function test_missing_and_garbage_tokens_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer 1|garbage'])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_a_registration_only_token_cannot_use_normal_endpoints(): void
    {
        $collector = User::factory()->collector()->create();
        $token = $collector->createToken('device-registration', ['device:register'], now()->addMinutes(10))->plainTextToken;

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_the_last_ip_that_used_a_token_is_recorded(): void
    {
        $owner = RetailerUser::factory()->create();
        $new = $owner->user->createToken('retailer-app', ['retailer'], now()->addDays(30));

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$new->plainTextToken}"])->assertOk();

        $this->assertSame('127.0.0.1', PersonalAccessToken::query()->findOrFail($new->accessToken->getKey())->last_used_ip);
    }
}
