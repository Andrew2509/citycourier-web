<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

/**
 * Withdrawal Controller.
 * Handles withdrawal creation, listing, and status checking.
 *
 * PRD §36: API Backend endpoints for withdrawals
 * PRD §60: Withdrawal lifecycle
 */
class WithdrawalController extends Controller
{
    private WithdrawalService $withdrawalService;

    public function __construct(?WithdrawalService $withdrawalService = null)
    {
        $this->withdrawalService = $withdrawalService ?? new WithdrawalService();
    }

    /**
     * POST /api/courier/withdrawals
     * Create a withdrawal request.
     * PRD §19: Withdrawal API
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:1',
            'idempotency_key'  => 'required|string|max:50',
        ]);

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $result = $this->withdrawalService->create(
            $courier->id,
            (float) $request->amount,
            $request->idempotency_key,
        );

        if ($result['success']) {
            $withdrawal = $result['withdrawal'];
            $statusCode = isset($result['idempotent']) && $result['idempotent'] ? 200 : 201;

            return response()->json([
                'success' => true,
                'data'    => $this->formatWithdrawal($withdrawal),
                'message' => isset($result['idempotent']) && $result['idempotent']
                    ? 'Penarikan sudah diproses sebelumnya.'
                    : 'Penarikan berhasil dibuat.',
            ], $statusCode);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Gagal memproses penarikan.',
        ], 400);
    }

    /**
     * GET /api/courier/withdrawals
     * Get withdrawals list.
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

        $page = (int) $request->input('page', 1);
        $result = $this->withdrawalService->getWithdrawals($courier->id, $page);

        $withdrawals = collect($result['withdrawals'])->map(function ($w) {
            return $this->formatWithdrawal($w);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'withdrawals' => $withdrawals,
                'total'       => $result['total'],
                'page'        => $result['page'],
            ],
        ]);
    }

    /**
     * GET /api/courier/withdrawals/{id}
     * Get withdrawal detail by ID or idempotency_key.
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

        // Try by ID first, then by idempotency_key
        $withdrawal = $this->withdrawalService->getWithdrawal($courier->id, (int) $id);

        if (!$withdrawal) {
            $withdrawal = $this->withdrawalService->getWithdrawalByKey($courier->id, $id);
        }

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatWithdrawal($withdrawal),
        ]);
    }

    /**
     * POST /api/courier/withdrawals/{id}/cancel
     * Cancel a pending withdrawal.
     */
    public function cancel(Request $request, $id)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $withdrawal = $this->withdrawalService->getWithdrawal($courier->id, (int) $id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        if (!in_array($withdrawal->status, ['pending', 'reserved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak dapat dibatalkan karena sudah diproses.',
            ], 400);
        }

        // Release reserved balance
        if ($withdrawal->status === 'reserved') {
            $walletService = new \App\Services\WalletService();
            $walletService->releaseReservation(
                $courier->id,
                $withdrawal->amount,
                $withdrawal->idempotency_key,
            );
        }

        $withdrawal->update([
            'status'         => 'cancelled',
            'failure_reason' => 'Dibatalkan oleh kurir',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penarikan berhasil dibatalkan.',
        ]);
    }

    /**
     * Format withdrawal for API response.
     */
    private function formatWithdrawal(Withdrawal $withdrawal): array
    {
        return [
            'withdrawal_id'     => $withdrawal->id,
            'idempotency_key'   => $withdrawal->idempotency_key,
            'amount'            => (float) $withdrawal->amount,
            'fee'               => (float) $withdrawal->fee,
            'net_amount'        => (float) $withdrawal->net_amount,
            'status'            => $withdrawal->status,
            'destination'       => 'DANA',
            'masked_account'    => $withdrawal->danaConnection?->masked_phone ?? '-',
            'provider_reference' => $withdrawal->provider_reference,
            'failure_reason'    => $withdrawal->failure_reason,
            'created_at'        => $withdrawal->created_at,
            'processed_at'      => $withdrawal->processed_at,
        ];
    }
}
