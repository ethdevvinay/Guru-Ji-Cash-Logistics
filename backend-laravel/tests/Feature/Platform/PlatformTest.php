<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlatformTest extends TestCase
{
    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_tests_run_on_mariadb_10_6_or_newer_with_utc_sessions(): void
    {
        $this->assertSame('mariadb', DB::connection()->getDriverName());

        $version = (string) DB::scalar('select version()');
        $this->assertStringContainsStringIgnoringCase('mariadb', $version);
        $this->assertSame(1, preg_match('/^(\d+\.\d+\.\d+)/', $version, $matches));
        $this->assertTrue(version_compare($matches[1], '10.6.0', '>='), "MariaDB {$version} is older than 10.6");

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', (string) DB::scalar('select @@session.time_zone'));
    }
}
