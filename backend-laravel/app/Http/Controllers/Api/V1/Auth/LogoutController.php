<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $audit->record('AUTH.LOGOUT', $user);

        return ApiResponse::success(null, 'Logged out.');
    }
}
