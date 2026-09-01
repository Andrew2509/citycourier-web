<?php

namespace App\Services;

use App\Models\DanaConnection;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Withdrawal Service Layer.
 * Handles withdrawal lifecycle: validate → reserve → process → confirm/release.
 *
 * PRD §17: Backend validation checklist
 * PRD §25: Idempotency
 * PRD §30: Failure → release reserved balance
 * PRD §47: Fraud prevention (limits, cooldown)
 * PRD §48: Rate limiting
 * PRD §60: Withdrawal lifecycle
 */
class WithdrawalService
{
    private DanaService $danaService;
    private WalletService $walletService;

    // PRD §47: Fraud prevention defaults (should come from config)
    private const MIN_WITHDRAWAL        = 10000;   // PRD §16
    private const MAX_WITHDRAWAL        = 5000000; // Configurable
    private const DAILY_LIMIT           = 5000000; // Configurable
    private const ADMIN_FEE_DEFAULT     = 2500;
    private const RATE_LIMIT_REQUESTS   = 5;       // PRD §48
    private const RATE_LIMIT_WINDOW_MIN = 10;

    public function __construct(?DanaService $danaService = null, ?WalletService $walletService = null)
    {
        $this->danaService    = $danaService ?? new DanaService();
        $this->walletService  = $walletService ?? new WalletService();
    }

    /**
     * Get withdrawal fee from configuration.
     * PRD §15: Don't hardcode fee.
     */
    public function getFeeConfig(): array
    {
        return [
            'admin_fee'         => config('withdrawals.admin_fee', self::ADMIN_FEE_DEFAULT),
            'minimum'           => config('withdrawals.minimum', self::MIN_WITHDRAWAL),
            'maximum'           => config('withdrawals.maximum', self::MAX_WITHDRAWAL),
            'daily_limit'       => config('withdrawals.daily_limit', self::DAILY_LIMIT),
        ];
    }

