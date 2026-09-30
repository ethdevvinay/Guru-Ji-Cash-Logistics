<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_exposes_what_the_apps_need_at_start_up(): void
    {
        app(Settings::class)->set('min_app_version_collector', '1.2.0');

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('data.min_app_version.collector', '1.2.0')
            ->assertJsonPath('data.min_app_version.retailer', '1.0.0')
            ->assertJsonPath('data.denominations_paise', [50000, 20000, 10000, 5000, 2000, 1000])
            ->assertJsonPath('data.broadcast_window_seconds', 90)
            ->assertJsonPath('data.geofence_radius_m', 100);
    }
}
