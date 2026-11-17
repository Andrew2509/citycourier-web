<?php

namespace App\Modules\Ledger\Services;

use App\Models\Wallet;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Wallet\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class DoubleEntryLedgerService
{
    /**
     * Record a balanced double-entry journal atomically inside a DB transaction
     * and synchronize impacted wallet_balances + legacy wallets projections.
     *
     * Each line item requires:
     * - wallet_id: int|null
     * - user_id: int|null
     * - account_code: string
     * - entry_type: 'debit'|'credit'
     * - amount: int (> 0, IDR BIGINT)
     * - dedupe_key: string (optional; auto-generated per line if omitted)
     * - description: string|null
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{journal_id: string, idempotent_replay: bool, entries: array<int, WalletLedgerEntry>}
     */
    public function recordJournal(
        string $journalReference,
        string $transactionType,
        array $lines,
        ?string $idempotencyKey = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A double-entry journal requires at least two lines.');
        }

        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $entryType = $line['entry_type'] ?? '';
            $amount = (int) ($line['amount'] ?? 0);

            if (! in_array($entryType, ['debit', 'credit'], true)) {
                throw new InvalidArgumentException("Invalid ledger entry_type: {$entryType}");
            }
            if ($amount <= 0) {
                throw new InvalidArgumentException('Ledger entry amount must be positive integer IDR.');
            }
            if (empty($line['account_code'])) {
                throw new InvalidArgumentException('Ledger line must include account_code.');
            }

            if ($entryType === 'debit') {
                $totalDebit += $amount;
            } else {
                $totalCredit += $amount;
            }
        }

        if ($totalDebit !== $totalCredit || $totalDebit <= 0) {
            throw new RuntimeException(
                "Unbalanced ledger journal {$journalReference}: debit={$totalDebit}, credit={$totalCredit}."
            );
        }

        return DB::transaction(function () use (
            $journalReference,
            $transactionType,
            $lines,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        ) {
            if ($idempotencyKey) {
                $existing = WalletLedgerEntry::where('idempotency_key', $idempotencyKey)->get();
                if ($existing->isNotEmpty()) {
                    return [
                        'journal_id' => (string) $existing->first()->journal_id,
                        'idempotent_replay' => true,
                        'entries' => $existing->all(),
                    ];
                }
            }

            // Lock impacted wallets in ascending order to prevent deadlocks
            $walletIds = collect($lines)
                ->pluck('wallet_id')
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            /** @var array<int, Wallet> $lockedWallets */
            $lockedWallets = [];
            /** @var array<int, WalletBalance> $lockedBalances */
            $lockedBalances = [];

            foreach ($walletIds as $walletId) {
                $wallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
                $lockedWallets[$walletId] = $wallet;
                $lockedBalances[$walletId] = $this->lockOrCreateBalance($wallet);
            }

            $journalId = (string) Str::uuid();
            $now = now();
            $createdEntries = [];

            foreach ($lines as $index => $line) {
                $lineDedupeKey = (string) ($line['dedupe_key'] ?? sprintf(
                    '%s:%d:%s:%s',
                    $idempotencyKey ?? $journalReference,
                    $index,
                    $line['account_code'],
                    $line['entry_type']
                ));

                $dupEntry = WalletLedgerEntry::where('dedupe_key', $lineDedupeKey)->first();
                if ($dupEntry) {
                    $existingAll = WalletLedgerEntry::where('journal_id', $dupEntry->journal_id)->get();

                    return [
                        'journal_id' => (string) $dupEntry->journal_id,
                        'idempotent_replay' => true,
                        'entries' => $existingAll->all(),
                    ];
                }

                $amount = (int) $line['amount'];
                $accountCode = (string) $line['account_code'];
                $entryType = (string) $line['entry_type'];
                $walletId = isset($line['wallet_id']) ? (int) $line['wallet_id'] : null;

                $entry = WalletLedgerEntry::create([
                    'journal_id' => $journalId,
                    'journal_reference' => $journalReference,
                    'dedupe_key' => $lineDedupeKey,
                    'wallet_id' => $walletId,
                    'user_id' => $line['user_id'] ?? ($walletId ? $lockedWallets[$walletId]->user_id : null),
                    'account_code' => $accountCode,
                    'entry_type' => $entryType,
                    'amount' => $amount,
                    'currency' => 'IDR',
                    'transaction_type' => $transactionType,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'idempotency_key' => $idempotencyKey,
                    'mode' => $mode,
                    'description' => $line['description'] ?? null,
                    'metadata' => array_merge($metadata, $line['metadata'] ?? []),
                    'created_at' => $now,
                ]);

                $createdEntries[] = $entry;

                if ($walletId !== null && in_array($accountCode, WalletLedgerEntry::BUCKET_ACCOUNTS, true)) {
                    $this->applyBucketDelta(
                        $lockedBalances[$walletId],
                        $accountCode,
                        $entryType,
                        $amount,
                        $entry->id
                    );
                }
            }

            foreach ($lockedWallets as $walletId => $wallet) {
                $balance = $lockedBalances[$walletId];
                $balance->save();

                $wallet->available_balance = $balance->available_balance;
                $wallet->pending_balance = $balance->pending_balance;
                if (Schema::hasColumn('wallets', 'reserved_balance')) {
                    $wallet->reserved_balance = $balance->reserved_balance;
                }
                $wallet->save();
            }

            return [
                'journal_id' => $journalId,
                'idempotent_replay' => false,
                'entries' => $createdEntries,
            ];
        });
    }

    public function lockOrCreateBalance(Wallet $wallet): WalletBalance
    {
        $balance = WalletBalance::where('wallet_id', $wallet->id)->lockForUpdate()->first();
        if ($balance) {
            return $balance;
        }

        return WalletBalance::create([
            'wallet_id' => $wallet->id,
            'currency' => $wallet->currency ?: 'IDR',
            'available_balance' => (int) round((float) $wallet->available_balance),
            'pending_balance' => (int) round((float) $wallet->pending_balance),
            'reserved_balance' => Schema::hasColumn('wallets', 'reserved_balance') ? (int) $wallet->reserved_balance : 0,
            'total_credited' => 0,
            'total_debited' => 0,
            'version' => 1,
        ]);
    }

    private function applyBucketDelta(
        WalletBalance $balance,
        string $accountCode,
        string $entryType,
        int $amount,
        int $entryId
    ): void {
        // Liability bucket: credit increases balance, debit decreases balance.
        $signedDelta = $entryType === 'credit' ? $amount : -$amount;

        switch ($accountCode) {
            case WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE:
                $next = (int) $balance->available_balance + $signedDelta;
                if ($next < 0) {
                    throw new RuntimeException('Insufficient wallet available balance for ledger debit.');
                }
                $balance->available_balance = $next;
                if ($entryType === 'credit') {
                    $balance->total_credited += $amount;
                } else {
                    $balance->total_debited += $amount;
                }
                break;

            case WalletLedgerEntry::ACCOUNT_WALLET_PENDING:
                $next = (int) $balance->pending_balance + $signedDelta;
                if ($next < 0) {
                    throw new RuntimeException('Insufficient wallet pending balance for ledger debit.');
                }
                $balance->pending_balance = $next;
                break;

            case WalletLedgerEntry::ACCOUNT_WALLET_RESERVED:
                $next = (int) $balance->reserved_balance + $signedDelta;
                if ($next < 0) {
                    throw new RuntimeException('Insufficient wallet reserved balance for ledger debit.');
                }
                $balance->reserved_balance = $next;
                break;
        }

        $balance->last_ledger_entry_id = $entryId;
        $balance->version = (int) $balance->version + 1;
    }
}
