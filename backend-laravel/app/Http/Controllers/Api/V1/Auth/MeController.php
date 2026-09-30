<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\UserProfileResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success((new UserProfileResource($request->user()))->resolve($request));
    }
}
