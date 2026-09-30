<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use Database\Factories\ZoneFactory;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * An operational polygon such as "Ward 4" or "Rural East" (spec §11.7).
 * Stored as SRID-0 POLYGON with x = longitude and y = latitude.
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    protected $fillable = ['territory_id', 'name', 'code', 'boundary', 'boundary_geojson', 'color', 'status'];

    /** The raw WKB polygon is binary and never serialised. */
    protected $hidden = ['boundary'];

    protected function casts(): array
    {
        return ['boundary_geojson' => 'array', 'status' => RecordStatus::class];
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /**
     * SQL for a polygon built from [lat, lng] corners. Coordinates are formatted as numbers,
     * so the expression cannot carry injected SQL.
     *
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public static function polygonExpression(array $ring): Expression
    {
        $points = array_map(
            static fn (array $corner): string => sprintf('%.7F %.7F', $corner[1], $corner[0]),
            self::closedRing($ring),
        );

        return DB::raw(sprintf("ST_GeomFromText('POLYGON((%s))')", implode(', ', $points)));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return array{type: string, coordinates: list<list<array{0: float, 1: float}>>}
     */
    public static function geoJson(array $ring): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [array_map(static fn (array $corner): array => [$corner[1], $corner[0]], self::closedRing($ring))],
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function closedRing(array $ring): array
    {
        if (count($ring) < 3) {
            throw new InvalidArgumentException('A zone needs at least three corners.');
        }

        if ($ring[0] !== $ring[array_key_last($ring)]) {
            $ring[] = $ring[0];
        }

        return $ring;
    }
}
