<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\ShopRole;
use App\Models\Retailer;
use App\Models\RetailerUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetailerUser>
 */
class RetailerUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retailer_id' => Retailer::factory(),
            'user_id' => User::factory()->retailer(),
            'shop_role' => ShopRole::Owner,
            'can_request' => true,
            'can_confirm' => true,
            'can_spend' => true,
            'can_manage_staff' => true,
            'status' => RecordStatus::Active,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (): array => [
            'shop_role' => ShopRole::Staff,
            'can_spend' => false,
            'can_manage_staff' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::Inactive]);
    }
}
