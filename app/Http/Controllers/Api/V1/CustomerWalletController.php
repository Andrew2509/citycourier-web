<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerWalletController extends Controller
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletManager->getOrCreateCustomerWallet($user);
        $balance = $wallet->balanceRecord;
        $mode = $this->providerResolver->currentMode();
        $activeTopupProvider = $this->providerResolver->safePaymentProviderCode();
        $topupProviderEnabled = ! $this->providerResolver->isPaymentProviderDisabled();
        $payoutProviderEnabled = ! $this->providerResolver->isPayoutProviderDisabled();
        $withdrawalEnabled = $this->providerResolver->isCustomerWithdrawalEnabled() && $payoutProviderEnabled;
        $withdrawalBlockedReason = ! $this->providerResolver->isCustomerWithdrawalEnabled()
            ? 'Penarikan dana wallet pelanggan dinonaktifkan di production hingga persetujuan regulasi dan penyedia pembayaran.'
            : (! $payoutProviderEnabled
                ? 'Gateway pencairan sedang dinonaktifkan oleh Admin.'
                : null);

        return response()->json([
            'success' => true,
            'message' => 'Wallet pelanggan berhasil dimuat.',
            'data' => [
                'wallet_id' => $wallet->id,
                'wallet_type' => 'customer',
                'currency' => $wallet->currency ?: 'IDR',
                'status' => $wallet->status,
                'is_frozen' => $wallet->isFrozen(),
                'freeze_reason' => $wallet->freeze_reason,
                'frozen_at' => $wallet->frozen_at ? Carbon::parse($wallet->frozen_at)->toIso8601String() : null,
                'available_balance' => (int) ($balance?->available_balance ?? round((float) $wallet->available_balance)),
                'pending_balance' => (int) ($balance?->pending_balance ?? round((float) $wallet->pending_balance)),
                'reserved_balance' => (int) ($balance?->reserved_balance ?? ($wallet->reserved_balance ?? 0)),
                'total_credited' => (int) ($balance?->total_credited ?? 0),
                'total_debited' => (int) ($balance?->total_debited ?? 0),
                'mode' => $mode,
                'is_sandbox_or_demo' => $mode !== 'production',
                'active_payment_provider' => $activeTopupProvider,
                'topup_provider_enabled' => $topupProviderEnabled,
                'security' => [
                    'has_transaction_pin' => $user->hasTransactionPin(),
                    'biometric_enabled' => (bool) ($user->getAttribute('biometric_enabled') ?? false),
                ],
                'withdrawal_policy' => [
                    'enabled' => $withdrawalEnabled,
                    'provider_enabled' => $payoutProviderEnabled,
                    'blocked_reason' => $withdrawalBlockedReason,
                    'min_amount_idr' => (int) config('payment_wallet.customer_withdrawal_min_amount_idr', 10000),
                    'max_amount_idr' => (int) config('payment_wallet.customer_withdrawal_max_amount_idr', 5000000),
                ],
                'topup_policy' => [
                    'min_amount_idr' => (int) config('payment_wallet.topup.min_amount_idr', 10000),
                    'max_amount_idr' => (int) config('payment_wallet.topup.max_amount_idr', 10000000),
                ],
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletManager->getOrCreateCustomerWallet($user);

        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $query = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('id');

        if ($type !== '' && $type !== 'all') {
            if ($type === 'withdrawal') {
                $query->whereIn('type', ['withdrawal', 'payout', 'payout_reversal']);
            } else {
                $query->where('type', $type);
            }
        }
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(fn (WalletTransaction $tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'direction' => $tx->direction ?? ((float) $tx->amount >= 0 ? 'credit' : 'debit'),
            'amount' => (int) ($tx->amount_idr ?? abs((int) round((float) $tx->amount))),
            'signed_amount' => (int) round((float) $tx->amount),
            'balance_after' => $tx->balance_after !== null ? (int) $tx->balance_after : null,
            'status' => $tx->status,
            'reference_id' => $tx->reference ?? $tx->reference_id,
            'description' => $tx->description,
            'mode' => $tx->mode ?? 'sandbox',
            'metadata' => $tx->metadata ?? [],
            'created_at' => $tx->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat transaksi wallet berhasil dimuat.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function transactionDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletManager->getOrCreateCustomerWallet($user);

        $tx = WalletTransaction::where('wallet_id', $wallet->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi ditemukan.',
            'data' => [
                'id' => $tx->id,
                'wallet_id' => $tx->wallet_id,
                'type' => $tx->type,
                'direction' => $tx->direction ?? ((float) $tx->amount >= 0 ? 'credit' : 'debit'),
                'amount' => (int) ($tx->amount_idr ?? abs((int) round((float) $tx->amount))),
                'balance_after' => $tx->balance_after !== null ? (int) $tx->balance_after : null,
                'status' => $tx->status,
                'reference_id' => $tx->reference ?? $tx->reference_id,
                'idempotency_key' => $tx->idempotency_key,
                'description' => $tx->description,
                'mode' => $tx->mode ?? 'sandbox',
                'metadata' => $tx->metadata ?? [],
                'created_at' => $tx->created_at?->toIso8601String(),
            ],
        ]);
    }
}
