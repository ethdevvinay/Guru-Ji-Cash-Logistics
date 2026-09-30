<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceBound
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Device binding is mandatory for Collector role
        if ($user && $user->isCollector()) {
            $deviceUuid = $request->header('X-Device-Id');

            if (!$deviceUuid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hardware device identifier missing.',
                    'data' => null,
                    'errors' => ['device' => 'X-Device-Id header is required.'],
                ], Response::HTTP_PRECONDITION_REQUIRED);
            }

            $device = $user->devices()
                ->where('device_uuid', $deviceUuid)
                ->where('is_active', true)
                ->first();

            if (!$device) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not authorized. Please register this device with an Administrator.',
                    'data' => null,
                    'errors' => ['device' => 'Hardware binding mismatch.'],
                ], Response::HTTP_FORBIDDEN);
            }

            // Update device heartbeat
            $device->update(['last_active_at' => now()]);
            $request->attributes->set('active_device', $device);
        }

        return $next($request);
    }
}
