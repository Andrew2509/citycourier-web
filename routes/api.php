<?php

use App\Http\Controllers\Api\AccountSecurityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\DanaController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\iPaymuWebhookController;

// Penanda versi deploy — publik & ringan (tanpa database).
Route::get('/health', [HealthController::class, 'index'])->name('api.health');

Route::get('/documentation', function () {
    return response(
        file_get_contents(public_path('docs/index.html')), 200
    )->header('Content-Type', 'text/html; charset=UTF-8');
})->name('api.documentation');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register-kurir', [AuthController::class, 'registerKurir']);
Route::post('/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::match(['get', 'post'], '/courier/dana/callback', [DanaController::class, 'callback']);

Route::match(['get', 'post'], '/dana/callback', [DanaController::class, 'callback']);

Route::post('/dana/webhook', [DanaController::class, 'webhookCallback']);
Route::post('/ovo/webhook', [App\Http\Controllers\Api\OvoController::class, 'webhook']);

Route::post('/test-wa', function (\Illuminate\Http\Request $request, App\Services\WhatsAppManager $whatsapp) {
    return $whatsapp->sendMessage($request->phone, $request->message ?? 'Test message from City Courier');
});

Route::post('/auth/google', [AuthController::class, 'loginWithGoogle']);
Route::post('/auth/phone', [AuthController::class, 'loginWithPhone']);

Route::post('/courier/request-phone-otp', [AuthController::class, 'requestCourierPhoneOtp']);
Route::post('/courier/verify-phone-otp', [AuthController::class, 'verifyCourierPhoneOtp']);

Route::get('/drop-points', [App\Http\Controllers\Api\DropPointController::class, 'index']);

Route::get('/couriers', [CourierController::class, 'index']);

Route::prefix('geocoding')->group(function () {
    Route::get('/search', [GeocodingController::class, 'search']);
    Route::get('/reverse', [GeocodingController::class, 'reverse']);
});

Route::match(['get', 'post'], '/shipping/match-region', function (\Illuminate\Http\Request $request, App\Services\RajaOngkirMatcherService $matcher) {
    $result = $matcher->match($request->all());
    return response()->json($result, !empty($result['disabled']) ? 503 : 200);
});

Route::get('/shipments/track/{number}', [App\Http\Controllers\Api\ShipmentController::class, 'track']);
Route::get('/shipments/{tracking_number}/tracking', [App\Http\Controllers\Api\ShipmentController::class, 'tracking']);
Route::post('/shipments/{identifier}/cancel', [App\Http\Controllers\Api\ShipmentController::class, 'cancel']);

Route::post('/payment/callback', [PaymentController::class, 'callback']);

Route::post('/upload-apk', [App\Http\Controllers\Api\ApkUploadController::class, 'upload']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/phone/request-otp', [AuthController::class, 'requestProfilePhoneOtp']);
    Route::post('/profile/phone/verify-otp', [AuthController::class, 'verifyProfilePhoneOtp']);

    // Keamanan Akun (PIN Transaksi, Biometrik, Perangkat Tepercaya, Log Keamanan)
    Route::prefix('security')->group(function () {
        Route::get('/overview', [AccountSecurityController::class, 'overview']);
        Route::post('/pin', [AccountSecurityController::class, 'updatePin']);
        Route::post('/pin/request-otp', [AccountSecurityController::class, 'requestPinOtp']);
        Route::post('/pin/verify', [AccountSecurityController::class, 'verifyPin']);
        Route::patch('/preferences', [AccountSecurityController::class, 'updatePreferences']);
        Route::get('/devices', [AccountSecurityController::class, 'devices']);
        Route::post('/devices/register', [AccountSecurityController::class, 'registerDevice']);
        Route::post('/devices/revoke-others', [AccountSecurityController::class, 'revokeOtherDevices']);
        Route::delete('/devices/{tokenId}', [AccountSecurityController::class, 'revokeDevice']);
        Route::get('/logs', [AccountSecurityController::class, 'logs']);
    });

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/available', [OrderController::class, 'available']);
    Route::get('/orders/active', [OrderController::class, 'active']);
    Route::match(['patch', 'post'], '/update-status-order', [OrderController::class, 'updateStatus']);
    Route::post('/orders/report-issue', [OrderController::class, 'reportIssue']);
    Route::post('/orders/cancel-assignment', [OrderController::class, 'cancelAssignment']);
    Route::post('/orders/{order}/cancel-assignment', [OrderController::class, 'cancelAssignment']);
    Route::post('/orders/reject', [OrderController::class, 'rejectOrder']);
    Route::post('/orders/{order}/reject', [OrderController::class, 'rejectOrder']);

    Route::prefix('courier')->group(function () {
        Route::get('/details', [App\Http\Controllers\Api\CourierController::class, 'details']);
        Route::get('/registration-status', [App\Http\Controllers\Api\CourierController::class, 'registrationStatus']);
        Route::put('/status', [App\Http\Controllers\Api\CourierController::class, 'updateStatus']);
        Route::put('/location', [App\Http\Controllers\Api\CourierController::class, 'updateLocation']);
        Route::get('/stats', [App\Http\Controllers\Api\CourierController::class, 'stats']);
        Route::get('/earnings', [App\Http\Controllers\Api\CourierController::class, 'earnings']);
        Route::get('/profile', [App\Http\Controllers\Api\CourierController::class, 'profile']);
        Route::post('/profile', [App\Http\Controllers\Api\CourierController::class, 'updateProfile']);
    });

    Route::get('/withdrawals', [App\Http\Controllers\Api\WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [App\Http\Controllers\Api\WithdrawalController::class, 'store']);

    // Keluarga API penarikan di bawah prefix /courier — dipakai aplikasi
    // kurir Flutter (createWithdrawal, detail, cancel, sinkronisasi status).
    Route::prefix('courier/withdrawals')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\WithdrawalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\WithdrawalController::class, 'store']);
        // Preview nama pemilik DANA tujuan (wajib SEBELUM /{id} agar
        // "destination-inquiry" tidak tertangkap parameter {id}).
        Route::get('/destination-inquiry', [App\Http\Controllers\Api\WithdrawalController::class, 'destinationInquiry']);
        // {id}: ID numerik atau idempotency_key (kontrak test/aplikasi lama).
        Route::get('/{id}', [App\Http\Controllers\Api\WithdrawalController::class, 'show']);
        Route::post('/{id}/cancel', [App\Http\Controllers\Api\WithdrawalController::class, 'cancel']);
        Route::get('/{id}/status', [App\Http\Controllers\Api\WithdrawalController::class, 'syncStatus']);
    });

    Route::prefix('shipping')->group(function () {
        Route::get('/provinces', [ShippingController::class, 'provinces']);
        Route::get('/cities', [ShippingController::class, 'cities']);
        Route::get('/districts', [ShippingController::class, 'districts']);
        Route::get('/subdistricts', [ShippingController::class, 'subdistricts']);
        Route::post('/cost', [ShippingController::class, 'calculateCost']);

        Route::post('/map/routing', [App\Http\Controllers\Api\MapApiController::class, 'routing']);
        Route::get('/map/autocomplete', [App\Http\Controllers\Api\MapApiController::class, 'autocomplete']);
        Route::post('/map/matrix', [App\Http\Controllers\Api\MapApiController::class, 'matrix']);
        Route::get('/map/styles/{name}.json', [App\Http\Controllers\Api\MapApiController::class, 'getStyle']);
    });

    Route::get('/shipments/stats', [App\Http\Controllers\Api\ShipmentController::class, 'stats']);
    Route::get('/shipments', [App\Http\Controllers\Api\ShipmentController::class, 'index']);
    Route::post('/shipments', [App\Http\Controllers\Api\ShipmentController::class, 'store']);
    Route::post('/shipments/{shipment}/confirm-payment', [App\Http\Controllers\Api\ShipmentController::class, 'confirmPayment']);
    Route::get('/shipments/{shipment}', [App\Http\Controllers\Api\ShipmentController::class, 'show']);

    Route::get('/addresses', [App\Http\Controllers\Api\SavedAddressController::class, 'index']);
    Route::post('/addresses', [App\Http\Controllers\Api\SavedAddressController::class, 'store']);
    Route::put('/addresses/{address}', [App\Http\Controllers\Api\SavedAddressController::class, 'update']);
    Route::delete('/addresses/{address}', [App\Http\Controllers\Api\SavedAddressController::class, 'destroy']);
    Route::patch('/addresses/{address}/favorite', [App\Http\Controllers\Api\SavedAddressController::class, 'toggleFavorite']);

    Route::prefix('payment')->group(function () {
        Route::get('/methods', [PaymentController::class, 'methods']);
        Route::post('/create', [PaymentController::class, 'create']);
        Route::get('/status', [PaymentController::class, 'status']);
        Route::post('/cancel', [PaymentController::class, 'cancel']);
        Route::get('/{paymentId}/status', [PaymentController::class, 'status'])->where('paymentId', '.*');
        Route::post('/{paymentId}/cancel', [PaymentController::class, 'cancel'])->where('paymentId', '.*');
    });

    Route::get('/courier/wallet', [WalletController::class, 'index']);
    Route::get('/courier/wallet/history', [WalletController::class, 'history']);
    Route::get('/courier/wallet/notifications', [WalletController::class, 'notifications']);
    Route::get('/courier/wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('/courier/wallet/transactions/{id}', [WalletController::class, 'transactionDetail']);
    Route::get('/courier/wallet/fee-config', [WalletController::class, 'feeConfig']);

    Route::get('/courier/dana/status', [DanaController::class, 'status']);
    Route::post('/courier/dana/connect', [DanaController::class, 'connect']);
    Route::post('/courier/dana/mock-connect', [DanaController::class, 'mockConnect']);
    Route::post('/courier/dana/disconnect', [DanaController::class, 'disconnect']);

    Route::post('/courier/dana/binding', [DanaController::class, 'binding']);
    Route::post('/courier/dana/unbind', [DanaController::class, 'disconnect']);
    Route::post('/courier/dana/rebind', [DanaController::class, 'reconnect']);
    Route::post('/courier/dana/verify', [DanaController::class, 'verify']);

    Route::post('/dana/bind/init', [DanaController::class, 'initBinding']);
    Route::post('/dana/bind/status', [DanaController::class, 'checkBindingStatus']);
});

