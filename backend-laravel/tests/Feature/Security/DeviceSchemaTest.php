<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeviceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_collector_can_have_only_one_active_phone(): void
    {
        $user = User::factory()->collector()->create();
        Device::factory()->active()->for($user)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Device::factory()->active()->for($user)->create();
    }

    public function test_one_phone_cannot_be_active_for_two_collectors(): void
    {
        $first = Device::factory()->active()->create(['fingerprint_hash' => str_repeat('f', 64)]);

        $this->expectException(UniqueConstraintViolationException::class);

        Device::factory()->active()->create(['fingerprint_hash' => $first->fingerprint_hash]);
    }

    public function test_revoked_and_pending_phones_do_not_count_as_active(): void
    {
        $user = User::factory()->collector()->create();
        Device::factory()->revoked()->for($user)->create();
        Device::factory()->revoked()->for($user)->create();
        Device::factory()->pending()->for($user)->create();
        Device::factory()->active()->for($user)->create();

        $this->assertSame(4, Device::query()->where('user_id', $user->id)->count());
    }
}
