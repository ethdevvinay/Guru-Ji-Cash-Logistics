<?php

declare(strict_types=1);

namespace App\Services\Geo;

use App\Enums\RecordStatus;
use App\Models\Zone;

final class ZoneResolver
{
    /** The active zone containing the point, or null. POINT(x, y) takes longitude first. */
    public function resolve(float $lat, float $lng): ?Zone
    {
        return Zone::query()
            ->where('status', RecordStatus::Active->value)
            ->whereRaw('ST_Contains(boundary, POINT(?, ?))', [$lng, $lat])
            ->orderBy('id')
            ->first();
    }
}
