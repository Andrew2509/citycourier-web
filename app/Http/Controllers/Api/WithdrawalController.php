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
     * GET /api/courier/withdrawals/destination-inquiry?phone=…
     *
     * Preview nama pemilik akun DANA tujuan SEBELUM penarikan dikirim.
     * Sumber kebenaran adalah penyedia payout (iPaymu) — endpoint ini hanya
     * meneruskan, tidak pernah menebak nama. Selama layanan payout besar
     * (disbursement) belum diaktifkan iPaymu, responsnya jujur:
     * { available: false, status: "unavailable" } dan UI Flutter cukup
     * menampilkan hint netral (bukan error keras).
     */
    public function destinationInquiry(Request $request)
    {
        $phone = $this->withdrawalService->normalizePhoneNumber(
            (string) ($request->query('phone') ?? $request->query('phone_number') ?? '')
        );

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor DANA tidak valid. Gunakan format 08…, 628…, atau 8… (9–13 digit).',
                'error_code' => 'INVALID_PHONE',
            ], 422);
        }

        $result = (new \App\Services\iPaymuService())->inquireAccountName($phone);

        if (!$result['available']) {
            // Bukan kegagalan form — layanan inquiry memang belum tersedia.
            return response()->json([
                'success' => true,
                'data' => [
                    'available'    => false,
                    'account_name' => null,
                    'status'       => $result['reason'], // endpoint_not_set | payout_inactive | not_configured | lookup_failed
                    'destination'  => $phone,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'available'    => true,
                'account_name' => $result['account_name'],
                'status'       => 'ok',
                'destination'  => $phone,
            ],
        ]);
    }

    /**
     * POST /api/courier/withdrawals
     * Create a withdrawal request to DANA via iPaymu (no binding needed).
     * PRD §19: Withdrawal API
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'idempotency_key'   => 'required|string|max:50',
            'phone_number'      => 'nullable|string|max:20',
            'destination_phone' => 'nullable|string|max:20',
        ]);

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $destinationPhone = $request->input('phone_number') ?: $request->input('destination_phone');

        $result = $this->withdrawalService->create(
            $courier->id,
            (float) $request->amount,
            (string) $request->idempotency_key,
            $destinationPhone !== null ? (string) $destinationPhone : null,
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
     * GET /api/courier/withdrawals/{id}/status
     * Sync and check latest withdrawal status with payment provider.
     */
    public function syncStatus(Request $request, $id)
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
            $withdrawal = $this->withdrawalService->getWithdrawalByKey($courier->id, $id);
        }

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        $updated = $this->withdrawalService->checkAndSyncStatus($withdrawal);

        return response()->json([
            'success' => true,
            'data'    => $this->formatWithdrawal($updated),
        ]);
    }

    /**
     * Format withdrawal for API response.
     * Nomor HP DANA tujuan di-mask sebelum dikirim ke aplikasi.
     */
    private function formatWithdrawal(Withdrawal $withdrawal): array
    {
        $provider = strtoupper((string) ($withdrawal->provider ?? 'DANA'));
        $conn = $withdrawal->danaConnection;
        $rawAccount = $withdrawal->account_number ?: ($conn?->masked_phone ?? '');
        $maskedAccount = $rawAccount !== '' ? WithdrawalService::maskPhone($rawAccount) : '-';
        $accountName = $withdrawal->account_name ?: ($conn?->account_name ?? '-');

        return [
            'id'                 => $withdrawal->id,
            'withdrawal_id'      => $withdrawal->id,
            'idempotency_key'    => $withdrawal->idempotency_key,
            'amount'             => (float) $withdrawal->amount,
            'fee'                => (float) $withdrawal->fee,
            'net_amount'         => (float) $withdrawal->net_amount,
            'status'             => $withdrawal->status,
            'provider'           => $provider,
            'destination'        => $maskedAccount,
            'bank_name'          => $withdrawal->bank_name ?? $provider,
            'account_number'     => $maskedAccount,
            'account_name'       => $accountName,
            'masked_account'     => $maskedAccount,
            'provider_reference' => $withdrawal->provider_reference,
            'failure_reason'     => $withdrawal->failure_reason,
            'status_history'     => $withdrawal->status_history,
            'created_at'         => $withdrawal->created_at,
            'processed_at'       => $withdrawal->processed_at,
        ];
    }
}
