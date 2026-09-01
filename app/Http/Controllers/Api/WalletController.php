<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\DanaConnection;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Get wallet data for authenticated courier
     * GET /api/courier/wallet
     */
    public function index(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $wallet = Wallet::firstOrCreate(
            ['courier_id' => $courier->id],
            ['status' => 'not_active']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'available_balance' => $wallet->available_balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency,
                'status' => $wallet->status,
            ],
        ]);
    }

    /**
     * Get wallet transactions
     * GET /api/courier/wallet/transactions
     */
    public function transactions(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $wallet = Wallet::where('courier_id', $courier->id)->first();

        if (!$wallet) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }

    /**
     * Add earning to wallet (called by order completion)
     */
    public static function addEarning($courierId, $orderId, $amount, $description = null)
    {
        $wallet = Wallet::firstOrCreate(
            ['courier_id' => $courierId],
            ['status' => 'not_active']
        );

        $transaction = WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'courier_id' => $courierId,
            'order_id' => $orderId,
            'type' => 'earning',
            'amount' => $amount,
            'fee' => 0,
            'net_amount' => $amount,
            'status' => 'completed',
            'description' => $description ?? 'Pengantaran #' . $orderId,
        ]);

        $wallet->increment('available_balance', $amount);

        return $transaction;
    }
}
