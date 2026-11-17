<?php

namespace App\Modules\Payout\Services;

use App\Models\Courier;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Earnings\Services\CourierEarningService;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Jobs\PayoutProcessingJob;
use App\Modules\Payout\Jobs\PayoutStatusCheckJob;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Wallet\Models\PayoutDestination;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PayoutService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver,
        private readonly CourierEarningService $earningService
    ) {}

    public function minCourierPayoutIdr(): int
    {
        $default = (int) config('payment_wallet.courier_payout.min_amount_idr', 20000);
        if (Schema::hasTable('settings')) {
            $val = Setting::get('courier_min_payout_idr');
            if ($val !== null && $val !== '') {
                return max(1000, (int) $val);
            }
        }

        return $default;
    }

    public function isCourierAutoPayoutEnabled(): bool
    {
        $default = (bool) config('payment_wallet.courier_payout.auto_enabled', true);
        if (Schema::hasTable('settings')) {
            $val = Setting::get('courier_auto_payout_enabled');
            if ($val !== null && $val !== '') {
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $default;
    }

    /**
     * Customer wallet withdrawal (HARD BLOCKED in production unless legally/regulatory approved).
     */
    public function requestCustomerWithdrawal(
        User $user,
        int $amount,
        int $destinationId,
        string $idempotencyKey
    ): array {
        if (! $this->providerResolver->isCustomerWithdrawalEnabled()) {
            throw new RuntimeException(
                'CUSTOMER_WITHDRAWAL_DISABLED_IN_PRODUCTION:Penarikan dana wallet pelanggan dinonaktifkan di production hingga struktur regulasi dan persetujuan penyedia pembayaran terpenuhi.'
            );
        }

        $min = (int) config('payment_wallet.customer_withdrawal_min_amount_idr', 10000);
        $max = (int) config('payment_wallet.customer_withdrawal_max_amount_idr', 5000000);
        if ($amount < $min || $amount > $max) {
            throw new InvalidArgumentException("Nominal penarikan harus antara Rp {$min} dan Rp {$max}.");
        }

        $existing = PayoutTransaction::where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return ['payout' => $existing, 'idempotent_replay' => true];
        }

        $destination = PayoutDestination::where('id', $destinationId)
            ->where('user_id', $user->id)
            ->where('owner_type', 'customer')
            ->where('is_active', true)
            ->firstOrFail();

        $fee = (int) config('payment_wallet.customer_withdrawal_fee_idr', 0);
        if ($amount <= $fee) {
            throw new InvalidArgumentException('Nominal penarikan harus lebih besar dari biaya admin.');
        }

        $payout = DB::transaction(function () use ($user, $amount, $fee, $destination, $idempotencyKey) {
            $wallet = $this->walletManager->getOrCreateCustomerWallet($user);
            $this->walletManager->assertNotFrozen($wallet);

            $available = (int) round((float) ($wallet->balanceRecord?->available_balance ?? $wallet->available_balance));
            if ($available < $amount) {
                throw new RuntimeException("INSUFFICIENT_BALANCE:Saldo tersedia Rp {$available} tidak mencukupi.");
            }

            $mode = $this->providerResolver->currentMode();
            $provider = $this->providerResolver->resolvePayoutProvider();
            $reference = 'WD-CUST-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));

            $payout = PayoutTransaction::create([
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'owner_type' => 'customer',
                'user_id' => $user->id,
                'courier_id' => null,
                'wallet_id' => $wallet->id,
                'payout_destination_id' => $destination->id,
                'payout_type' => 'customer_withdrawal',
                'provider' => $provider->providerCode(),
                'mode' => $mode,
                'is_simulated' => $provider->providerCode() === 'mock',
                'channel_code' => $destination->channel_code,
                'account_number_masked' => $destination->account_number_masked,
                'account_holder_name' => $destination->account_holder_name,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $amount - $fee,
                'status' => 'pending',
                'scheduled_at' => now(),
            ]);
            $payout->appendStatusHistory('pending', "Withdrawal requested ({$mode} mode)");
            $payout->save();

            $this->walletManager->reserveForPayout(
                $wallet,
                $amount,
                $reference,
                'wd-reserve-'.$payout->id,
                "Penarikan wallet ke {$destination->channel_name} ({$destination->account_number_masked})",
                PayoutTransaction::class,
                $payout->id,
                $mode,
                ['payout_id' => $payout->id, 'payout_type' => 'customer_withdrawal']
            );

            return $payout;
        });

        PayoutProcessingJob::dispatch($payout->id)->afterCommit();

        return ['payout' => $payout->fresh(), 'idempotent_replay' => false];
    }

    /**
     * Scheduler entry: settle mature earnings then schedule automatic courier payouts.
     *
     * @return array{released_earnings: int, scheduled_payouts: int}
     */
    public function runScheduledCourierAutoPayouts(): array
    {
        $released = 0;
        if ((bool) config('payment_wallet.courier_earnings.auto_release_enabled', true)) {
            $released = $this->earningService->releaseMatureEarnings();
        }

        if (! $this->isCourierAutoPayoutEnabled()) {
            return ['released_earnings' => $released, 'scheduled_payouts' => 0];
        }

        if ($this->providerResolver->isPayoutProviderDisabled()) {
            return ['released_earnings' => $released, 'scheduled_payouts' => 0];
        }

        $minPayout = $this->minCourierPayoutIdr();
        $fee = (int) config('payment_wallet.courier_payout.fee_idr', 0);
        $requireVerified = (bool) config('payment_wallet.courier_payout.require_verified_destination', true);
        $scheduledCount = 0;

        $candidateWallets = Wallet::where('wallet_type', 'courier')
            ->where('status', 'active')
            ->whereNull('frozen_at')
            ->where('available_balance', '>=', $minPayout)
            ->get();

        foreach ($candidateWallets as $walletCandidate) {
            $payout = $this->scheduleSingleCourierPayout($walletCandidate->id, $minPayout, $fee, $requireVerified);
            if ($payout) {
                $scheduledCount++;
                PayoutProcessingJob::dispatch($payout->id)->afterCommit();
            }
        }

        return [
            'released_earnings' => $released,
            'scheduled_payouts' => $scheduledCount,
        ];
    }

    public function scheduleSingleCourierPayout(
        int $walletId,
        ?int $minPayout = null,
        int $fee = 0,
        bool $requireVerified = true
    ): ?PayoutTransaction {
        $minPayout = $minPayout ?? $this->minCourierPayoutIdr();

        return DB::transaction(function () use ($walletId, $minPayout, $fee, $requireVerified) {
            $wallet = Wallet::where('id', $walletId)->lockForUpdate()->first();
            if (! $wallet || $wallet->isFrozen() || ! $wallet->courier_id) {
                return null;
            }

            $balance = $this->walletManager->ensureBalanceProjection($wallet);
            $available = (int) $balance->available_balance;
            if ($available < $minPayout || $available <= $fee) {
                return null;
            }

            // Prevent duplicate concurrent payout for same courier wallet
            $hasInFlight = PayoutTransaction::where('wallet_id', $wallet->id)
                ->whereIn('status', ['pending', 'processing'])
                ->exists();
            if ($hasInFlight) {
                return null;
            }

            $courier = Courier::find($wallet->courier_id);
            if (! $courier || ! $courier->user_id) {
                return null;
            }

            $destQuery = PayoutDestination::where('user_id', $courier->user_id)
                ->where('owner_type', 'courier')
                ->where('is_active', true)
                ->where('is_default', true);

            if ($requireVerified) {
                $destQuery->where('is_verified', true);
            }

            $destination = $destQuery->first();
            if (! $destination) {
                return null;
            }

            $mode = $this->providerResolver->currentMode();
            $provider = $this->providerResolver->resolvePayoutProvider();
            $reference = 'PO-CUR-'.$courier->id.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
            $idempotencyKey = 'auto-payout-c'.$courier->id.'-'.now()->format('YmdHi').'-'.Str::random(6);

            $payout = PayoutTransaction::create([
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'owner_type' => 'courier',
                'user_id' => $courier->user_id,
                'courier_id' => $courier->id,
                'wallet_id' => $wallet->id,
                'payout_destination_id' => $destination->id,
                'payout_type' => 'courier_auto_payout',
                'provider' => $provider->providerCode(),
                'mode' => $mode,
                'is_simulated' => $provider->providerCode() === 'mock',
                'channel_code' => $destination->channel_code,
                'account_number_masked' => $destination->account_number_masked,
                'account_holder_name' => $destination->account_holder_name,
                'amount' => $available,
                'fee' => $fee,
                'net_amount' => $available - $fee,
                'status' => 'pending',
                'scheduled_at' => now(),
            ]);
            $payout->appendStatusHistory('pending', 'Scheduled automatic courier payout');
            $payout->save();

            // Reserve eligible earnings
            CourierEarning::where('courier_id', $courier->id)
                ->where('status', 'available')
                ->update([
                    'status' => 'reserved',
                    'payout_transaction_id' => $payout->id,
                ]);

            $this->walletManager->reserveForPayout(
                $wallet,
                $available,
                $reference,
                'po-reserve-'.$payout->id,
                "Auto-payout kurir ke {$destination->channel_name} ({$destination->account_number_masked})",
                PayoutTransaction::class,
                $payout->id,
                $mode,
                ['payout_id' => $payout->id, 'courier_id' => $courier->id]
            );

            return $payout->fresh();
        });
    }

    /**
     * Dispatch or verify a payout transaction with provider.
     * Enforces timeout rule: verify provider status BEFORE retrying.
     */
    public function processPayoutTransaction(int $payoutId): PayoutTransaction
    {
        $payout = PayoutTransaction::with(['destination', 'wallet'])->findOrFail($payoutId);

        if (in_array($payout->status, ['completed', 'failed', 'reversed'], true)) {
            return $payout;
        }

        // If previously submitted or timed out, verify with provider FIRST before risking duplicate payout
        if ($payout->provider_reference !== null || $payout->failure_code === 'PROVIDER_TIMEOUT' || $payout->status === 'processing') {
            return $this->verifyPayoutStatusWithProvider($payout->id);
        }

        $destination = $payout->destination;
        if (! $destination) {
            return $this->markPayoutFailedAndRelease($payout, 'MISSING_DESTINATION', 'Payout destination not found.');
        }

        try {
            $provider = $this->providerResolver->resolvePayoutProvider($payout->provider);
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'PROVIDER_DISABLED:')) {
                return $this->markPayoutFailedAndRelease(
                    $payout,
                    'PROVIDER_DISABLED',
                    substr($e->getMessage(), strlen('PROVIDER_DISABLED:'))
                );
            }
            throw $e;
        }

        DB::transaction(function () use ($payout) {
            $locked = PayoutTransaction::where('id', $payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                return;
            }
            $locked->status = 'processing';
            $locked->submitted_at = now();
            $locked->appendStatusHistory('processing', 'Submitting payout to '.$locked->provider);
            $locked->save();
        });

        try {
            $result = $provider->createPayout([
                'reference' => $payout->reference,
                'idempotency_key' => $payout->idempotency_key,
                'channel_code' => $payout->channel_code,
                'account_number' => $destination->account_number,
                'account_holder_name' => $payout->account_holder_name,
                'amount' => (int) $payout->net_amount,
                'description' => "City Courier Payout {$payout->reference}",
            ]);
        } catch (\Throwable $e) {
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out')
                || str_contains(strtolower($e->getMessage()), 'timeout')
                || str_contains(strtolower($e->getMessage()), 'curl error 28');

            if ($isTimeout) {
                DB::transaction(function () use ($payout, $e) {
                    $locked = PayoutTransaction::where('id', $payout->id)->lockForUpdate()->firstOrFail();
                    $locked->status = 'processing';
                    $locked->failure_code = 'PROVIDER_TIMEOUT';
                    $locked->failure_reason = $e->getMessage();
                    $locked->appendStatusHistory('processing', 'Provider timeout; queued for status verification before retry.');
                    $locked->save();
                });

                PayoutStatusCheckJob::dispatch($payout->id)
                    ->delay(now()->addMinutes((int) config('payment_wallet.courier_payout.timeout_recheck_minutes', 10)));

                return $payout->fresh();
            }

            $payout->retry_count = (int) $payout->retry_count + 1;
            $payout->save();

            return $this->markPayoutFailedAndRelease($payout, 'PROVIDER_ERROR', $e->getMessage());
        }

        $payout->provider_reference = $result['provider_reference'] ?? $payout->provider_reference;
        $payout->metadata = array_merge($payout->metadata ?? [], ['provider_response' => $result['raw_payload'] ?? []]);
        $payout->save();

        $providerStatus = strtolower((string) ($result['status'] ?? 'processing'));

        if ($providerStatus === 'completed') {
            return $this->markPayoutSucceeded($payout, $payout->provider_reference);
        }

        if (in_array($providerStatus, ['failed', 'reversed'], true)) {
            return $this->markPayoutFailedAndRelease(
                $payout,
                (string) ($result['failure_code'] ?? 'PROVIDER_REJECTED'),
                (string) ($result['failure_reason'] ?? 'Payout ditolak oleh penyedia pembayaran.')
            );
        }

        // Still processing (e.g. awaiting webhook or SIM_TIMEOUT)
        if (! empty($result['failure_code'])) {
            $payout->failure_code = (string) $result['failure_code'];
            $payout->failure_reason = (string) ($result['failure_reason'] ?? null);
            $payout->save();
        }
        PayoutStatusCheckJob::dispatch($payout->id)->delay(now()->addMinutes(5));

        return $payout->fresh();
    }

    public function verifyPayoutStatusWithProvider(int $payoutId): PayoutTransaction
    {
        $payout = PayoutTransaction::findOrFail($payoutId);
        if (in_array($payout->status, ['completed', 'failed', 'reversed'], true)) {
            return $payout;
        }

        $provider = $this->providerResolver->resolveWebhookPayoutProvider($payout->provider);
        $inquiry = $provider->getPayoutStatus((string) ($payout->provider_reference ?? ''), $payout->reference);

        $status = strtolower((string) ($inquiry['status'] ?? 'unknown'));
        if ($status === 'completed') {
            return $this->markPayoutSucceeded($payout, $inquiry['provider_reference'] ?: $payout->provider_reference);
        }
        if (in_array($status, ['failed', 'reversed'], true)) {
            return $this->markPayoutFailedAndRelease(
                $payout,
                (string) ($inquiry['failure_code'] ?? 'PAYOUT_FAILED'),
                (string) ($inquiry['failure_reason'] ?? 'Verified failed by provider status check.')
            );
        }

        return $payout->fresh();
    }

    public function markPayoutSucceeded(PayoutTransaction $payout, ?string $providerReference = null): PayoutTransaction
    {
        return DB::transaction(function () use ($payout, $providerReference) {
            $locked = PayoutTransaction::where('id', $payout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'completed') {
                return $locked;
            }

            if ($providerReference) {
                $locked->provider_reference = $providerReference;
            }
            $now = now();
            $locked->status = 'completed';
            $locked->completed_at = $now;
            $locked->failure_code = null;
            $locked->failure_reason = null;
            $locked->appendStatusHistory('completed', 'Payout completed');
            $locked->save();

            $wallet = $locked->wallet()->firstOrFail();

            $this->walletManager->confirmReservedPayout(
                $wallet,
                (int) $locked->amount,
                (int) $locked->fee,
                $locked->reference.'-SETTLE',
                'po-settle-'.$locked->id,
                "Payout berhasil ke {$locked->channel_code} ({$locked->account_number_masked})",
                PayoutTransaction::class,
                $locked->id,
                $locked->mode,
                ['payout_id' => $locked->id, 'provider_reference' => $locked->provider_reference]
            );

            if ($locked->courier_id) {
                CourierEarning::where('payout_transaction_id', $locked->id)
                    ->where('status', 'reserved')
                    ->update([
                        'status' => 'paid',
                        'paid_at' => $now,
                    ]);
            }

            $this->walletManager->writeAuditLog(
                $locked->user_id,
                'system',
                $wallet->id,
                'payout_completed',
                PayoutTransaction::class,
                $locked->id,
                ['status' => 'processing'],
                ['status' => 'completed', 'amount' => (int) $locked->amount, 'provider_reference' => $locked->provider_reference]
            );

            return $locked->fresh();
        });
    }

    public function markPayoutFailedAndRelease(
        PayoutTransaction $payout,
        string $failureCode,
        string $failureReason
    ): PayoutTransaction {
        return DB::transaction(function () use ($payout, $failureCode, $failureReason) {
            $locked = PayoutTransaction::where('id', $payout->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['completed', 'failed', 'reversed'], true)) {
                return $locked;
            }

            $locked->status = 'failed';
            $locked->failure_code = $failureCode;
            $locked->failure_reason = $failureReason;
            $locked->failed_at = now();
            $locked->appendStatusHistory('failed', $failureReason);
            $locked->save();

            $wallet = $locked->wallet()->firstOrFail();

            $this->walletManager->releaseReservedPayout(
                $wallet,
                (int) $locked->amount,
                $locked->reference.'-RELEASE',
                'po-release-'.$locked->id,
                "Dana payout dikembalikan (gagal: {$failureReason})",
                PayoutTransaction::class,
                $locked->id,
                $locked->mode,
                ['payout_id' => $locked->id, 'failure_code' => $failureCode]
            );

            if ($locked->courier_id) {
                CourierEarning::where('payout_transaction_id', $locked->id)
                    ->where('status', 'reserved')
                    ->update([
                        'status' => 'available',
                        'payout_transaction_id' => null,
                    ]);
            }

            $this->walletManager->writeAuditLog(
                $locked->user_id,
                'system',
                $wallet->id,
                'payout_failed_released',
                PayoutTransaction::class,
                $locked->id,
                ['status' => 'processing'],
                ['status' => 'failed', 'failure_code' => $failureCode, 'released_amount' => (int) $locked->amount]
            );

            return $locked->fresh();
        });
    }
}
