<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wallet Service Layer.
 * Handles balance operations, ledger entries, reserve/release pattern.
 *
 * PRD §27: Transaction Ledger - don't just store balance, maintain ledger.
 * PRD §28: Atomic Withdrawal - lock wallet during withdrawal.
 * PRD §29: Withdrawal Reservation - available_balance / pending_balance pattern.
 * PRD §30: Release on failure.
 */
class WalletService
{
    /**
     * Get or create wallet for a courier.
     */
    public function getOrCreateWallet(int $courierId): Wallet
    {
        return Wallet::firstOrCreate(
            ['courier_id' => $courierId],
            ['status'     => 'not_active']
        );
    }

    /**
     * Get wallet balance details.
     */
    public function getBalance(int $courierId): array
    {
        $wallet = $this->getOrCreateWallet($courierId);
        $available = (float) $wallet->available_balance;
        $pending   = (float) $wallet->pending_balance;
        $total     = $available + $pending;

        $todayStart = now()->startOfDay();
        $todayEarnings = (float) WalletTransaction::where('courier_id', $courierId)
            ->where('created_at', '>=', $todayStart)
            ->where(function ($q) {
                $q->where('type', 'earning')->orWhere('amount', '>', 0);
            })
            ->sum('amount');

        $totalEarnings = (float) WalletTransaction::where('courier_id', $courierId)
            ->where(function ($q) {
                $q->where('type', 'earning')->orWhere('amount', '>', 0);
            })
            ->sum('amount');

        // Total saldo yang sudah berhasil dicairkan (semua metode, termasuk legacy DANA)
        $totalWithdrawn = (float) Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['success', 'completed'])
            ->sum('amount');

