<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\RevokeDeviceRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RevokeDeviceController
{
    public function __invoke(RevokeDeviceRequest $request, Device $device, DeviceBindingService $binding): JsonResponse
    {
        $admin = $request->user();
        assert($admin instanceof User);
        $device = $binding->revoke($device, $admin, $request->string('reason')->toString());

        return ApiResponse::success(['device' => ['id' => $device->public_id, 'status' => $device->status->value]], 'Phone revoked.');
    }
}
