<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminPanelController;
use Illuminate\Support\Facades\Route;

// Redirect root to Admin Dashboard
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication
Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

// Protected Admin Panel Routes
Route::prefix('admin')->middleware(['auth', 'role:ADMIN'])->group(function () {
    Route::get('/dashboard', [AdminPanelController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/collectors', [AdminPanelController::class, 'collectors'])->name('admin.collectors.index');
    Route::get('/retailers', [AdminPanelController::class, 'retailers'])->name('admin.retailers.index');
    Route::get('/pickups', [AdminPanelController::class, 'pickups'])->name('admin.pickups.index');
    Route::get('/operations/map', [AdminPanelController::class, 'liveMap'])->name('admin.operations.map');
    Route::get('/territories', [AdminPanelController::class, 'territories'])->name('admin.territories.index');
    Route::get('/finances/wallets', [AdminPanelController::class, 'wallets'])->name('admin.finances.wallets');
    Route::get('/vault', [AdminPanelController::class, 'vault'])->name('admin.vault.index');
    Route::get('/finances/reconciliation', [AdminPanelController::class, 'reconciliation'])->name('admin.finances.reconciliation');
    Route::get('/finances/penalties', [AdminPanelController::class, 'penalties'])->name('admin.finances.penalties');
    Route::get('/communications/broadcasts', [AdminPanelController::class, 'broadcasts'])->name('admin.communications.broadcasts');
    Route::get('/reports', [AdminPanelController::class, 'reports'])->name('admin.reports.index');
    Route::get('/audit', [AdminPanelController::class, 'auditLogs'])->name('admin.audit.index');
    Route::get('/settings', [AdminPanelController::class, 'settings'])->name('admin.settings.index');
});