Route::prefix('courier/ovo')->group(function () {
    Route::get('/status', [App\Http\Controllers\Api\OvoController::class, 'status']);
    Route::post('/connect', [App\Http\Controllers\Api\OvoController::class, 'connect']);
    Route::post('/binding', [App\Http\Controllers\Api\OvoController::class, 'connect']);
    Route::post('/update', [App\Http\Controllers\Api\OvoController::class, 'update']);
    Route::post('/unbind', [App\Http\Controllers\Api\OvoController::class, 'disconnect']);
    Route::post('/disconnect', [App\Http\Controllers\Api\OvoController::class, 'disconnect']);
    Route::post('/withdraw', [App\Http\Controllers\Api\OvoController::class, 'withdraw']);
    Route::get('/withdrawals/{id}/status', [App\Http\Controllers\Api\OvoController::class, 'syncStatus']);
});

Route::get('/settings', [App\Http\Controllers\Api\SettingController::class, 'index']);

/**
 * Webhook iPaymu (publik).
 * Menerima callback status disbursement dari iPaymu. Signature divalidasi
 * (IPAYMU_WEBHOOK_SECRET) atau diverifikasi ulang lewat inquiry resmi
 * sebelum status penarikan berubah — lihat iPaymuWebhookController.
 */
Route::post('/ipaymu/webhook', [iPaymuWebhookController::class, 'handle'])->name('ipaymu.webhook');
Route::post('/ipaymu/callback', [iPaymuWebhookController::class, 'handle'])->name('ipaymu.callback');

