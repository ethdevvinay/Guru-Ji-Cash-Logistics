<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use App\Support\Settings\Settings;
use Illuminate\Http\JsonResponse;

final class MetaController
{
    public function __invoke(Settings $settings): JsonResponse
    {
        return ApiResponse::success([
            'api_version' => 'v1',
            'server_time' => ApiResponse::serverTime(),
            'min_app_version' => [
                'collector' => $settings->get('min_app_version_collector'),
                'retailer' => $settings->get('min_app_version_retailer'),
            ],
            'denominations_paise' => $settings->get('enabled_denominations_paise'),
            'broadcast_window_seconds' => $settings->get('broadcast_window_seconds'),
            'geofence_radius_m' => config('cms.geofence_radius_m'),
        ]);
    }
}
