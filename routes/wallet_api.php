<?php

use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\DanaController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wallet & DANA API Routes
|--------------------------------------------------------------------------
|
| PRD §36: API Backend endpoints
| These routes should be added inside the auth:sanctum middleware group
| in your main routes/api.php file.
|
*/

// ─── Wallet Routes ─────────────────────────────────────────────
Route::get('/courier/wallet', [WalletController::class, 'index']);
Route::get('/courier/wallet/transactions', [WalletController::class, 'transactions']);
Route::get('/courier/wallet/transactions/{id}', [WalletController::class, 'transactionDetail']);
Route::get('/courier/wallet/fee-config', [WalletController::class, 'feeConfig']);

// ─── Earnings Route ────────────────────────────────────────────
Route::get('/courier/earnings', [WalletController::class, 'earnings']);

// ─── DANA Routes ───────────────────────────────────────────────
Route::get('/courier/dana/status', [DanaController::class, 'status']);
Route::post('/courier/dana/connect', [DanaController::class, 'connect']);
Route::post('/courier/dana/verify', [DanaController::class, 'verify']);
Route::post('/courier/dana/mock-connect', [DanaController::class, 'mockConnect']);
Route::post('/courier/dana/callback', [DanaController::class, 'callback']);
Route::post('/courier/dana/disconnect', [DanaController::class, 'disconnect']);
Route::post('/courier/dana/reconnect', [DanaController::class, 'reconnect']);

// ─── Withdrawal Routes ─────────────────────────────────────────
Route::post('/courier/withdrawals', [WithdrawalController::class, 'store']);
Route::get('/courier/withdrawals', [WithdrawalController::class, 'index']);
Route::get('/courier/withdrawals/{id}', [WithdrawalController::class, 'show']);
Route::post('/courier/withdrawals/{id}/cancel', [WithdrawalController::class, 'cancel']);
