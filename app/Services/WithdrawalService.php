<?php

namespace App\Services;

use App\Models\DanaConnection;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Withdrawal Service Layer.
 * Handles withdrawal lifecycle: validate → reserve → process → confirm/release.
 * Migrated to OVO while maintaining backward compatibility for legacy records.
 */
class WithdrawalService
{
    private OvoService $ovoService;
    private DanaService $danaService;
    private WalletService $walletService;

    private const MIN_WITHDRAWAL        = 10000;
    private const MAX_WITHDRAWAL        = 5000000;
    private const DAILY_LIMIT           = 5000000;
    private const ADMIN_FEE_DEFAULT     = 2500;
    private const RATE_LIMIT_REQUESTS   = 5;
    private const RATE_LIMIT_WINDOW_MIN = 10;

    public function __construct(
        ?OvoService $ovoService = null,
        ?DanaService $danaService = null,
        ?WalletService $walletService = null
    ) {
        $this->ovoService    = $ovoService ?? new OvoService();
        $this->danaService   = $danaService ?? new DanaService();
        $this->walletService = $walletService ?? new WalletService();
    }

    /**
     * Get withdrawal fee from configuration.
     */
    public function getFeeConfig(): array
    {
        return [
            'admin_fee'   => config('withdrawals.admin_fee', self::ADMIN_FEE_DEFAULT),
            'minimum'     => config('withdrawals.minimum', self::MIN_WITHDRAWAL),
            'maximum'     => config('withdrawals.maximum', self::MAX_WITHDRAWAL),
            'daily_limit' => config('withdrawals.daily_limit', self::DAILY_LIMIT),
        ];
    }

