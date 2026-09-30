<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class MetaController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'api_version' => 'v1',
            'server_time' => ApiResponse::serverTime(),
        ]);
    }
}
