<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AppClient;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\Auth\LoginService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __invoke(LoginRequest $request, LoginService $login): JsonResponse
    {
        $issued = $login->login(
            $request->string('mobile')->toString(),
            $request->string('password')->toString(),
            AppClient::from($request->string('app')->toString()),
            $request,
        );

        return ApiResponse::success([
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
            'user' => (new UserProfileResource($issued->user))->resolve($request),
        ], 'Logged in.');
    }
}