    /**
     * Create and process a withdrawal request.
     *
     * PRD §17: Full validation checklist.
     * PRD §25: Idempotency key check.
     * PRD §28: Atomic wallet operation.
     * PRD §60: Lifecycle REQUESTED → VALIDATING → RESERVED → PROCESSING → SUCCESS/FAILED
     *
     * @param int    $courierId
     * @param float  $amount
     * @param string $idempotencyKey
     * @return array ['success' => bool, 'withdrawal' => Withdrawal|null, 'error' => string|null]
     */
    public function create(int $courierId, float $amount, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($courierId, $amount, $idempotencyKey) {
            // ─── PRD §25: Idempotency check ──────────────────────────────
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

            // ─── PRD §17: Validation ─────────────────────────────────────
            $validation = $this->validate($courierId, $amount);
            if (!$validation['valid']) {
                return [
                    'success'    => false,
                    'withdrawal' => null,
                    'error'      => $validation['error'],
                ];
            }

            $wallet    = $validation['wallet'];
            $connection = $validation['connection'];
            $feeConfig = $validation['fee_config'];
            $fee       = $feeConfig['admin_fee'];
            $netAmount = $amount - $fee;

            // ─── PRD §60: REQUESTED → VALIDATING → RESERVED ──────────────
            $withdrawal = Withdrawal::create([
                'courier_id'          => $courierId,
                'wallet_id'           => $wallet->id,
                'dana_connection_id'  => $connection->id,
                'amount'              => $amount,
                'fee'                 => $fee,
                'net_amount'          => $netAmount,
                'status'              => 'reserved',
                'idempotency_key'     => $idempotencyKey,
            ]);

            // ─── PRD §28-29: Atomic reserve ──────────────────────────────
            try {
                $this->walletService->reserve($courierId, $amount, $idempotencyKey);
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

            // ─── PRD §60: → PROCESSING ───────────────────────────────────
            $withdrawal->update(['status' => 'processing']);

            // ─── PRD §21: Disburse via DANA ──────────────────────────────
            $disbursementResult = $this->danaService->disburseToDana(
                $connection,
                $amount,
                $idempotencyKey,
            );

            if ($disbursementResult['success']) {
                $withdrawal->update([
                    'status'            => 'success',
                    'provider_reference' => $disbursementResult['transaction_id'],
                    'processed_at'      => now(),
                ]);

                // PRD §29: Confirm reservation → remove pending
                $this->walletService->confirmReservation($courierId, $amount, $idempotencyKey);

                // Create final withdrawal ledger entry
                $this->walletService->debit(
                    $courierId,
                    $amount,
                    'withdrawal',
                    'Penarikan ke DANA',
                    $idempotencyKey,
                );

                Log::info('[WithdrawalService] Withdrawal completed', [
                    'courier_id' => $courierId,
                    'amount'     => $amount,
                    'reference'  => $idempotencyKey,
                ]);
            } else {
                // PRD §30: DANA failed → release reservation
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $disbursementResult['error'] ?? 'DANA processing failed',
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $idempotencyKey);

                Log::warning('[WithdrawalService] Withdrawal failed', [
                    'courier_id' => $courierId,
                    'error'      => $disbursementResult['error'],
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
     * PRD §17: Full validation checklist.
     */
    private function validate(int $courierId, float $amount): array
    {
        $feeConfig = $this->getFeeConfig();

        // 1. Amount > 0
        if ($amount <= 0) {
            return ['valid' => false, 'error' => 'Jumlah penarikan harus lebih dari 0.'];
        }

        // 2. Amount >= minimum (PRD §16)
        if ($amount < $feeConfig['minimum']) {
            return ['valid' => false, 'error' => 'Minimum penarikan Rp' . number_format($feeConfig['minimum'], 0, ',', '.') . '.'];
        }

        // 3. Amount <= maximum
        if ($amount > $feeConfig['maximum']) {
            return ['valid' => false, 'error' => 'Maksimum penarikan Rp' . number_format($feeConfig['maximum'], 0, ',', '.') . '.'];
        }

        // 4. DANA connected (PRD §17 #4)
        $connection = DanaConnection::where('courier_id', $courierId)
            ->where('status', 'connected')
            ->first();

        if (!$connection) {
            return ['valid' => false, 'error' => 'DANA belum terhubung.'];
        }

        // 5. Wallet active (PRD §17 #3)
        $wallet = Wallet::where('courier_id', $courierId)->first();

        if (!$wallet || $wallet->status !== 'active') {
            return ['valid' => false, 'error' => 'Wallet tidak aktif.'];
        }

        // 6. Amount <= available balance (PRD §17 #7)
        if ((float) $wallet->available_balance < $amount) {
            return ['valid' => false, 'error' => 'Saldo tidak mencukupi.'];
        }

        // 7. No conflicting withdrawal (PRD §17 #8)
        $processing = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($processing > 0) {
            return ['valid' => false, 'error' => 'Penarikan sedang diproses. Mohon tunggu.'];
        }

        // 8. Daily limit check (PRD §47)
        $todayStart = now()->startOfDay();
        $todayTotal = Withdrawal::where('courier_id', $courierId)
            ->where('status', 'success')
            ->where('created_at', '>=', $todayStart)
            ->sum('amount');

        if ($todayTotal + $amount > $feeConfig['daily_limit']) {
            return ['valid' => false, 'error' => 'Batas penarikan harian tercapai.'];
        }

        return [
            'valid'     => true,
            'wallet'    => $wallet,
            'connection' => $connection,
            'fee_config' => $feeConfig,
            'error'     => null,
        ];
    }

    /**
     * Get all withdrawals for a courier.
     */
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

    /**
     * Get a single withdrawal by ID.
     */
    public function getWithdrawal(int $courierId, int $withdrawalId): ?Withdrawal
    {
        return Withdrawal::where('courier_id', $courierId)
            ->where('id', $withdrawalId)
            ->first();
    }

    /**
     * Get withdrawal by idempotency key.
     */
    public function getWithdrawalByKey(int $courierId, string $idempotencyKey): ?Withdrawal
    {
        return Withdrawal::where('courier_id', $courierId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Handle DANA status update (webhook or polling result).
     * PRD §24: Webhook / Notification
     */
    public function handleStatusUpdate(string $providerReference, string $newStatus, ?string $failureReason = null): ?Withdrawal
    {
        $withdrawal = Withdrawal::where('provider_reference', $providerReference)->first();

        if (!$withdrawal) {
            Log::warning('[WithdrawalService] Status update for unknown transaction', [
                'provider_reference' => $providerReference,
            ]);
            return null;
        }

        $courierId = $withdrawal->courier_id;
        $amount    = $withdrawal->amount;
        $reference = $withdrawal->idempotency_key;

        if ($newStatus === 'success' && $withdrawal->status !== 'success') {
            $withdrawal->update([
                'status'      => 'success',
                'processed_at' => $withdrawal->processed_at ?? now(),
            ]);

            $this->walletService->confirmReservation($courierId, $amount, $reference);
            $this->walletService->debit($courierId, $amount, 'withdrawal', 'Penarikan ke DANA', $reference);

            Log::info('[WithdrawalService] Status updated to success', ['withdrawal_id' => $withdrawal->id]);
        } elseif ($newStatus === 'failed' && !in_array($withdrawal->status, ['success', 'failed'])) {
            $withdrawal->update([
                'status'         => 'failed',
                'failure_reason' => $failureReason ?? 'DANA transaction failed',
            ]);

            $this->walletService->releaseReservation($courierId, $amount, $reference);

            Log::info('[WithdrawalService] Status updated to failed', ['withdrawal_id' => $withdrawal->id]);
        }

        return $withdrawal->fresh();
    }
}
