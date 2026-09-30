<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Enums\RetailerStatus;
use App\Models\Retailer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retailer>
 */
class RetailerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retailer_code' => 'RET-'.fake()->unique()->numerify('#####'),
            'shop_name' => fake()->company().' Digital Store',
            'owner_name' => fake()->name(),
            'mobile' => '+91'.fake()->unique()->numerify('8#########'),
            'address' => fake()->streetAddress(),
            'city' => 'Rohtak',
            'pincode' => '124001',
            'lat' => fake()->latitude(28.86, 28.92),
            'lng' => fake()->longitude(76.56, 76.66),
            'kyc_status' => KycStatus::Pending,
            'status' => RetailerStatus::Active,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => RetailerStatus::Blocked]);
    }
}
