<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * User Login (Admin, Collector, Retailer).
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string', // Email or Mobile
            'password' => 'required|string',
            'device_uuid' => 'nullable|string',
            'device_model'=> 'nullable|string',
        ]);

        $login = $request->input('login');
        $user = User::where('mobile', $login)
            ->orWhere('email', $login)
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid mobile/email or password.',
                'data' => null,
                'errors' => ['credentials' => 'Authentication failed.'],
            ], 401);
        }

        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is ' . strtolower($user->status) . '. Please contact Administrator.',
                'data' => null,
                'errors' => ['status' => 'Account inactive.'],
            ], 403);
        }

        // Hardware device binding check for Collector
        if ($user->isCollector() && $request->filled('device_uuid')) {
            $device = $user->devices()
                ->where('device_uuid', $request->input('device_uuid'))
                ->where('is_active', true)
                ->first();

            if (!$device) {
                // Auto-register first device or require admin approval
                $existingCount = $user->devices()->count();
                $device = Device::create([
                    'user_id'      => $user->id,
                    'device_uuid'  => $request->input('device_uuid'),
                    'device_model' => $request->input('device_model', 'Unknown Android'),
                    'os_version'   => $request->input('os_version', 'Android'),
                    'app_version'  => $request->input('app_version', '1.0.0'),
                    'is_verified'  => ($existingCount === 0), // Auto-verify first device
                    'is_active'    => true,
                    'last_active_at' => now(),
                ]);
            } else {
                $device->update(['last_active_at' => now()]);
            }
        }

        // Generate Sanctum Bearer Token
        $token = $user->createToken('auth-token-' . $user->role)->plainTextToken;

        // Log audit event
        AuditLogService::log('LOGIN', 'User', $user->id, null, ['role' => $user->role], $request);

        $profile = [
            'id'     => $user->id,
            'name'   => $user->name,
            'email'  => $user->email,
            'mobile' => $user->mobile,
            'role'   => $user->role,
            'status' => $user->status,
        ];

        if ($user->isCollector() && $user->collector) {
            $profile['collector'] = [
                'id'             => $user->collector->id,
                'collector_code' => $user->collector->collector_code,
                'bike_number'    => $user->collector->bike_number,
                'current_float'  => $user->collector->current_float_paise / 100,
                'float_limit'    => $user->collector->float_limit_paise / 100,
                'duty_status'    => $user->collector->duty_status,
                'zone_id'        => $user->collector->zone_id,
            ];
        }

        if ($user->isRetailer() && $user->retailer) {
            $profile['retailer'] = [
                'id'          => $user->retailer->id,
                'shop_name'   => $user->retailer->shop_name,
                'owner_name'  => $user->retailer->owner_name,
                'address'     => $user->retailer->address,
                'latitude'    => $user->retailer->latitude,
                'longitude'   => $user->retailer->longitude,
                'wallet'      => $user->retailer->wallet ? ($user->retailer->wallet->balance_paise / 100) : 0,
                'outstanding' => $user->retailer->outstanding_paise / 100,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token'   => $token,
                'user'    => $profile,
            ],
            'errors' => [],
        ]);
    }

    /**
     * Get Current Profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['collector', 'retailer.wallet']);

        return response()->json([
            'success' => true,
            'message' => 'Profile retrieved successfully.',
            'data' => [
                'user' => $user,
            ],
            'errors' => [],
        ]);
    }

    /**
     * Bind or verify mobile device.
     */
    public function bindDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_uuid'  => 'required|string',
            'device_model' => 'required|string',
            'os_version'   => 'nullable|string',
            'app_version'  => 'nullable|string',
            'fcm_token'    => 'nullable|string',
        ]);

        $user = $request->user();

        $device = Device::updateOrCreate(
            [
                'user_id'     => $user->id,
                'device_uuid' => $request->input('device_uuid'),
            ],
            [
                'device_model' => $request->input('device_model'),
                'os_version'   => $request->input('os_version', 'Android'),
                'app_version'  => $request->input('app_version', '1.0.0'),
                'fcm_token'    => $request->input('fcm_token'),
                'is_active'    => true,
                'last_active_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device bound successfully.',
            'data' => ['device' => $device],
            'errors' => [],
        ]);
    }

    /**
     * Logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'data' => null,
            'errors' => [],
        ]);
    }
}
