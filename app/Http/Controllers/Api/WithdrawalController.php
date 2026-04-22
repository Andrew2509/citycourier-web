<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    /**
     * List withdrawals for the authenticated courier.
     * GET /api/withdrawals
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
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
        ]);
    }

    /**
     * Request a new withdrawal.
     * POST /api/withdrawals
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10000',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'account_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Check if there's already a pending withdrawal
        $pending = Withdrawal::where('courier_id', $courier->id)
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki permintaan penarikan yang tertunda.',
            ], 400);
        }

        // Check if balance is sufficient
        $totalEarnings = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'completed')
            ->sum('price') * 0.9; // 90% commission

        $processedWithdrawals = Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['completed', 'pending'])
            ->sum('amount');

        $availableBalance = $totalEarnings - $processedWithdrawals;

        if ($request->amount > $availableBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo tidak mencukupi.',
                'available_balance' => $availableBalance,
            ], 400);
        }
        
        $withdrawal = Withdrawal::create([
            'courier_id' => $courier->id,
            'amount' => $request->amount,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan penarikan berhasil diajukan.',
            'data' => $withdrawal,
        ], 201);
    }
}
