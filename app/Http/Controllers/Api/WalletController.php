<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\DanaConnection;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

/**
 * Wallet Controller.
 * Handles wallet balance, transactions, earnings, and fee configuration.
 *
 * PRD §36: API Backend endpoints for wallet
 */
class WalletController extends Controller
{
    private WalletService $walletService;
    private WithdrawalService $withdrawalService;

    public function __construct(?WalletService $walletService = null, ?WithdrawalService $withdrawalService = null)
    {
        $this->walletService    = $walletService ?? new WalletService();
        $this->withdrawalService = $withdrawalService ?? new WithdrawalService();
    }

    /**
     * GET /api/courier/wallet
     * Get wallet data for authenticated courier.
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

        $balance = $this->walletService->getBalance($courier->id);

        return response()->json([
            'success' => true,
            'data'    => $balance,
        ]);
    }

    /**
     * GET /api/courier/wallet/transactions
     * Get wallet transactions (ledger).
     * PRD §27: Ledger as audit trail.
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

        $page = (int) $request->input('page', 1);
        $result = $this->walletService->getLedger($courier->id, $page);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * GET /api/courier/wallet/transactions/{id}
     * Get single transaction detail.
     */
    public function transactionDetail(Request $request, $id)
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
                'success' => false,
                'message' => 'Wallet tidak ditemukan.',
            ], 404);
        }

        $transaction = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('id', $id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $transaction,
        ]);
    }

    /**
     * GET /api/courier/earnings
     * Get courier earnings summary: today, week, month, and recent activity.
     * Called by DompetScreen and RiwayatTransaksiScreen.
     */
    public function earnings(Request $request)
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
                'data'    => [
                    'today'           => 0,
                    'week'            => 0,
                    'month'           => 0,
                    'recent_activity' => [],
                ],
            ]);
        }

        $now = now();

        // Earnings for today
        $today = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'earning')
            ->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->sum('amount');

        // Earnings for this week
        $week = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'earning')
            ->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->startOfWeek())
            ->sum('amount');

        // Earnings for this month
        $month = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'earning')
            ->where('status', 'completed')
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->sum('amount');

        // Recent activity (last 10 transactions)
        $recentTransactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($txn) {
                $isPositive = $txn->amount > 0;
                return [
                    'id'       => $txn->id,
                    'type'     => $txn->type,
                    'title'    => $txn->description ?? ($txn->type === 'earning' ? 'Pendapatan' : 'Penarikan'),
                    'amount'   => (float) $txn->amount,
                    'time'     => $txn->created_at->format('d M, H:i'),
                    'date'     => $txn->created_at->format('d M'),
                    'date_label' => $txn->created_at->format('Y-m-d'),
                    'status'   => $txn->type === 'earning' ? 'Selesai' : ($txn->type === 'withdrawal' ? 'Berhasil' : null),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'today'           => (float) $today,
                'week'            => (float) $week,
                'month'           => (float) $month,
                'recent_activity' => $recentTransactions,
            ],
        ]);
    }

    /**
     * GET /api/courier/wallet/fee-config
     * Get withdrawal fee configuration.
     * PRD §15: Fee from configuration, not hardcoded.
     */
    public function feeConfig(Request $request)
    {
        $config = $this->withdrawalService->getFeeConfig();

        return response()->json([
            'success' => true,
            'data'    => $config,
        ]);
    }

    /**
     * Add earning to wallet (called by order completion).
     * PRD §59: Order → Earnings → Wallet Credit
     */
    public static function addEarning($courierId, $orderId, $amount, $description = null)
    {
        $walletService = new WalletService();

        return $walletService->credit(
            $courierId,
            $amount,
            'earning',
            $orderId,
            $description ?? 'Pengantaran #' . $orderId,
        );
    }
}
