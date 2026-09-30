<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceBindingService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApproveDeviceController
{
    public function __invoke(Request $request, Device $device, DeviceBindingService $binding): JsonResponse
    {
        $admin = $request->user();
        assert($admin instanceof User);
        $device = $binding->approve($device, $admin);

        return ApiResponse::success(['device' => ['id' => $device->public_id, 'status' => $device->status->value]], 'Phone approved.');
    }
}
