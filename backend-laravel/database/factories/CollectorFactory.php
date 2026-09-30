<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CollectorStatus;
use App\Models\Collector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collector>
 */
class CollectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->collector(),
            'collector_code' => 'COL-'.fake()->unique()->numerify('####'),
            'employee_id' => 'EMP-'.fake()->unique()->numerify('#####'),
            'vehicle_type' => 'BIKE',
            'vehicle_number' => 'HR12'.strtoupper(fake()->bothify('??####')),
            'float_limit_paise' => 10000000,
            'status' => CollectorStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => CollectorStatus::Suspended]);
    }
}