/**
 * Halaman kembali iPaymu Redirect Payment (publik, hanya informasi).
 * Status yang mengikat tetap dari webhook ber-signature + inquiry.
 */
Route::get('/ipaymu/return', [\App\Http\Controllers\Api\IpaymuReturnController::class, 'completed'])
    ->name('ipaymu.return');
Route::get('/ipaymu/cancel', [\App\Http\Controllers\Api\IpaymuReturnController::class, 'cancelled'])
    ->name('ipaymu.cancel');

/*
|--------------------------------------------------------------------------
| Payment & Wallet System v1 API
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function () {
    // Public signed gateway webhooks
    Route::post('/webhooks/xendit/payment', [\App\Http\Controllers\Api\V1\WebhookV1Controller::class, 'xenditPayment'])
        ->name('api.v1.webhooks.xendit.payment');
    Route::post('/webhooks/xendit/payout', [\App\Http\Controllers\Api\V1\WebhookV1Controller::class, 'xenditPayout'])
        ->name('api.v1.webhooks.xendit.payout');
    Route::post('/webhooks/doku', [\App\Http\Controllers\Api\V1\WebhookV1Controller::class, 'doku'])
        ->name('api.v1.webhooks.doku');

    Route::middleware('auth:sanctum')->group(function () {
        // Customer Wallet & Payment APIs
        Route::prefix('customer/wallet')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CustomerWalletController::class, 'show']);
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\CustomerWalletController::class, 'transactions']);
            Route::get('/transactions/{id}', [\App\Http\Controllers\Api\V1\CustomerWalletController::class, 'transactionDetail']);

            Route::get('/topups/methods', [\App\Http\Controllers\Api\V1\CustomerTopupController::class, 'methods']);
            Route::post('/topups', [\App\Http\Controllers\Api\V1\CustomerTopupController::class, 'store']);
            Route::get('/topups/{id}', [\App\Http\Controllers\Api\V1\CustomerTopupController::class, 'show']);
            Route::post('/topups/{id}/simulate-paid', [\App\Http\Controllers\Api\V1\CustomerTopupController::class, 'simulatePaid']);

            Route::post('/payments', [\App\Http\Controllers\Api\V1\CustomerWalletPaymentController::class, 'store']);

            Route::get('/withdrawals', [\App\Http\Controllers\Api\V1\CustomerWithdrawalController::class, 'index']);
            Route::post('/withdrawals', [\App\Http\Controllers\Api\V1\CustomerWithdrawalController::class, 'store']);

            Route::get('/refunds', [\App\Http\Controllers\Api\V1\CustomerRefundController::class, 'index']);
            Route::post('/refunds', [\App\Http\Controllers\Api\V1\CustomerRefundController::class, 'store']);

            Route::get('/destinations', [\App\Http\Controllers\Api\V1\CustomerDestinationController::class, 'index']);
            Route::post('/destinations', [\App\Http\Controllers\Api\V1\CustomerDestinationController::class, 'store']);
            Route::patch('/destinations/{id}', [\App\Http\Controllers\Api\V1\CustomerDestinationController::class, 'update']);
        });

        // Courier Wallet, Earnings & Auto-Payout APIs
        Route::prefix('courier/wallet')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'show']);
            Route::get('/transactions', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'transactions']);
            Route::get('/transactions/{id}', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'transactionDetail']);
            Route::get('/earnings', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'earnings']);
            Route::get('/payouts', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'payouts']);
            Route::get('/payouts/{id}', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'payoutDetail']);
            Route::get('/notifications', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'notifications']);
            Route::get('/destinations', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'destinations']);
            Route::post('/destinations', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'storeDestination']);
            Route::patch('/destinations/{id}/default', [\App\Http\Controllers\Api\V1\CourierWalletV1Controller::class, 'setDefaultDestination']);
        });
    });
});