<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\TerritoryType;
use App\Models\Territory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territory>
 */
class TerritoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => TerritoryType::City,
            'name' => 'Rohtak',
            'code' => 'T-'.fake()->unique()->bothify('####??'),
            'status' => RecordStatus::Active,
        ];
    }
}
