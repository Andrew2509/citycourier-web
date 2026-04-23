<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
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

// Drop Points
Route::get('/drop-points', [\App\Http\Controllers\Api\DropPointController::class, 'index']);

// Tracking (Public)
Route::get('/shipments/track/{number}', [\App\Http\Controllers\Api\ShipmentController::class, 'track']);

// Komerce Payment Callback (PUBLIC - tidak butuh auth, dipanggil Komerce)
Route::post('/payment/callback', [PaymentController::class, 'callback']);

// ─── Protected API Routes (Sanctum) ─────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/available', [OrderController::class, 'available']);
    Route::get('/orders/active', [OrderController::class, 'active']);
    Route::patch('/update-status-order', [OrderController::class, 'updateStatus']);

    // Courier Operations
    Route::prefix('courier')->group(function () {
        Route::get('/details', [\App\Http\Controllers\Api\CourierController::class, 'details']);
        Route::put('/status', [\App\Http\Controllers\Api\CourierController::class, 'updateStatus']);
        Route::put('/location', [\App\Http\Controllers\Api\CourierController::class, 'updateLocation']);
        Route::get('/stats', [\App\Http\Controllers\Api\CourierController::class, 'stats']);
        Route::get('/profile', [\App\Http\Controllers\Api\CourierController::class, 'profile']);
    });

    // Withdrawals
    Route::get('/withdrawals', [\App\Http\Controllers\Api\WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [\App\Http\Controllers\Api\WithdrawalController::class, 'store']);

    // Shipping (RajaOngkir)
    Route::prefix('shipping')->group(function () {
        Route::get('/provinces', [ShippingController::class, 'provinces']);
        Route::get('/cities', [ShippingController::class, 'cities']);
        Route::get('/districts', [ShippingController::class, 'districts']);
        Route::get('/subdistricts', [ShippingController::class, 'subdistricts']);
        Route::post('/cost', [ShippingController::class, 'calculateCost']);
    });

    // Shipments (Request Pickup dari Flutter)
    Route::get('/shipments/stats', [\App\Http\Controllers\Api\ShipmentController::class, 'stats']);
    Route::get('/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'index']);
    Route::post('/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'store']);
    Route::get('/shipments/{shipment}', [\App\Http\Controllers\Api\ShipmentController::class, 'show']);

    // Saved Addresses (Alamat Favorit)
    Route::get('/addresses', [\App\Http\Controllers\Api\SavedAddressController::class, 'index']);
    Route::post('/addresses', [\App\Http\Controllers\Api\SavedAddressController::class, 'store']);
    Route::put('/addresses/{address}', [\App\Http\Controllers\Api\SavedAddressController::class, 'update']);
    Route::delete('/addresses/{address}', [\App\Http\Controllers\Api\SavedAddressController::class, 'destroy']);
    Route::patch('/addresses/{address}/favorite', [\App\Http\Controllers\Api\SavedAddressController::class, 'toggleFavorite']);

    // ─── Payment (Komerce Payment API) ──────────────────────────
    Route::prefix('payment')->group(function () {
        Route::get('/methods', [PaymentController::class, 'methods']);
        Route::post('/create', [PaymentController::class, 'create']);
        Route::get('/status', [PaymentController::class, 'status']);
        Route::post('/cancel', [PaymentController::class, 'cancel']);
        Route::get('/{paymentId}/status', [PaymentController::class, 'status'])->where('paymentId', '.*');
        Route::post('/{paymentId}/cancel', [PaymentController::class, 'cancel'])->where('paymentId', '.*');
    });
});
