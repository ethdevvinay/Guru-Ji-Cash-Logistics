<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RetailerMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'retailer.member'])->get('/api/v1/_test/shop', fn (Request $request) => ApiResponse::success([
            'shop' => $request->attributes->get('retailer_membership')->retailer->public_id,
        ]));
    }

    public function test_an_active_member_reaches_shop_endpoints_with_their_shop_attached(): void
    {
        $member = RetailerUser::factory()->staff()->create();
        Sanctum::actingAs($member->user, ['retailer']);

        $this->getJson('/api/v1/_test/shop')->assertOk()->assertJsonPath('data.shop', $member->retailer->public_id);
    }

    public function test_a_blocked_shop_is_refused(): void
    {
        $member = RetailerUser::factory()->for(Retailer::factory()->blocked())->create();
        Sanctum::actingAs($member->user, ['retailer']);

        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'RETAILER_BLOCKED');
    }

    public function test_an_inactive_membership_and_other_roles_are_refused(): void
    {
        Sanctum::actingAs(RetailerUser::factory()->inactive()->create()->user, ['retailer']);
        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

        Sanctum::actingAs(User::factory()->collector()->create(), ['collector']);
        $this->getJson('/api/v1/_test/shop')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }
}
