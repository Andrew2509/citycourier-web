<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\DanaController;
use App\Http\Controllers\Api\WithdrawalController;


// ─── Public API Routes ───────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register-kurir', [AuthController::class, 'registerKurir']);
Route::post('/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// DANA callback (di-redirect oleh browser user setelah otorisasi di DANA App).
// Harus publik: DANA mengirim GET tanpa bearer token.
Route::match(['get', 'post'], '/courier/dana/callback', [DanaController::class, 'callback']);

// Alias publik sesuai PRD §34.
Route::match(['get', 'post'], '/dana/callback', [DanaController::class, 'callback']);

// DANA webhook (alias publik, sesuai panduan integrasi).
Route::post('/dana/webhook', [DanaController::class, 'webhookCallback']);

// WhatsApp Test Route
Route::post('/test-wa', function (\Illuminate\Http\Request $request, \App\Services\WhatsAppService $wa) {
    return $wa->sendMessage($request->phone, $request->message ?? 'Test message from City Courier');
});

Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::post('/auth/phone', [AuthController::class, 'loginWithPhone']);

// Drop Points
Route::get('/drop-points', [\App\Http\Controllers\Api\DropPointController::class, 'index']);

// ─── Geocoding (Public - Nominatim/Photon Proxy) ──────────
Route::prefix('geocoding')->group(function () {
    Route::get('/search', [GeocodingController::class, 'search']);
    Route::get('/reverse', [GeocodingController::class, 'reverse']);
});


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
        Route::get('/earnings', [\App\Http\Controllers\Api\CourierController::class, 'earnings']);
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
        
        // Map Proxy / Gatekeeper API
        Route::post('/map/routing', [\App\Http\Controllers\Api\MapApiController::class, 'routing']);
        Route::get('/map/autocomplete', [\App\Http\Controllers\Api\MapApiController::class, 'autocomplete']);
        Route::post('/map/matrix', [\App\Http\Controllers\Api\MapApiController::class, 'matrix']);
        Route::get('/map/styles/{name}.json', [\App\Http\Controllers\Api\MapApiController::class, 'getStyle']);
    });

    // Shipments (Request Pickup dari Flutter)
    Route::get('/shipments/stats', [\App\Http\Controllers\Api\ShipmentController::class, 'stats']);
    Route::get('/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'index']);
    Route::post('/shipments', [\App\Http\Controllers\Api\ShipmentController::class, 'store']);
    Route::post('/shipments/{shipment}/confirm-payment', [\App\Http\Controllers\Api\ShipmentController::class, 'confirmPayment']);
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

    // ─── Wallet & DANA Routes ───────────────────────────────────
    Route::get('/courier/wallet', [WalletController::class, 'index']);
    Route::get('/courier/wallet/transactions', [WalletController::class, 'transactions']);

    Route::get('/courier/dana/status', [DanaController::class, 'status']);
    Route::post('/courier/dana/connect', [DanaController::class, 'connect']);
    Route::post('/courier/dana/mock-connect', [DanaController::class, 'mockConnect']);
    Route::post('/courier/dana/disconnect', [DanaController::class, 'disconnect']);

    // Endpoint PRD §34: binding, unbind, rebind.
    Route::post('/courier/dana/binding', [DanaController::class, 'binding']);
    Route::post('/courier/dana/unbind', [DanaController::class, 'disconnect']);
    Route::post('/courier/dana/rebind', [DanaController::class, 'reconnect']);

    // Alias endpoint panduan integrasi DANA.
    Route::post('/dana/bind/init', [DanaController::class, 'initBinding']);
    Route::post('/dana/bind/status', [DanaController::class, 'checkBindingStatus']);

    Route::post('/courier/withdrawals', [WithdrawalController::class, 'store']);
    Route::get('/courier/withdrawals', [WithdrawalController::class, 'index']);
    Route::get('/courier/withdrawals/{id}', [WithdrawalController::class, 'show']);

});
