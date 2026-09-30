<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use App\Enums\RecordStatus;
use App\Models\Zone;
use App\Services\Geo\ZoneResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ZoneResolverTest extends TestCase
{
    use RefreshDatabase;

    /** A rectangle 0.10° of latitude by 0.20° of longitude: a swap of x and y cannot go unnoticed. */
    private const RING = [[28.80, 76.50], [28.80, 76.70], [28.90, 76.70], [28.90, 76.50]];

    public function test_it_finds_the_active_zone_containing_a_point(): void
    {
        $ward = Zone::factory()->ring(self::RING)->create(['name' => 'Ward 4']);

        $this->assertTrue(app(ZoneResolver::class)->resolve(28.85, 76.65)?->is($ward));
    }

    public function test_points_outside_every_zone_resolve_to_null(): void
    {
        Zone::factory()->ring(self::RING)->create();

        $this->assertNull(app(ZoneResolver::class)->resolve(28.95, 76.65));
        $this->assertNull(app(ZoneResolver::class)->resolve(28.85, 76.75));
    }

    public function test_inactive_zones_are_ignored(): void
    {
        Zone::factory()->ring(self::RING)->create(['status' => RecordStatus::Inactive]);

        $this->assertNull(app(ZoneResolver::class)->resolve(28.85, 76.65));
    }

    public function test_polygons_are_stored_as_longitude_then_latitude(): void
    {
        $zone = Zone::factory()->ring(self::RING)->create();

        $wkt = (string) DB::scalar('select ST_AsText(boundary) from zones where id = ?', [$zone->id]);

        $this->assertStringStartsWith('POLYGON((76.5 28.8,76.7 28.8', $wkt);
        $this->assertSame([76.5, 28.8], $zone->boundary_geojson['coordinates'][0][0]);
    }
}
