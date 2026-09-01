<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\DanaConnection;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WithdrawalController extends Controller
{
    private const MINIMUM_WITHDRAWAL = 10000;
    private const ADMIN_FEE = 2500;

    /**
     * Create withdrawal
     * POST /api/courier/withdrawals
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:' . self::MINIMUM_WITHDRAWAL,
            'idempotency_key' => 'required|string|max:50',
        ]);

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Check idempotency
        $existing = Withdrawal::where('reference', $request->idempotency_key)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $this->formatWithdrawal($existing),
                'message' => 'Penarikan sudah diproses.',
            ]);
        }

        // Check DANA connection
        $danaConnection = DanaConnection::where('courier_id', $courier->id)
            ->where('status', 'connected')
            ->first();

        if (!$danaConnection) {
            return response()->json([
                'success' => false,
                'message' => 'DANA belum terhubung.',
            ], 400);
        }

        // Check wallet balance
        $wallet = Wallet::where('courier_id', $courier->id)->first();

        if (!$wallet || $wallet->available_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo tidak mencukupi.',
            ], 400);
        }

        // Calculate fee
        $amount = $request->amount;
        $fee = self::ADMIN_FEE;
        $netAmount = $amount - $fee;

        // Deduct balance (will be confirmed by webhook)
        $wallet->decrement('available_balance', $amount);
        $wallet->increment('pending_balance', $amount);

        // Create withdrawal
        $withdrawal = Withdrawal::create([
            'courier_id' => $courier->id,
            'wallet_id' => $wallet->id,
            'dana_connection_id' => $danaConnection->id,
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => $netAmount,
            'status' => 'pending',
            'reference' => $request->idempotency_key,
        ]);

        // TODO: Send to DANA payout API
        // For now, simulate processing
        $this->processPayout($withdrawal);

        return response()->json([
            'success' => true,
            'data' => $this->formatWithdrawal($withdrawal),
            'message' => 'Penarikan berhasil dibuat.',
        ], 201);
    }

    /**
     * Get withdrawals list
     * GET /api/courier/withdrawals
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

        $withdrawals = Withdrawal::where('courier_id', $courier->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
        ]);
    }

    /**
     * Get withdrawal detail
     * GET /api/courier/withdrawals/{id}
     */
    public function show(Request $request, $id)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $withdrawal = Withdrawal::where('courier_id', $courier->id)
            ->where('id', $id)
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatWithdrawal($withdrawal),
        ]);
    }

    /**
     * Process payout (mock for now)
     */
    private function processPayout(Withdrawal $withdrawal)
    {
        // TODO: Integrate with actual DANA payout API
        // For now, simulate success after delay
        
        $withdrawal->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        // In production, this would be triggered by webhook
        // Simulate success
        $withdrawal->update([
            'status' => 'success',
            'provider_reference' => 'DANA-' . strtoupper(Str::random(10)),
        ]);

        // Update wallet
        $wallet = $withdrawal->wallet;
        $wallet->decrement('pending_balance', $withdrawal->amount);

        // Create transaction record
        \App\Models\WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'courier_id' => $withdrawal->courier_id,
            'type' => 'withdrawal',
            'amount' => -$withdrawal->amount,
            'fee' => $withdrawal->fee,
            'net_amount' => -$withdrawal->net_amount,
            'status' => 'completed',
            'reference' => $withdrawal->reference,
            'description' => 'Penarikan ke DANA',
        ]);
    }

    /**
     * Format withdrawal for response
     */
    private function formatWithdrawal(Withdrawal $withdrawal)
    {
        return [
            'withdrawal_id' => $withdrawal->reference,
            'amount' => $withdrawal->amount,
            'fee' => $withdrawal->fee,
            'net_amount' => $withdrawal->net_amount,
            'status' => $withdrawal->status,
            'destination' => 'DANA',
            'masked_account' => $withdrawal->danaConnection?->masked_phone ?? '-',
            'created_at' => $withdrawal->created_at,
            'processed_at' => $withdrawal->processed_at,
        ];
    }
}
