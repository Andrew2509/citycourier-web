<?php

namespace App\Modules\Wallet\Services;

use App\Models\Courier;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orkestrasi transaksi dompet (WalletTransactionService):
 * - Membaca ringkasan saldo real-time (available, pending, reserved)
 * - Settlement top-up (idempoten, double-entry)
 * - Hold / reserve saldo atomik saat withdrawal agar tidak dapat ditarik dua kali
 * - Finalisasi debit atau pengembalian penuh saldo tertahan saat payout gagal
 */
class WalletTransactionService
{
    public function __construct(
        private readonly WalletLedgerManager $ledgerManager
    ) {}

    public function getOrCreateCustomerWallet(User $user): Wallet
    {
        return $this->ledgerManager->getOrCreateCustomerWallet($user);
    }

    public function getOrCreateCourierWallet(Courier $courier): Wallet
    {
        return $this->ledgerManager->getOrCreateCourierWallet($courier);
    }

    public function getWalletSummary(Wallet $wallet): array
    {
        $balance = $this->ledgerManager->ensureBalanceProjection($wallet);

        return [
            'wallet_id' => $wallet->id,
            'wallet_type' => $wallet->wallet_type,
            'currency' => $wallet->currency ?: 'IDR',
            'status' => $wallet->status,
            'is_frozen' => $wallet->isFrozen(),
            'available_balance' => (int) round((float) $balance->available_balance),
            'pending_balance' => (int) round((float) $balance->pending_balance),
            'reserved_balance' => (int) round((float) $balance->reserved_balance),
            'total_credited' => (int) $balance->total_credited,
            'total_debited' => (int) $balance->total_debited,
        ];
    }

    public function creditTopupSettlement(
        Wallet $wallet,
        int $amountIdr,
        string $referenceId,
        string $idempotencyKey,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        string $mode = 'sandbox',
        array $meta = []
    ): WalletLedgerEntry {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal top-up harus lebih besar dari 0.');
        }

        return $this->ledgerManager->creditAvailable(
            $wallet,
            $amountIdr,
            WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
            'topup',
            $referenceId,
            $idempotencyKey,
            $description,
            $sourceType,
            $sourceId,
            $mode,
            $meta
        );
    }

    /**
     * Tahan saldo secara atomik untuk withdrawal sehingga penarikan paralel tidak dapat menggandakan saldo keluar.
     */
    public function reserveForWithdrawal(
        Wallet $wallet,
        int $amountIdr,
        string $referenceId,
        string $idempotencyKey,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        string $mode = 'sandbox',
        array $meta = []
    ): WalletLedgerEntry {
        if ($amountIdr <= 0) {
            throw new InvalidArgumentException('Nominal penarikan harus lebih besar dari 0.');
        }

        return DB::transaction(function () use (
            $wallet,
            $amountIdr,
            $referenceId,
            $idempotencyKey,
            $description,
            $sourceType,
            $sourceId,
            $mode,
            $meta
        ) {
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $this->ledgerManager->assertNotFrozen($lockedWallet);

            $balance = $this->ledgerManager->ensureBalanceProjection($lockedWallet);
            $available = (int) round((float) $balance->available_balance);
            if ($available < $amountIdr) {
                throw new RuntimeException("INSUFFICIENT_BALANCE:Saldo tersedia Rp {$available} tidak mencukupi.");
            }

            return $this->ledgerManager->reserveForPayout(
                $lockedWallet,
                $amountIdr,
                $referenceId,
                $idempotencyKey,
                $description,
                $sourceType,
                $sourceId,
                $mode,
                $meta
            );
        });
    }

    public function settleWithdrawalSuccess(
        Wallet $wallet,
        int $amountIdr,
        int $feeIdr,
        string $referenceId,
        string $idempotencyKey,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        string $mode = 'sandbox',
        array $meta = []
    ): WalletLedgerEntry {
        return $this->ledgerManager->confirmReservedPayout(
            $wallet,
            $amountIdr,
            $feeIdr,
            $referenceId,
            $idempotencyKey,
            $description,
            $sourceType,
            $sourceId,
            $mode,
            $meta
        );
    }

    /**
     * Kembalikan saldo tertahan ke available_balance saat payout gagal atau dibatalkan penyedia.
     */
    public function refundFailedWithdrawal(
        Wallet $wallet,
        int $amountIdr,
        string $referenceId,
        string $idempotencyKey,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        string $mode = 'sandbox',
        array $meta = []
    ): WalletLedgerEntry {
        return $this->ledgerManager->releaseReservedPayout(
            $wallet,
            $amountIdr,
            $referenceId,
            $idempotencyKey,
            $description,
            $sourceType,
            $sourceId,
            $mode,
            $meta
        );
    }
}
