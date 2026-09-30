<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Territory;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /** A block around central Rohtak, as [lat, lng] corners. */
    private const ROHTAK = [[28.86, 76.56], [28.86, 76.66], [28.92, 76.66], [28.92, 76.56]];

    public function definition(): array
    {
        return [
            'territory_id' => Territory::factory(),
            'name' => 'Ward '.fake()->unique()->numberBetween(1, 9999),
            'code' => 'Z-'.fake()->unique()->bothify('####??'),
            'boundary' => Zone::polygonExpression(self::ROHTAK),
            'boundary_geojson' => Zone::geoJson(self::ROHTAK),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public function ring(array $ring): static
    {
        return $this->state(fn (): array => [
            'boundary' => Zone::polygonExpression($ring),
            'boundary_geojson' => Zone::geoJson($ring),
        ]);
    }
}
