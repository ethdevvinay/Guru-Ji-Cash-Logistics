<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\UserProfileResource;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\DeviceSignatureVerifier;
use App\Services\Auth\IssuedToken;
use App\Services\Auth\TokenIssuer;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Polled by the phone after registering. Signed, so only the phone holding the key can collect the session. */
final class DeviceStatusController
{
    public function __invoke(Request $request, DeviceSignatureVerifier $signatures, TokenIssuer $tokens, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $device = Device::query()
            ->where('public_id', (string) $request->header('X-Device-Id', ''))
            ->where('user_id', $user->id)
            ->first();

        if ($device === null) {
            throw new ApiException('NOT_FOUND', 'This phone has not been registered.', 404);
        }

        $signatures->verify($request, $device);

        if ($device->status !== DeviceStatus::Active) {
            return ApiResponse::success(['status' => $device->status->value, 'reason' => $device->revoke_reason]);
        }

        $issued = DB::transaction(function () use ($user, $device, $tokens, $request, $audit): IssuedToken {
            $issued = $tokens->issueCollectorToken($user, $device);

            $registration = $user->currentAccessToken();
            if ($registration instanceof PersonalAccessToken) {
                $registration->delete();
            }

            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
            $audit->record('AUTH.LOGIN', $user, null, null, ['app' => 'collector', 'via' => 'device_approval', 'device' => $device->public_id]);

            return $issued;
        });

        return ApiResponse::success([
            'status' => DeviceStatus::Active->value,
            'token' => $issued->plainText,
            'expires_at' => ApiResponse::formatTime($issued->expiresAt),
            'user' => (new UserProfileResource($user))->resolve($request),
        ], 'Phone approved.');
    }
}
