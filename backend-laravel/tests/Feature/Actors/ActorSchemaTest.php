<?php

declare(strict_types=1);

namespace Tests\Feature\Actors;

use App\Models\Collector;
use App\Models\Retailer;
use App\Models\RetailerUser;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActorSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_can_have_only_one_active_owner(): void
    {
        $owner = RetailerUser::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        RetailerUser::factory()->for($owner->retailer)->create();
    }

    public function test_a_new_owner_is_allowed_once_the_old_owner_is_inactive(): void
    {
        $old = RetailerUser::factory()->inactive()->create();

        $new = RetailerUser::factory()->for($old->retailer)->create();

        $this->assertTrue($old->retailer->owner->is($new));
    }

    public function test_a_shop_can_have_many_staff_logins(): void
    {
        $owner = RetailerUser::factory()->create();

        RetailerUser::factory()->count(3)->staff()->for($owner->retailer)->create();

        $this->assertSame(4, $owner->retailer->members()->count());
        $this->assertTrue($owner->retailer->owner->is($owner));
    }

    public function test_one_login_belongs_to_exactly_one_shop(): void
    {
        $membership = RetailerUser::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        RetailerUser::factory()->for($membership->user)->create();
    }

    public function test_staff_cannot_spend_by_default(): void
    {
        $staff = RetailerUser::factory()->staff()->create();

        $this->assertFalse($staff->can_spend);
        $this->assertFalse($staff->can_manage_staff);
        $this->assertTrue($staff->can_confirm);
    }

    public function test_collector_codes_are_unique(): void
    {
        Collector::factory()->create(['collector_code' => 'COL-104']);

        $this->expectException(UniqueConstraintViolationException::class);

        Collector::factory()->create(['collector_code' => 'COL-104']);
    }

    public function test_the_retailer_penalty_override_must_stay_between_50_and_200_rupees(): void
    {
        $this->expectException(QueryException::class);

        Retailer::factory()->create(['penalty_amount_paise' => 30000]);
    }

    public function test_the_retailer_collector_share_cannot_exceed_the_penalty(): void
    {
        $this->expectException(QueryException::class);

        Retailer::factory()->create(['penalty_amount_paise' => 5000, 'penalty_collector_share_paise' => 6000]);
    }

    public function test_a_collector_login_has_one_collector_profile(): void
    {
        $collector = Collector::factory()->create();

        $this->assertTrue($collector->user->collector->is($collector));
        $this->assertSame('collector', $collector->user->role->value);
    }
}
