<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectorApiController;
use App\Http\Controllers\Api\RetailerApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 1. Authentication & Device Handshake (Brute Force Protected)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/device/bind', [AuthController::class, 'bindDevice']);
        });
    });

    // 2. Collector Mobile Suite (Hardware Bound & Role Guarded)
    Route::prefix('collector')->middleware(['auth:sanctum', 'role:COLLECTOR', 'device.bound', 'throttle:120,1'])->group(function () {
        Route::post('/duty/punch-in', [CollectorApiController::class, 'punchIn']);
        Route::post('/duty/punch-out', [CollectorApiController::class, 'punchOut']);
        Route::post('/gps/ping', [CollectorApiController::class, 'streamGps']);

        Route::get('/pickups/broadcasts', [CollectorApiController::class, 'getBroadcasts']);
        Route::post('/pickups/{id}/accept', [CollectorApiController::class, 'acceptJob']);
        Route::post('/pickups/{id}/verify-geofence', [CollectorApiController::class, 'verifyGeofence']);
        Route::post('/pickups/{id}/submit-cash', [CollectorApiController::class, 'submitCash']);

        Route::get('/float/meter', [CollectorApiController::class, 'getFloatMeter']);
        Route::post('/sos/trigger', [CollectorApiController::class, 'triggerSos'])->middleware('throttle:10,1');
    });

    // 3. Retailer Portal & Mobile App Suite
    Route::prefix('retailer')->middleware(['auth:sanctum', 'role:RETAILER'])->group(function () {
        Route::get('/dashboard', [RetailerApiController::class, 'getDashboard']);
        Route::post('/pickups/request', [RetailerApiController::class, 'createPickup']);
        Route::post('/pickups/create', [RetailerApiController::class, 'createPickup']);
        Route::get('/pickups/active', [RetailerApiController::class, 'getActivePickup']);
        Route::get('/pickups/{id}/collector-location', [RetailerApiController::class, 'getCollectorLocation']);
        Route::post('/pickups/{id}/cancel', [RetailerApiController::class, 'cancelPickup']);
        Route::post('/pickups/{id}/accept-and-confirm', [RetailerApiController::class, 'acceptAndConfirm']);
        Route::post('/pickups/{id}/confirm', [RetailerApiController::class, 'acceptAndConfirm']);

        Route::get('/wallet', [RetailerApiController::class, 'getWalletBalance']);
        Route::get('/wallet/balance', [RetailerApiController::class, 'getWalletBalance']);
        Route::get('/wallet/passbook', [RetailerApiController::class, 'getPassbook']);

        Route::post('/recharge', [RetailerApiController::class, 'executeRecharge'])->middleware('idempotent');
        Route::post('/recharge/execute', [RetailerApiController::class, 'executeRecharge'])->middleware('idempotent');
        Route::post('/bbps', [RetailerApiController::class, 'payBbpsBill'])->middleware('idempotent');
        Route::post('/bbps/pay-bill', [RetailerApiController::class, 'payBbpsBill'])->middleware('idempotent');

        Route::get('/broadcasts/active', [RetailerApiController::class, 'getBroadcasts']);
    });

    // 4. Admin Management APIs
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
        Route::get('/dashboard/kpis', [AdminApiController::class, 'getDashboardKpis']);
        Route::get('/radar/live-fleet', [AdminApiController::class, 'getLiveRadar']);
        Route::post('/penalties/{id}/waive', [AdminApiController::class, 'waivePenalty']);
        Route::post('/vault/handover', [AdminApiController::class, 'settleVaultHandover']);
        Route::post('/banks/deposits/allocate', [AdminApiController::class, 'allocateBankDeposit']);
        Route::post('/broadcasts', [AdminApiController::class, 'createBroadcast']);
    });

});
