<?php

namespace App\Modules\Ledger\Services;

use App\Models\Wallet;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Wallet\Models\WalletBalance;

class LedgerReconciliationService
{
    /**
     * Reconcile global double-entry balance and every wallet projection against ledger entries.
     *
     * @return array<string, mixed>
     */
    public function runReconciliation(): array
    {
        $totalDebit = (int) WalletLedgerEntry::where('entry_type', 'debit')->sum('amount');
        $totalCredit = (int) WalletLedgerEntry::where('entry_type', 'credit')->sum('amount');
        $globalBalanced = ($totalDebit === $totalCredit);

        // Verify every journal_id is individually balanced
        $unbalancedJournals = WalletLedgerEntry::query()
            ->selectRaw("journal_id, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END) as debit_sum, SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) as credit_sum")
            ->groupBy('journal_id')
            ->get()
            ->filter(fn ($row) => (int) $row->debit_sum !== (int) $row->credit_sum)
            ->values()
            ->map(fn ($row) => [
                'journal_id' => $row->journal_id,
                'debit' => (int) $row->debit_sum,
                'credit' => (int) $row->credit_sum,
            ])
            ->all();

        $mismatchedWallets = [];
        $wallets = Wallet::with('balanceRecord')->get();

        foreach ($wallets as $wallet) {
            $derivedAvailable = $this->derivedBucketBalance($wallet->id, WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE);
            $derivedPending = $this->derivedBucketBalance($wallet->id, WalletLedgerEntry::ACCOUNT_WALLET_PENDING);
            $derivedReserved = $this->derivedBucketBalance($wallet->id, WalletLedgerEntry::ACCOUNT_WALLET_RESERVED);

            $projection = $wallet->balanceRecord;
            $storedAvailable = $projection ? (int) $projection->available_balance : (int) round((float) $wallet->available_balance);
            $storedPending = $projection ? (int) $projection->pending_balance : (int) round((float) $wallet->pending_balance);
            $storedReserved = $projection ? (int) $projection->reserved_balance : (int) ($wallet->reserved_balance ?? 0);

            if (
                $derivedAvailable !== $storedAvailable
                || $derivedPending !== $storedPending
                || $derivedReserved !== $storedReserved
            ) {
                $mismatchedWallets[] = [
                    'wallet_id' => $wallet->id,
                    'wallet_type' => $wallet->wallet_type,
                    'user_id' => $wallet->user_id,
                    'courier_id' => $wallet->courier_id,
                    'stored' => [
                        'available' => $storedAvailable,
                        'pending' => $storedPending,
                        'reserved' => $storedReserved,
                    ],
                    'derived_from_ledger' => [
                        'available' => $derivedAvailable,
                        'pending' => $derivedPending,
                        'reserved' => $derivedReserved,
                    ],
                ];
            }
        }

        // System account trial summary
        $accountTotals = WalletLedgerEntry::query()
            ->selectRaw("account_code, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END) as total_debit, SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) as total_credit")
            ->groupBy('account_code')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->account_code => [
                    'debit' => (int) $row->total_debit,
                    'credit' => (int) $row->total_credit,
                    'net_credit_minus_debit' => (int) $row->total_credit - (int) $row->total_debit,
                ],
            ])
            ->all();

        $totalWalletAvailable = (int) WalletBalance::sum('available_balance');
        $totalWalletPending = (int) WalletBalance::sum('pending_balance');
        $totalWalletReserved = (int) WalletBalance::sum('reserved_balance');

        return [
            'checked_at' => now()->toIso8601String(),
            'global_balanced' => $globalBalanced,
            'total_ledger_debit' => $totalDebit,
            'total_ledger_credit' => $totalCredit,
            'imbalance_amount' => $totalDebit - $totalCredit,
            'unbalanced_journals_count' => count($unbalancedJournals),
            'unbalanced_journals' => $unbalancedJournals,
            'wallets_checked' => $wallets->count(),
            'mismatched_wallets_count' => count($mismatchedWallets),
            'mismatched_wallets' => $mismatchedWallets,
            'is_clean' => $globalBalanced && count($unbalancedJournals) === 0 && count($mismatchedWallets) === 0,
            'total_wallet_liabilities' => [
                'available' => $totalWalletAvailable,
                'pending' => $totalWalletPending,
                'reserved' => $totalWalletReserved,
                'grand_total' => $totalWalletAvailable + $totalWalletPending + $totalWalletReserved,
            ],
            'account_totals' => $accountTotals,
        ];
    }

    public function derivedBucketBalance(int $walletId, string $accountCode): int
    {
        $row = WalletLedgerEntry::query()
            ->where('wallet_id', $walletId)
            ->where('account_code', $accountCode)
            ->selectRaw("SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END) as credit_sum, SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END) as debit_sum")
            ->first();

        if (! $row) {
            return 0;
        }

        return (int) $row->credit_sum - (int) $row->debit_sum;
    }
}
