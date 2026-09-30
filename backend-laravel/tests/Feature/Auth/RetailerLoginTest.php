<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AppClient;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use App\Models\PersonalAccessToken;
use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RetailerLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Retailer@Pass1';

    public function test_a_shop_owner_logs_in_and_gets_a_30_day_token(): void
    {
        $owner = $this->member();

        $response = $this->login('9812000004')->assertOk()
            ->assertJsonPath('data.user.role', 'retailer')
            ->assertJsonPath('data.user.shop.shop_role', 'OWNER')
            ->assertJsonPath('data.user.shop.id', $owner->retailer->public_id);

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($token);
        $this->assertSame(['retailer'], $token->abilities);
        $this->assertEqualsWithDelta(now()->addDays(30)->getTimestamp(), $token->expires_at->getTimestamp(), 5);
    }

    public function test_staff_log_in_but_cannot_spend(): void
    {
        $this->member(staff: true);

        $this->login('9812000004')->assertOk()
            ->assertJsonPath('data.user.shop.shop_role', 'STAFF')
            ->assertJsonPath('data.user.shop.can_spend', false)
            ->assertJsonPath('data.user.shop.can_confirm', true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everydayFormats(): array
    {
        return [
            'plain' => ['9812000004'],
            'spaced with +91' => ['+91 98120 00004'],
            'trunk zero' => ['098120 00004'],
            'dashes' => ['91-98120-00004'],
        ];
    }

    #[DataProvider('everydayFormats')]
    public function test_everyday_mobile_formats_reach_the_same_account(string $typed): void
    {
        $this->member();

        $this->login($typed)->assertOk();
    }

    public function test_a_wrong_password_and_an_unknown_mobile_get_the_same_answer(): void
    {
        $this->member();

        $wrong = $this->login('9812000004', 'not-the-password')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $unknown = $this->login('9812999999')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->assertSame($wrong->json('message'), $unknown->json('message'));
        $this->assertSame(2, AuditLog::query()->where('action', 'AUTH.LOGIN_FAILED')->count());
    }

    public function test_ten_wrong_passwords_lock_the_account_for_fifteen_minutes(): void
    {
        $this->member();
        $service = app(LoginService::class);

        for ($i = 0; $i < 10; $i++) {
            try {
                $service->login('9812000004', 'wrong', AppClient::Retailer, Request::create('/'));
            } catch (ApiException) {
                // expected
            }
        }

        try {
            $service->login('9812000004', self::PASSWORD, AppClient::Retailer, Request::create('/'));
            $this->fail('A locked account accepted the right password.');
        } catch (ApiException $e) {
            $this->assertSame('ACCOUNT_LOCKED', $e->errorCode);
            $this->assertStringContainsString('IST', $e->getMessage());
        }
        $this->assertSame(1, AuditLog::query()->where('action', 'AUTH.ACCOUNT_LOCKED')->count());

        $this->travel(16)->minutes();

        $this->assertNotSame('', $service->login('9812000004', self::PASSWORD, AppClient::Retailer, Request::create('/'))->plainText);
    }

    public function test_the_login_endpoint_is_rate_limited(): void
    {
        $this->member();

        for ($i = 0; $i < 5; $i++) {
            $this->login('9812000004', 'wrong')->assertUnauthorized();
        }

        $this->login('9812000004', 'wrong')->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_a_blocked_user_is_refused_only_after_the_right_password(): void
    {
        $this->member(userState: ['status' => 'BLOCKED']);

        $this->login('9812000004', 'wrong')->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');
        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_BLOCKED');
    }

    public function test_a_blocked_shop_cannot_log_in(): void
    {
        $this->member(retailerState: ['status' => 'BLOCKED']);

        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'RETAILER_BLOCKED');
    }

    public function test_an_inactive_shop_login_cannot_log_in(): void
    {
        $this->member(membershipState: ['status' => 'INACTIVE']);

        $this->login('9812000004')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_admins_and_collectors_cannot_use_the_retailer_app(): void
    {
        User::factory()->admin()->create(['mobile' => '+919812000001', 'password' => self::PASSWORD]);
        User::factory()->collector()->create(['mobile' => '+919812000003', 'password' => self::PASSWORD]);

        $this->login('9812000001')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->login('9812000003')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_successful_login_is_audited_and_the_password_never_stored(): void
    {
        $owner = $this->member();

        $this->login('9812000004')->assertOk();

        $log = AuditLog::query()->where('action', 'AUTH.LOGIN')->sole();
        $this->assertSame($owner->user->id, $log->actor_user_id);
        $this->assertSame(0, AuditLog::query()->where('before', 'like', '%'.self::PASSWORD.'%')
            ->orWhere('after', 'like', '%'.self::PASSWORD.'%')->orWhere('meta', 'like', '%'.self::PASSWORD.'%')->count());
    }

    /**
     * @param  array<string, mixed>  $userState
     * @param  array<string, mixed>  $retailerState
     * @param  array<string, mixed>  $membershipState
     */
    private function member(bool $staff = false, array $userState = [], array $retailerState = [], array $membershipState = []): RetailerUser
    {
        $factory = RetailerUser::factory()
            ->for(Retailer::factory()->state($retailerState))
            ->for(User::factory()->retailer()->state(array_merge(['mobile' => '+919812000004', 'password' => self::PASSWORD], $userState)))
            ->state($membershipState);

        return ($staff ? $factory->staff() : $factory)->create();
    }

    private function login(string $mobile, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['mobile' => $mobile, 'password' => $password, 'app' => 'retailer']);
    }
}
