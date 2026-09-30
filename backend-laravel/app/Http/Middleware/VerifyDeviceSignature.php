<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\DeviceSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every collector request must come from the phone its token is bound to and carry
 * that phone's signature. Retailers and admins pass through.
 */
final class VerifyDeviceSignature
{
    public function __construct(private readonly DeviceSignatureVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== UserRole::Collector) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        $device = $token instanceof PersonalAccessToken && $token->device_id !== null
            ? Device::query()->find($token->device_id)
            : null;

        if ($device === null
            || $device->status !== DeviceStatus::Active
            || $device->user_id !== $user->id
            || $device->public_id !== $request->header('X-Device-Id')) {
            throw new ApiException('DEVICE_NOT_BOUND', 'This phone is not registered for your account.', 403);
        }

        $this->verifier->verify($request, $device);

        $request->attributes->set('device', $device);
        $request->attributes->set('device_id', $device->id);

        if ($device->last_seen_at === null || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
