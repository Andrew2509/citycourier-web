<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Services\AccountInquiryService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    private WithdrawalService $withdrawalService;
    private ProviderResolver $providerResolver;
    private AccountInquiryService $accountInquiryService;

    public function __construct(
        ?WithdrawalService $withdrawalService = null,
        ?ProviderResolver $providerResolver = null,
        ?AccountInquiryService $accountInquiryService = null
    ) {
        $this->withdrawalService = $withdrawalService ?? app(WithdrawalService::class);
        $this->providerResolver = $providerResolver ?? app(ProviderResolver::class);
        $this->accountInquiryService = $accountInquiryService ?? app(AccountInquiryService::class);
    }

    /**
     * GET /api/courier/withdrawals/destination-inquiry?phone=…&channel_code=…&destination_type=…
     */
    public function destinationInquiry(Request $request)
    {
        $destType = strtolower((string) ($request->query('destination_type') ?: 'ewallet'));
        $channelCode = (string) ($request->query('channel_code') ?: 'ID_DANA');
        $rawAccount = (string) ($request->query('account_number') ?? $request->query('phone') ?? $request->query('phone_number') ?? '');

        if ($destType === 'ewallet') {
            $account = $this->withdrawalService->normalizePhoneNumber($rawAccount);
            if ($account === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor e-wallet tidak valid. Gunakan format 08…, 628…, atau 8… (9–13 digit).',
                    'error_code' => 'INVALID_PHONE',
                ], 422);
            }
        } else {
            $account = preg_replace('/\D+/', '', $rawAccount) ?? '';
            if (strlen($account) < 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor rekening minimal 6 digit.',
                    'error_code' => 'INVALID_ACCOUNT_NUMBER',
                ], 422);
            }
        }

        if ($this->providerResolver->isPayoutProviderDisabled('xendit')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'available' => false,
                    'verification_status' => 'unavailable',
                    'account_name' => null,
                    'status' => 'provider_disabled',
                    'provider' => 'xendit',
                    'destination' => $account,
                    'can_input_manually' => false,
                    'message' => 'Gateway pencairan Xendit sedang dinonaktifkan oleh Admin.',
                ],
            ]);
        }

        $res = $this->accountInquiryService->inquire($destType, $channelCode, $account);
        $verificationStatus = (string) ($res['verification_status'] ?? 'unverified');
        $available = ($res['found'] ?? false) === true;

        return response()->json([
            'success' => true,
            'data' => [
                'available' => $available,
                'verification_status' => $verificationStatus,
                'account_name' => $available ? $res['account_holder_name'] : null,
                'status' => $available ? 'ok' : $verificationStatus,
                'provider' => $res['provider'] ?? 'xendit',
                'destination' => $account,
                'can_input_manually' => (bool) ($res['can_input_manually'] ?? true),
                'message' => $res['message'] ?? null,
            ],
        ]);
    }

    /**
     * POST /api/courier/withdrawals
     */
    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'idempotency_key' => 'required|string|max:50',
            'destination_type' => 'nullable|string|in:bank,ewallet',
            'channel_code' => 'nullable|string|max:64',
            'account_number' => 'nullable|string|max:40',
            'account_holder_name' => 'nullable|string|max:150',
            'account_name' => 'nullable|string|max:150',
            'phone_number' => 'nullable|string|max:20',
            'destination_phone' => 'nullable|string|max:20',
        ]);

        $courier = $request->user()->courier;
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $destinationAccount = $request->input('account_number')
            ?: ($request->input('phone_number') ?: $request->input('destination_phone'));
        $holderName = $request->input('account_holder_name') ?: $request->input('account_name');

        $result = $this->withdrawalService->create(
            $courier->id,
            (float) $request->amount,
            (string) $request->idempotency_key,
            $destinationAccount !== null ? (string) $destinationAccount : null,
            $request->input('channel_code') ? (string) $request->input('channel_code') : null,
            $holderName !== null ? (string) $holderName : null,
            $request->input('destination_type') ? (string) $request->input('destination_type') : null
        );

        if ($result['success']) {
            $withdrawal = $result['withdrawal'];
            $statusCode = isset($result['idempotent']) && $result['idempotent'] ? 200 : 201;

            return response()->json([
                'success' => true,
                'data' => $this->formatWithdrawal($withdrawal),
                'message' => isset($result['idempotent']) && $result['idempotent']
                    ? 'Penarikan sudah diproses sebelumnya.'
                    : 'Penarikan berhasil dibuat.',
            ], $statusCode);
        }

        $statusCode = match ($result['error_code'] ?? null) {
            'PROVIDER_DISABLED' => 403,
            'UNSUPPORTED_CHANNEL' => 422,
            default => 400,
        };

        return response()->json([
            'success' => false,
            'error_code' => $result['error_code'] ?? null,
            'message' => $result['error'] ?? 'Gagal memproses penarikan.',
        ], $statusCode);
    }

    public function index(Request $request)
    {
        $courier = $request->user()->courier;
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $page = (int) $request->input('page', 1);
        $result = $this->withdrawalService->getWithdrawals($courier->id, $page);

        $withdrawals = collect($result['withdrawals'])->map(fn ($w) => $this->formatWithdrawal($w));

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals,
                'total' => $result['total'],
                'page' => $result['page'],
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $courier = $request->user()->courier;
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $withdrawal = $this->withdrawalService->getWithdrawal($courier->id, (int) $id)
            ?? $this->withdrawalService->getWithdrawalByKey($courier->id, $id);

        if (! $withdrawal) {
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

    public function cancel(Request $request, $id)
    {
        $courier = $request->user()->courier;
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $withdrawal = $this->withdrawalService->getWithdrawal($courier->id, (int) $id);
        if (! $withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        if (! in_array($withdrawal->status, ['pending', 'reserved'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak dapat dibatalkan karena sudah diproses.',
            ], 400);
        }

        if ($withdrawal->status === 'reserved') {
            $walletService = new WalletService();
            $walletService->releaseReservation(
                $courier->id,
                $withdrawal->amount,
                $withdrawal->idempotency_key,
            );
        }

        $withdrawal->update([
            'status' => 'cancelled',
            'failure_reason' => 'Dibatalkan oleh kurir',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penarikan berhasil dibatalkan.',
        ]);
    }

    public function syncStatus(Request $request, $id)
    {
        $courier = $request->user()->courier;
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $withdrawal = $this->withdrawalService->getWithdrawal($courier->id, (int) $id)
            ?? $this->withdrawalService->getWithdrawalByKey($courier->id, $id);

        if (! $withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Penarikan tidak ditemukan.',
            ], 404);
        }

        $updated = $this->withdrawalService->checkAndSyncStatus($withdrawal);

        return response()->json([
            'success' => true,
            'data' => $this->formatWithdrawal($updated),
        ]);
    }

    private function formatWithdrawal(Withdrawal $withdrawal): array
    {
        $provider = strtoupper((string) ($withdrawal->provider ?? 'XENDIT'));
        $conn = $withdrawal->danaConnection;
        $rawAccount = $withdrawal->account_number ?: ($conn?->masked_phone ?? '');
        $maskedAccount = $rawAccount !== '' ? WithdrawalService::maskPhone($rawAccount) : '-';
        $accountName = $withdrawal->account_name ?: ($conn?->account_name ?? '-');

        return [
            'id' => $withdrawal->id,
            'withdrawal_id' => $withdrawal->id,
            'idempotency_key' => $withdrawal->idempotency_key,
            'amount' => (float) $withdrawal->amount,
            'fee' => (float) $withdrawal->fee,
            'net_amount' => (float) $withdrawal->net_amount,
            'status' => $withdrawal->status,
            'provider' => $provider,
            'destination' => $maskedAccount,
            'bank_name' => $withdrawal->bank_name ?? $provider,
            'account_number' => $maskedAccount,
            'account_name' => $accountName,
            'masked_account' => $maskedAccount,
            'provider_reference' => $withdrawal->provider_reference,
            'failure_reason' => $withdrawal->failure_reason,
            'status_history' => $withdrawal->status_history,
            'created_at' => $withdrawal->created_at,
            'processed_at' => $withdrawal->processed_at,
        ];
    }
}
