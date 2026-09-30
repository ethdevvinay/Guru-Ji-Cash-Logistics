<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\IssuedToken;
use App\Services\Auth\TokenIssuer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RotateTokenController
{
    public function __invoke(Request $request, TokenIssuer $tokens, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);
        $current = $user->currentAccessToken();

        if (! $current instanceof PersonalAccessToken) {
            throw new ApiException('FORBIDDEN', 'Only app sessions can be renewed.', 403);
        }

        $issued = DB::transaction(function () use ($user, $current, $tokens, $audit): IssuedToken {
            $issued = $tokens->reissue($user, $current);
            $audit->record('AUTH.TOKEN_ROTATED', $user);

            return $issued;
        });

        return ApiResponse::success([
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
        ], 'Session renewed.');
    }
}
