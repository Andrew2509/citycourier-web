<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanaConnection;
use App\Services\OvoService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * OVO Controller — SNAP B2B Integration for Courier Wallet.
 */
class OvoController extends Controller
{
    private OvoService $ovoService;
    private WithdrawalService $withdrawalService;

    public function __construct(
        ?OvoService $ovoService = null,
        ?WithdrawalService $withdrawalService = null
    ) {
        $this->ovoService        = $ovoService ?? new OvoService();
        $this->withdrawalService = $withdrawalService ?? new WithdrawalService();
    }

    /**
     * GET /api/courier/ovo/status
     */
    public function status(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        $connection = $this->ovoService->freshStatus($courier->id);

        $status = $connection?->status ?? 'not_connected';
        $isConnected = $connection !== null && $status === 'connected';

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => [
                'status'         => $isConnected ? 'CONNECTED' : strtoupper($status),
                'status_label'   => $isConnected ? 'CONNECTED' : strtoupper($status),
                'provider'       => 'OVO',
                'connected'      => $isConnected,
                'phone_number'   => $connection?->masked_phone,
                'masked_phone'   => $connection?->masked_phone,
                'masked_account' => $connection?->masked_phone,
                'account_name'   => $connection?->account_name,
                'bound_at'       => $connection?->bound_at?->toIso8601String(),
                'connected_at'   => $connection?->linked_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/courier/ovo/connect
     * Hubungkan nomor OVO dengan validasi format & inquiry resmi OVO.
     */
    public function connect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        $request->validate([
            'phone_number' => 'required|string',
            'account_name' => 'required|string|max:150',
            'consent'      => 'required',
        ]);

        $consent = filter_var($request->input('consent'), FILTER_VALIDATE_BOOLEAN);

        $result = $this->ovoService->connectAccount(
            $courier->id,
            (string) $request->input('phone_number'),
            (string) $request->input('account_name'),
            $consent
        );

        if (!$result['success']) {
            return response()->json([
                'success'    => false,
                'message'    => $result['error'],
                'error_code' => $result['error_code'] ?? 'OVO_CONNECT_FAILED',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Akun OVO berhasil dihubungkan.',
            'data'    => [
                'status'       => 'CONNECTED',
                'provider'     => 'OVO',
                'connected'    => true,
                'masked_phone' => $result['masked_phone'],
                'account_name' => $result['account_name'],
            ],
        ]);
    }

    /**
     * POST /api/courier/ovo/update
     * Perbarui nomor atau nama pemilik akun OVO.
     */
    public function update(Request $request)
    {
        return $this->connect($request);
    }

    /**
     * POST /api/courier/ovo/unbind
     * Putuskan koneksi OVO.
     */
    public function disconnect(Request $request)
    {
        $courier = $request->user()->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'message'    => 'Profil kurir tidak ditemukan.',
                'error_code' => 'SESSION_EXPIRED',
            ], 404);
        }

        $result = $this->ovoService->unbindAccount($courier->id);

        if (!$result['success']) {
            return response()->json([
                'success'    => false,
                'message'    => $result['error'],
                'error_code' => $result['error_code'] ?? 'OVO_DISCONNECT_FAILED',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Koneksi OVO berhasil diputuskan.',
        ]);
    }

    /**
     * POST /api/courier/ovo/withdraw
     * Tarik saldo ke OVO.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount'          => 'required|numeric|min:1',
            'idempotency_key' => 'required|string|max:50',
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
            $request->idempotency_key
        );

        if ($result['success']) {
            $withdrawal = $result['withdrawal'];
            $statusCode = isset($result['idempotent']) && $result['idempotent'] ? 200 : 201;

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'                 => $withdrawal->id,
                    'withdrawal_id'      => $withdrawal->id,
                    'idempotency_key'    => $withdrawal->idempotency_key,
                    'amount'             => (float) $withdrawal->amount,
                    'fee'                => (float) $withdrawal->fee,
                    'net_amount'         => (float) $withdrawal->net_amount,
                    'status'             => $withdrawal->status,
                    'provider'           => 'OVO',
                    'destination'        => 'OVO',
                    'bank_name'          => 'OVO',
                    'account_number'     => $withdrawal->account_number ?? $withdrawal->danaConnection?->masked_phone,
                    'account_name'       => $withdrawal->account_name ?? $withdrawal->danaConnection?->account_name,
                    'provider_reference' => $withdrawal->provider_reference,
                    'failure_reason'     => $withdrawal->failure_reason,
                    'created_at'         => $withdrawal->created_at,
                    'processed_at'       => $withdrawal->processed_at,
                ],
                'message' => isset($result['idempotent']) && $result['idempotent']
                    ? 'Penarikan sudah diproses sebelumnya.'
                    : 'Penarikan ke OVO berhasil diajukan.',
            ], $statusCode);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Gagal memproses penarikan ke OVO.',
        ], 400);
    }

    /**
     * GET /api/courier/ovo/withdrawals/{id}/status
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
            'data'    => [
                'id'                 => $updated->id,
                'status'             => $updated->status,
                'provider'           => 'OVO',
                'provider_reference' => $updated->provider_reference,
                'failure_reason'     => $updated->failure_reason,
                'amount'             => (float) $updated->amount,
                'net_amount'         => (float) $updated->net_amount,
            ],
        ]);
    }

    /**
     * POST /api/ovo/webhook
     * Webhook notifikasi SNAP dari OVO.
     */
    public function webhook(Request $request)
    {
        Log::info('[OvoController] Webhook notification received', $request->all());

        $partnerReference = $request->input('originalPartnerReferenceNo')
            ?? $request->input('partnerReferenceNo')
            ?? $request->input('reference_no');

        $statusRaw = $request->input('latestTransactionStatus')
            ?? $request->input('transactionStatus')
            ?? $request->input('status');

        $failureReason = $request->input('responseMessage') ?? $request->input('reason');

        if (!$partnerReference) {
            return response()->json([
                'responseCode'    => '4000000',
                'responseMessage' => 'Missing partnerReferenceNo',
            ], 400);
        }

        $newStatus = match ($statusRaw) {
            '00', 'SUCCESS', 'success' => 'success',
            '02', 'FAILED', 'failed'   => 'failed',
            default                    => 'processing',
        };

        $this->withdrawalService->handleStatusUpdate($partnerReference, $newStatus, $failureReason);

        return response()->json([
            'responseCode'    => '2000000',
            'responseMessage' => 'Successful',
        ]);
    }
}
