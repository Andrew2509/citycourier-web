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
    });
});

// Redirect root to safe location
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
});