        return [
            'total_balance'     => $total,
            'available_balance' => $available,
            'pending_balance'   => $pending,
            'today_earnings'    => $todayEarnings,
            'total_earnings'    => $totalEarnings,
            'total_withdrawn'   => $totalWithdrawn,
            'currency'          => $wallet->currency,
            'status'            => $wallet->status,
        ];
    }

    /**
     * Credit wallet (add earning).
     * PRD §59: Order → Earnings → Wallet Credit
     *
     * @return WalletTransaction
     */
    public function credit(
        int $courierId,
        float $amount,
        string $type = 'earning',
        ?int $orderId = null,
        ?string $description = null,
        ?string $reference = null,
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($courierId);

        return DB::transaction(function () use ($wallet, $courierId, $amount, $type, $orderId, $description, $reference) {
            // PRD §27: Create ledger entry
            $transaction = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'courier_id'   => $courierId,
                'order_id'     => $orderId,
                'type'         => $type,
                'amount'       => $amount,
                'fee'          => 0,
                'net_amount'   => $amount,
                'status'       => 'completed',
                'reference'    => $reference,
                'description'  => $description ?? "Credit: {$type}",
            ]);

            // Update available balance
            $wallet->increment('available_balance', $amount);

            Log::info('[WalletService] Credited wallet', [
                'courier_id' => $courierId,
                'amount'     => $amount,
                'type'       => $type,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit wallet (general deduction, NOT withdrawal).
     */
    public function debit(
        int $courierId,
        float $amount,
        string $type = 'adjustment',
        ?string $description = null,
        ?string $reference = null,
    ): WalletTransaction {
        $wallet = $this->getOrCreateWallet($courierId);

        return DB::transaction(function () use ($wallet, $courierId, $amount, $type, $description, $reference) {
            $transaction = WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'courier_id'   => $courierId,
                'order_id'     => null,
                'type'         => $type,
                'amount'       => -$amount,
                'fee'          => 0,
                'net_amount'   => -$amount,
                'status'       => 'completed',
                'reference'    => $reference,
                'description'  => $description ?? "Debit: {$type}",
            ]);

            $wallet->decrement('available_balance', $amount);

            return $transaction;
        });
    }

    /**
     * Reserve balance for a withdrawal.
     * PRD §29: Move from available → pending.
     *
     * Returns true on success, throws on insufficient balance.
     */
    public function reserve(int $courierId, float $amount, string $withdrawalReference, ?string $description = null): bool
    {
        return DB::transaction(function () use ($courierId, $amount, $withdrawalReference, $description) {
            // PRD §28: Lock wallet row (SELECT FOR UPDATE)
            $wallet = Wallet::where('courier_id', $courierId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet tidak ditemukan.');
            }

            if ($wallet->status !== 'active') {
                throw new \RuntimeException('Wallet tidak aktif.');
            }

            if ((float) $wallet->available_balance < $amount) {
                throw new \RuntimeException('Saldo tidak mencukupi.');
            }

            // Move from available → pending
            $wallet->decrement('available_balance', $amount);
            $wallet->increment('pending_balance', $amount);

            // Create ledger entry for reservation
            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'courier_id'   => $courierId,
                'order_id'     => null,
                'type'         => 'withdrawal_hold',
                'amount'       => -$amount,
                'fee'          => 0,
                'net_amount'   => -$amount,
                'status'       => 'pending',
                'reference'    => $withdrawalReference,
                'description'  => $description ?? 'Penarikan OVO - dana ditahan',
            ]);

            Log::info('[WalletService] Reserved balance', [
                'courier_id' => $courierId,
                'amount'     => $amount,
            ]);

            return true;
        });
    }

    /**
     * Confirm reservation (DANA success) → reduce pending.
     * PRD §29: After DANA success, pending → zero.
     */
    public function confirmReservation(int $courierId, float $amount, string $withdrawalReference): void
    {
        DB::transaction(function () use ($courierId, $amount, $withdrawalReference) {
            $wallet = Wallet::where('courier_id', $courierId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet tidak ditemukan.');
            }

            $wallet->decrement('pending_balance', $amount);

            // Update ledger entry
            WalletTransaction::where('reference', $withdrawalReference)
                ->where('type', 'withdrawal_hold')
                ->update(['status' => 'completed']);

            Log::info('[WalletService] Confirmed reservation', [
                'courier_id' => $courierId,
                'amount'     => $amount,
            ]);
        });
    }

    /**
     * Release reservation (DANA failed) → move pending back to available.
     * PRD §30: If DANA fails, release reserved balance.
     */
    public function releaseReservation(int $courierId, float $amount, string $withdrawalReference): void
    {
        DB::transaction(function () use ($courierId, $amount, $withdrawalReference) {
            $wallet = Wallet::where('courier_id', $courierId)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet tidak ditemukan.');
            }

            $wallet->decrement('pending_balance', $amount);
            $wallet->increment('available_balance', $amount);

            // Update ledger entry (mark as released/reversed)
            WalletTransaction::where('reference', $withdrawalReference)
                ->where('type', 'withdrawal_hold')
                ->update(['status' => 'reversed']);

            // Add reversal ledger entry
            WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'courier_id'   => $courierId,
                'order_id'     => null,
                'type'         => 'withdrawal_release',
                'amount'       => $amount,
                'fee'          => 0,
                'net_amount'   => $amount,
                'status'       => 'completed',
                'reference'    => $withdrawalReference . '-RELEASE',
                'description'  => 'Penarikan gagal - dana dikembalikan',
            ]);

            Log::info('[WalletService] Released reservation', [
                'courier_id' => $courierId,
                'amount'     => $amount,
            ]);
        });
    }

    /**
     * Get transaction ledger (all entries for audit).
     * PRD §27: Ledger as audit trail.
     */
    public function getLedger(int $courierId, int $page = 1, int $perPage = 15): array
    {
        $wallet = $this->getOrCreateWallet($courierId);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'transactions' => $transactions->items(),
            'total'        => $transactions->total(),
            'page'         => $page,
            'per_page'     => $perPage,
        ];
    }
}
