<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('meta', MetaController::class)->name('meta');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', LoginController::class)->middleware('throttle:auth-login')->name('login');

        Route::middleware(['auth:sanctum', 'ability:retailer,collector', 'password.changed'])->group(function (): void {
            Route::get('me', MeController::class)->name('me');
            Route::post('logout', LogoutController::class)->name('logout');
            Route::post('password/change', ChangePasswordController::class)->name('password.change');
        });
    });
});
