<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\ApproveDeviceController;
use App\Http\Controllers\Api\V1\Admin\RevokeDeviceController;
use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\DeviceStatusController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterDeviceController;
use App\Http\Controllers\Api\V1\Auth\RotateTokenController;
use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('meta', MetaController::class)->name('meta');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', LoginController::class)->middleware('throttle:auth-login')->name('login');

        Route::middleware(['auth:sanctum', 'ability:retailer,collector', 'device.signed', 'password.changed'])->group(function (): void {
            Route::get('me', MeController::class)->name('me');
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('token/rotate', RotateTokenController::class)->name('token.rotate');
            Route::post('password/change', ChangePasswordController::class)->name('password.change');
        });

        Route::middleware(['auth:sanctum', 'ability:device:register', 'role:collector'])->prefix('device')->name('device.')->group(function (): void {
            Route::post('register', RegisterDeviceController::class)->middleware('throttle:device-register')->name('register');
            Route::get('status', DeviceStatusController::class)->name('status');
        });
    });

    Route::middleware(['auth:sanctum', 'role:admin', 'password.changed'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::post('devices/{device}/approve', ApproveDeviceController::class)->middleware('permission:devices.manage')->name('devices.approve');
        Route::post('devices/{device}/revoke', RevokeDeviceController::class)->middleware('permission:devices.manage')->name('devices.revoke');
    });
});
