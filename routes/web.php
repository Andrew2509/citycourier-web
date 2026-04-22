<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// ─── Guest Routes ────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Auth Routes ─────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin Panel
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard.index');

        // User Management
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionController::class);
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);

        // Couriers
        Route::get('/couriers', [AdminController::class, 'couriers'])->name('couriers');
        Route::patch('/couriers/{courier}/verify', [AdminController::class, 'toggleVerifyCourier'])->name('couriers.verify');
        Route::patch('/couriers/{courier}/toggle-active', [AdminController::class, 'toggleActiveCourier'])->name('couriers.toggle-active');

        // Orders
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AdminController::class, 'orderDetail'])->name('orders.detail');

        // Settings / Provider
        Route::get('/settings/whatsapp', [\App\Http\Controllers\Admin\SettingController::class, 'whatsapp'])->name('settings.whatsapp');
        Route::post('/settings/whatsapp', [\App\Http\Controllers\Admin\SettingController::class, 'updateWhatsapp'])->name('settings.whatsapp.update');
        Route::post('/settings/whatsapp/test', [\App\Http\Controllers\Admin\SettingController::class, 'testWhatsapp'])->name('settings.whatsapp.test');

        // Drop Points
        Route::resource('drop-points', \App\Http\Controllers\Admin\DropPointController::class);
        Route::patch('drop-points/{drop_point}/toggle-active', [\App\Http\Controllers\Admin\DropPointController::class, 'toggleActive'])->name('drop-points.toggle-active');

        // Ci-Work Operational
        Route::prefix('ci-work')->name('ci-work.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CiWorkController::class, 'index'])->name('index');
            Route::get('/attendance', [\App\Http\Controllers\Admin\CiWorkController::class, 'attendance'])->name('attendance');
            Route::get('/tasks', [\App\Http\Controllers\Admin\CiWorkController::class, 'tasks'])->name('tasks');
            Route::get('/finance', [\App\Http\Controllers\Admin\CiWorkController::class, 'finance'])->name('finance');
            Route::post('/finance/withdrawal/{id}', [\App\Http\Controllers\Admin\CiWorkController::class, 'updateWithdrawalStatus'])->name('finance.withdrawal.update');
        });
    });
});

// Redirect root to safe location
Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
});
