<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\RegisterDeviceRequest;
use App\Models\User;
use App\Services\Auth\DeviceRegistrationService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegisterDeviceController
{
    public function __invoke(RegisterDeviceRequest $request, DeviceRegistrationService $registrations): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        /** @var array{public_key: string, android_id: string, model?: string|null, manufacturer?: string|null, os_version?: string|null, app_version?: string|null, integrity_token?: string|null} $data */
        $data = $request->validated();
        $device = $registrations->register($user, $data);

        return ApiResponse::success(
            ['device' => ['id' => $device->public_id, 'status' => $device->status->value]],
            'Phone registered. Waiting for approval.',
            201,
        );
    }
}