    /**
     * Create and process a withdrawal request.
     */
    public function create(int $courierId, float $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($courierId, $amount, $idempotencyKey) {
            // 1. Idempotency check
            $existing = Withdrawal::where('courier_id', $courierId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [
                    'success'    => true,
                    'withdrawal' => $existing,
                    'error'      => null,
                    'idempotent' => true,
                ];
            }

            // 2. Validation
            $validation = $this->validate($courierId, $amount);
            if (!$validation['valid']) {
                return [
                    'success'    => false,
                    'withdrawal' => null,
                    'error'      => $validation['error'],
                ];
            }

            $wallet     = $validation['wallet'];
            $connection = $validation['connection'];
            $feeConfig  = $validation['fee_config'];
            $fee        = (float) $feeConfig['admin_fee'];
            $netAmount  = $amount - $fee;
            $provider   = strtolower((string) ($connection->provider ?? 'ovo'));

            // 3. Create withdrawal record (status = reserved)
            $withdrawal = Withdrawal::create([
                'courier_id'         => $courierId,
                'wallet_id'          => $wallet->id,
                'dana_connection_id' => $connection->id,
                'provider'           => $provider,
                'bank_name'          => strtoupper($provider),
                'account_number'     => $connection->masked_phone ?? '',
                'account_name'       => $connection->account_name ?? 'Kurir',
                'amount'             => $amount,
                'fee'                => $fee,
                'net_amount'         => $netAmount,
                'status'             => 'reserved',
                'idempotency_key'    => $idempotencyKey,
            ]);

            // 4. Atomic reserve (available -> pending)
            try {
                $holdDesc = 'Penarikan ' . strtoupper($provider) . ' - dana ditahan';
                $this->walletService->reserve($courierId, $amount, $idempotencyKey, $holdDesc);
            } catch (\RuntimeException $e) {
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
                return [
                    'success'    => false,
                    'withdrawal' => $withdrawal,
                    'error'      => $e->getMessage(),
                ];
            }

            // 5. Update to processing
            $withdrawal->update(['status' => 'processing']);

            // 6. Disburse via OVO (or DANA for legacy)
            if ($provider === 'dana') {
                $disbursementResult = $this->danaService->disburseToDana($connection, $amount, $idempotencyKey);
            } else {
                $disbursementResult = $this->ovoService->disburseToOvo($connection, $amount, $idempotencyKey);
            }

            $txId = $disbursementResult['transaction_id'] ?? null;
            $status = $disbursementResult['status'] ?? 'failed';

            if ($status === 'success') {
                $withdrawal->update([
                    'status'             => 'success',
                    'provider_reference' => $txId,
                    'processed_at'       => now(),
                ]);

                // Confirm reservation (pending balance -> 0)
                $this->walletService->confirmReservation($courierId, $amount, $idempotencyKey);

                // Record completed withdrawal ledger entry (without double-decrementing available_balance)
                \App\Models\WalletTransaction::create([
                    'wallet_id'   => $wallet->id,
                    'courier_id'  => $courierId,
                    'order_id'    => null,
                    'type'        => 'withdrawal',
                    'amount'      => -$amount,
                    'fee'         => $fee,
                    'net_amount'  => -$netAmount,
                    'status'      => 'completed',
                    'reference'   => $idempotencyKey,
                    'description' => 'Penarikan ke ' . strtoupper($provider),
                ]);

                Log::info('[WithdrawalService] Withdrawal succeeded immediately', [
                    'courier_id' => $courierId,
                    'amount'     => $amount,
                    'provider'   => $provider,
                    'ref'        => $idempotencyKey,
                ]);
            } elseif ($status === 'processing' || $status === 'pending') {
                // Keep funds held in pending_balance
                $withdrawal->update([
                    'status'             => 'processing',
                    'provider_reference' => $txId,
                ]);

                Log::info('[WithdrawalService] Withdrawal queued/processing (holding reserved funds)', [
                    'courier_id' => $courierId,
                    'amount'     => $amount,
                    'provider'   => $provider,
                    'ref'        => $idempotencyKey,
                ]);
            } else {
                // Failed -> release reservation
                $failureReason = $disbursementResult['error'] ?? 'Disbursement failed';
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $failureReason,
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $idempotencyKey);

                Log::warning('[WithdrawalService] Withdrawal disbursement failed', [
                    'courier_id' => $courierId,
                    'error'      => $failureReason,
                ]);
            }

            return [
                'success'    => true,
                'withdrawal' => $withdrawal->fresh(),
                'error'      => null,
            ];
        });
    }

    /**
     * Validate a withdrawal request.
     */
    private function validate(int $courierId, float $amount): array
    {
        $feeConfig = $this->getFeeConfig();

        if ($amount <= 0) {
            return ['valid' => false, 'error' => 'Jumlah penarikan harus lebih dari 0.'];
        }

        if ($amount < $feeConfig['minimum']) {
            return ['valid' => false, 'error' => 'Minimum penarikan Rp' . number_format($feeConfig['minimum'], 0, ',', '.') . '.'];
        }

        if ($amount > $feeConfig['maximum']) {
            return ['valid' => false, 'error' => 'Maksimum penarikan Rp' . number_format($feeConfig['maximum'], 0, ',', '.') . '.'];
        }

        // Connection check (OVO or DANA)
        $connection = DanaConnection::where('courier_id', $courierId)
            ->where('status', 'connected')
            ->first();

        if (!$connection) {
            return ['valid' => false, 'error' => 'Metode pencairan OVO belum terhubung.'];
        }

        $wallet = Wallet::where('courier_id', $courierId)->first();
        if (!$wallet || $wallet->status !== 'active') {
            return ['valid' => false, 'error' => 'Wallet tidak aktif.'];
        }

        if ((float) $wallet->available_balance < $amount) {
            return ['valid' => false, 'error' => 'Saldo tidak mencukupi.'];
        }

        // Double withdrawal / concurrent processing prevention
        $processing = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($processing > 0) {
            return ['valid' => false, 'error' => 'Penarikan sedang diproses. Mohon tunggu.'];
        }

        // Daily limit check
        $todayStart = now()->startOfDay();
        $todayTotal = Withdrawal::where('courier_id', $courierId)
            ->where('status', 'success')
            ->where('created_at', '>=', $todayStart)
            ->sum('amount');

        if ($todayTotal + $amount > $feeConfig['daily_limit']) {
            return ['valid' => false, 'error' => 'Batas penarikan harian tercapai.'];
        }

        return [
            'valid'      => true,
            'wallet'     => $wallet,
            'connection' => $connection,
            'fee_config' => $feeConfig,
            'error'      => null,
        ];
    }

    public function getWithdrawals(int $courierId, int $page = 1, int $perPage = 15): array
    {
        $withdrawals = Withdrawal::where('courier_id', $courierId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'withdrawals' => $withdrawals->items(),
            'total'       => $withdrawals->total(),
            'page'        => $page,
            'per_page'    => $perPage,
        ];
    }

    public function getWithdrawal(int $courierId, int $withdrawalId): ?Withdrawal
    {
        return Withdrawal::where('courier_id', $courierId)
            ->where('id', $withdrawalId)
            ->first();
    }

    public function getWithdrawalByKey(int $courierId, string $idempotencyKey): ?Withdrawal
    {
        return Withdrawal::where('courier_id', $courierId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Handle status update from webhook callback or status polling.
     * Guaranteed IDEMPOTENT: ignores duplicate callbacks if transaction is already terminal.
     */
    public function handleStatusUpdate(string $providerReference, string $newStatus, ?string $failureReason = null): ?Withdrawal
    {
        return DB::transaction(function () use ($providerReference, $newStatus, $failureReason) {
            $withdrawal = Withdrawal::where('provider_reference', $providerReference)
                ->orWhere('idempotency_key', $providerReference)
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                Log::warning('[WithdrawalService] Status update for unknown transaction', [
                    'provider_reference' => $providerReference,
                ]);
                return null;
            }

            // Idempotency check: terminal state cannot be altered by duplicate callbacks
            if ($withdrawal->isTerminal()) {
                Log::info('[WithdrawalService] Ignoring callback for already terminal withdrawal', [
                    'withdrawal_id' => $withdrawal->id,
                    'status'        => $withdrawal->status,
                ]);
                return $withdrawal;
            }

            $courierId = $withdrawal->courier_id;
            $amount    = (float) $withdrawal->amount;
            $reference = $withdrawal->idempotency_key;
            $provider  = strtolower((string) ($withdrawal->provider ?? 'ovo'));

            if ($newStatus === 'success') {
                $withdrawal->update([
                    'status'       => 'success',
                    'processed_at' => $withdrawal->processed_at ?? now(),
                ]);

                $this->walletService->confirmReservation($courierId, $amount, $reference);

                \App\Models\WalletTransaction::create([
                    'wallet_id'   => $withdrawal->wallet_id,
                    'courier_id'  => $courierId,
                    'order_id'    => null,
                    'type'        => 'withdrawal',
                    'amount'      => -$amount,
                    'fee'         => $withdrawal->fee,
                    'net_amount'  => -$withdrawal->net_amount,
                    'status'      => 'completed',
                    'reference'   => $reference,
                    'description' => 'Penarikan ke ' . strtoupper($provider),
                ]);

                Log::info('[WithdrawalService] Status updated to success via callback', ['withdrawal_id' => $withdrawal->id]);
            } elseif ($newStatus === 'failed') {
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $failureReason ?? 'Transaksi ditolak oleh provider.',
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $reference);

                Log::info('[WithdrawalService] Status updated to failed via callback', ['withdrawal_id' => $withdrawal->id]);
            }

            return $withdrawal->fresh();
        });
    }

    /**
     * Inquire status from OVO/DANA and synchronize withdrawal state.
     */
    public function checkAndSyncStatus(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->isTerminal()) {
            return $withdrawal;
        }

        $provider = strtolower((string) ($withdrawal->provider ?? 'ovo'));
        $ref = $withdrawal->idempotency_key;
        $txId = $withdrawal->provider_reference;

        if ($provider === 'dana') {
            $res = $this->danaService->inquireTransactionStatus($txId ?? $ref);
        } else {
            $res = $this->ovoService->inquireTransactionStatus($ref, $txId);
        }

        $status = $res['status'] ?? 'processing';
        if ($status === 'success' || $status === 'failed') {
            $updated = $this->handleStatusUpdate($txId ?? $ref, $status, $res['error'] ?? null);
            if ($updated) {
                return $updated;
            }
        }

        return $withdrawal->fresh();
    }
}
