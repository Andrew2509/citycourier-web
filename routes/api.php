<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

// ─── Public API Routes ───────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register-kurir', [AuthController::class, 'registerKurir']);
Route::post('/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// WhatsApp Test Route
Route::post('/test-wa', function (\Illuminate\Http\Request $request, \App\Services\WhatsAppService $wa) {
    return $wa->sendMessage($request->phone, $request->message ?? 'Test message from City Courier');
});

Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::post('/auth/phone', [AuthController::class, 'loginWithPhone']);

// ─── Protected API Routes (Sanctum) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/available', [OrderController::class, 'available']);
    Route::patch('/update-status-order', [OrderController::class, 'updateStatus']);
});
