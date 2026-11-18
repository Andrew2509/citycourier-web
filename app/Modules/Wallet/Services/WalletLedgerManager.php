<?php

namespace App\Modules\Wallet\Services;

use App\Models\Courier;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Ledger\Services\DoubleEntryLedgerService;
use App\Modules\Wallet\Models\WalletAuditLog;
use App\Modules\Wallet\Models\WalletBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class WalletLedgerManager
{
    public function __construct(
        private readonly DoubleEntryLedgerService $ledger
    ) {}

    public function getOrCreateCustomerWallet(User $user): Wallet
    {
        return DB::transaction(function () use ($user) {
            $wallet = Wallet::where('user_id', $user->id)
                ->where('wallet_type', 'customer')
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'courier_id' => null,
                    'wallet_type' => 'customer',
                    'status' => 'active',
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'reserved_balance' => 0,
                    'currency' => 'IDR',
                ]);
            }

            $this->ensureBalanceProjection($wallet);

            return $wallet->fresh(['balanceRecord']);
        });
    }

    public function getOrCreateCourierWallet(Courier $courier): Wallet
    {
        return DB::transaction(function () use ($courier) {
            $wallet = Wallet::where('courier_id', $courier->id)
                ->where(function ($q) {
                    $q->where('wallet_type', 'courier')->orWhereNull('wallet_type');
                })
                ->lockForUpdate()
                ->first();

            if (! $wallet && $courier->user_id) {
                $wallet = Wallet::where('user_id', $courier->user_id)
                    ->where('wallet_type', 'courier')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $courier->user_id,
                    'courier_id' => $courier->id,
                    'wallet_type' => 'courier',
                    'status' => 'active',
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'reserved_balance' => 0,
                    'currency' => 'IDR',
                ]);
            } else {
                $dirty = false;
                if ($courier->user_id && ! $wallet->user_id) {
                    $conflict = Wallet::where('user_id', $courier->user_id)
                        ->where('wallet_type', 'courier')
                        ->where('id', '!=', $wallet->id)
                        ->exists();
                    if (! $conflict) {
                        $wallet->user_id = $courier->user_id;
                        $dirty = true;
                    }
                }
                if (! $wallet->wallet_type) {
                    $wallet->wallet_type = 'courier';
                    $dirty = true;
                }
                if ($wallet->status === 'not_active') {
                    $wallet->status = 'active';
                    $dirty = true;
                }
                if ($dirty) {
                    $wallet->save();
                }
            }

            $this->ensureBalanceProjection($wallet);

            return $wallet->fresh(['balanceRecord']);
        });
    }

    public function ensureBalanceProjection(Wallet $wallet): WalletBalance
    {
        $balance = $this->ledger->lockOrCreateBalance($wallet);

        // If a legacy pre-ledger wallet had non-zero balance before any ledger rows exist,
        // create an opening adjustment journal so double-entry totals remain exact.
        $hasEntries = WalletLedgerEntry::where('wallet_id', $wallet->id)->exists();
        if (! $hasEntries && ($balance->available_balance > 0 || $balance->pending_balance > 0 || $balance->reserved_balance > 0)) {
            $lines = [];
            $totalOpening = 0;

            if ($balance->available_balance > 0) {
                $amt = (int) $balance->available_balance;
                $totalOpening += $amt;
                $lines[] = [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                    'entry_type' => 'credit',
                    'amount' => $amt,
                    'description' => 'Opening available balance backfill into ledger',
                ];
            }

            if ($balance->pending_balance > 0) {
                $amt = (int) $balance->pending_balance;
                $totalOpening += $amt;
                $lines[] = [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_PENDING,
                    'entry_type' => 'credit',
                    'amount' => $amt,
                    'description' => 'Opening pending balance backfill into ledger',
                ];
            }

            if ($balance->reserved_balance > 0) {
                $amt = (int) $balance->reserved_balance;
                $totalOpening += $amt;
                $lines[] = [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_RESERVED,
                    'entry_type' => 'credit',
                    'amount' => $amt,
                    'description' => 'Opening reserved balance backfill into ledger',
                ];
            }

            if ($totalOpening > 0) {
                // Zero-sum contra line and reset projection before applying journal cleanly
                $balance->available_balance = 0;
                $balance->pending_balance = 0;
                $balance->reserved_balance = 0;
                $balance->save();

                array_unshift($lines, [
                    'wallet_id' => null,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_SYSTEM_SUSPENSE_ADJUSTMENT,
                    'entry_type' => 'debit',
                    'amount' => $totalOpening,
                    'description' => 'Opening legacy wallet liability offset',
                ]);

                $this->ledger->recordJournal(
                    'OPENING-WALLET-'.$wallet->id,
                    'legacy_opening_balance',
                    $lines,
                    'opening-wallet-'.$wallet->id,
                    Wallet::class,
                    $wallet->id,
                    (string) config('payment_wallet.mode', 'sandbox')
                );

                return $balance->fresh();
            }
        }

        return $balance;
    }

    public function assertNotFrozen(Wallet $wallet): void
    {
        if ($wallet->status === 'frozen' || $wallet->status === 'suspended' || $wallet->frozen_at !== null) {
            throw new RuntimeException('Wallet sedang dibekukan oleh admin: '.($wallet->freeze_reason ?: 'Tidak dapat bertransaksi sementara.'));
        }
    }

    /**
     * Credit wallet available balance (e.g. TopUp settled, Refund credited) + log WalletTransaction.
     */
    public function creditAvailable(
        Wallet $wallet,
        int $amount,
        string $sourceAccountCode,
        string $transactionType,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $sourceAccountCode,
            $transactionType,
            $journalReference,
            $idempotencyKey,
            $description,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        ) {
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $journal = $this->ledger->recordJournal(
                $journalReference,
                $transactionType,
                [
                    [
                        'wallet_id' => null,
                        'user_id' => $locked->user_id,
                        'account_code' => $sourceAccountCode,
                        'entry_type' => 'debit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                        'entry_type' => 'credit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                ],
                $idempotencyKey,
                $referenceType,
                $referenceId,
                $mode,
                $metadata
            );

            if (! $journal['idempotent_replay']) {
                $freshBalance = WalletBalance::where('wallet_id', $locked->id)->firstOrFail();
                $this->recordWalletTransactionRow(
                    $locked,
                    $transactionType,
                    'credit',
                    $amount,
                    (int) $freshBalance->available_balance,
                    $journalReference,
                    $idempotencyKey,
                    $description,
                    $mode,
                    $metadata
                );
            }

            return $journal;
        });
    }

    /**
     * Debit wallet available balance (e.g. Order payment by wallet) + log WalletTransaction.
     */
    public function debitAvailable(
        Wallet $wallet,
        int $amount,
        string $destinationAccountCode,
        string $transactionType,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = [],
        bool $ignoreFreeze = false
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $destinationAccountCode,
            $transactionType,
            $journalReference,
            $idempotencyKey,
            $description,
            $referenceType,
            $referenceId,
            $mode,
            $metadata,
            $ignoreFreeze
        ) {
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            if (! $ignoreFreeze) {
                $this->assertNotFrozen($locked);
            }

            $journal = $this->ledger->recordJournal(
                $journalReference,
                $transactionType,
                [
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                        'entry_type' => 'debit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                    [
                        'wallet_id' => null,
                        'user_id' => $locked->user_id,
                        'account_code' => $destinationAccountCode,
                        'entry_type' => 'credit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                ],
                $idempotencyKey,
                $referenceType,
                $referenceId,
                $mode,
                $metadata
            );

            if (! $journal['idempotent_replay']) {
                $freshBalance = WalletBalance::where('wallet_id', $locked->id)->firstOrFail();
                $this->recordWalletTransactionRow(
                    $locked,
                    $transactionType,
                    'debit',
                    $amount,
                    (int) $freshBalance->available_balance,
                    $journalReference,
                    $idempotencyKey,
                    $description,
                    $mode,
                    $metadata
                );
            }

            return $journal;
        });
    }

    /**
     * Reserve available balance into WALLET_RESERVED for an in-flight payout or withdrawal.
     */
    public function reserveForPayout(
        Wallet $wallet,
        int $amount,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        return DB::transaction(function () use (
            $wallet,
            $amount,
            $journalReference,
            $idempotencyKey,
            $description,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        ) {
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $this->assertNotFrozen($locked);

            $journal = $this->ledger->recordJournal(
                $journalReference,
                'payout_reserve',
                [
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                        'entry_type' => 'debit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_RESERVED,
                        'entry_type' => 'credit',
                        'amount' => $amount,
                        'description' => $description,
                    ],
                ],
                $idempotencyKey,
                $referenceType,
                $referenceId,
                $mode,
                $metadata
            );

            if (! $journal['idempotent_replay']) {
                $freshBalance = WalletBalance::where('wallet_id', $locked->id)->firstOrFail();
                $this->recordWalletTransactionRow(
                    $locked,
                    $locked->wallet_type === 'customer' ? 'withdrawal' : 'payout',
                    'debit',
                    $amount,
                    (int) $freshBalance->available_balance,
                    $journalReference,
                    $idempotencyKey,
                    $description,
                    $mode,
                    $metadata,
                    'pending'
                );
            }

            return $journal;
        });
    }

    /**
     * Confirm an in-flight reserved payout/withdrawal as disbursed to external payout clearing.
     */
    public function confirmReservedPayout(
        Wallet $wallet,
        int $grossAmount,
        int $feeAmount,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');
        $netAmount = $grossAmount - $feeAmount;

        if ($netAmount <= 0 || $feeAmount < 0) {
            throw new RuntimeException('Invalid gross/fee amounts when confirming payout.');
        }

        $lines = [
            [
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_RESERVED,
                'entry_type' => 'debit',
                'amount' => $grossAmount,
                'description' => $description,
            ],
            [
                'wallet_id' => null,
                'user_id' => $wallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_SYSTEM_PAYOUT_CLEARING,
                'entry_type' => 'credit',
                'amount' => $netAmount,
                'description' => $description,
            ],
        ];

        if ($feeAmount > 0) {
            $lines[] = [
                'wallet_id' => null,
                'user_id' => $wallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_SYSTEM_PLATFORM_REVENUE,
                'entry_type' => 'credit',
                'amount' => $feeAmount,
                'description' => 'Payout/withdrawal admin fee',
            ];
        }

        $journal = $this->ledger->recordJournal(
            $journalReference,
            'payout_settled',
            $lines,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        );

        if (! $journal['idempotent_replay']) {
            $refCol = Schema::hasColumn('wallet_transactions', 'reference') ? 'reference' : 'reference_id';
            WalletTransaction::where($refCol, str_replace('-SETTLE', '', $journalReference))
                ->where('status', 'pending')
                ->update(['status' => 'completed']);
        }

        return $journal;
    }

    /**
     * Release reserved funds back into WALLET_AVAILABLE after confirmed payout failure.
     */
    public function releaseReservedPayout(
        Wallet $wallet,
        int $amount,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        $journal = $this->ledger->recordJournal(
            $journalReference,
            'payout_reversal',
            [
                [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_RESERVED,
                    'entry_type' => 'debit',
                    'amount' => $amount,
                    'description' => $description,
                ],
                [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                    'entry_type' => 'credit',
                    'amount' => $amount,
                    'description' => $description,
                ],
            ],
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        );

        if (! $journal['idempotent_replay']) {
            $freshBalance = WalletBalance::where('wallet_id', $wallet->id)->firstOrFail();
            $refCol = Schema::hasColumn('wallet_transactions', 'reference') ? 'reference' : 'reference_id';
            WalletTransaction::where($refCol, str_replace('-RELEASE', '', $journalReference))
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            $this->recordWalletTransactionRow(
                $wallet,
                'payout_reversal',
                'credit',
                $amount,
                (int) $freshBalance->available_balance,
                $journalReference,
                $idempotencyKey,
                $description,
                $mode,
                $metadata
            );
        }

        return $journal;
    }

    /**
     * Credit courier WALLET_PENDING upon verified delivery, recording commission revenue separately.
     */
    public function creditPendingEarning(
        Wallet $courierWallet,
        int $grossAmount,
        int $commissionAmount,
        int $netAmount,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        if ($grossAmount <= 0 || $netAmount <= 0 || ($commissionAmount + $netAmount) !== $grossAmount) {
            throw new RuntimeException("Invalid earning split: gross={$grossAmount}, commission={$commissionAmount}, net={$netAmount}.");
        }

        $lines = [
            [
                'wallet_id' => null,
                'user_id' => $courierWallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_SYSTEM_ORDER_ESCROW,
                'entry_type' => 'debit',
                'amount' => $grossAmount,
                'description' => $description,
            ],
            [
                'wallet_id' => $courierWallet->id,
                'user_id' => $courierWallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_PENDING,
                'entry_type' => 'credit',
                'amount' => $netAmount,
                'description' => $description,
            ],
        ];

        if ($commissionAmount > 0) {
            $lines[] = [
                'wallet_id' => null,
                'user_id' => $courierWallet->user_id,
                'account_code' => WalletLedgerEntry::ACCOUNT_SYSTEM_PLATFORM_REVENUE,
                'entry_type' => 'credit',
                'amount' => $commissionAmount,
                'description' => 'Platform commission deduction',
            ];
        }

        return $this->ledger->recordJournal(
            $journalReference,
            'courier_earning_pending',
            $lines,
            $idempotencyKey,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        );
    }

    /**
     * Release settled courier earnings from WALLET_PENDING to WALLET_AVAILABLE.
     */
    public function releasePendingToAvailable(
        Wallet $courierWallet,
        int $netAmount,
        string $journalReference,
        string $idempotencyKey,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $mode = null,
        array $metadata = []
    ): array {
        $mode = $mode ?? (string) config('payment_wallet.mode', 'sandbox');

        return DB::transaction(function () use (
            $courierWallet,
            $netAmount,
            $journalReference,
            $idempotencyKey,
            $description,
            $referenceType,
            $referenceId,
            $mode,
            $metadata
        ) {
            $locked = Wallet::where('id', $courierWallet->id)->lockForUpdate()->firstOrFail();

            $journal = $this->ledger->recordJournal(
                $journalReference,
                'courier_earning_settled',
                [
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_PENDING,
                        'entry_type' => 'debit',
                        'amount' => $netAmount,
                        'description' => $description,
                    ],
                    [
                        'wallet_id' => $locked->id,
                        'user_id' => $locked->user_id,
                        'account_code' => WalletLedgerEntry::ACCOUNT_WALLET_AVAILABLE,
                        'entry_type' => 'credit',
                        'amount' => $netAmount,
                        'description' => $description,
                    ],
                ],
                $idempotencyKey,
                $referenceType,
                $referenceId,
                $mode,
                $metadata
            );

            if (! $journal['idempotent_replay']) {
                $freshBalance = WalletBalance::where('wallet_id', $locked->id)->firstOrFail();
                $this->recordWalletTransactionRow(
                    $locked,
                    'earning',
                    'credit',
                    $netAmount,
                    (int) $freshBalance->available_balance,
                    $journalReference,
                    $idempotencyKey,
                    $description,
                    $mode,
                    $metadata
                );
            }

            return $journal;
        });
    }

    public function recordWalletTransactionRow(
        Wallet $wallet,
        string $type,
        string $direction,
        int $amountIdr,
        int $balanceAfter,
        string $referenceId,
        ?string $idempotencyKey,
        string $description,
        string $mode,
        array $metadata = [],
        string $status = 'completed'
    ): WalletTransaction {
        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $signedAmount = $direction === 'debit' ? -$amountIdr : $amountIdr;
        $refCol = Schema::hasColumn('wallet_transactions', 'reference') ? 'reference' : 'reference_id';

        $data = [
            'wallet_id' => $wallet->id,
            'courier_id' => $wallet->courier_id,
            'type' => $type,
            'amount' => $signedAmount,
            'status' => $status,
            $refCol => $referenceId,
            'description' => $description,
        ];

        if (Schema::hasColumn('wallet_transactions', 'fee')) {
            $data['fee'] = 0;
        }
        if (Schema::hasColumn('wallet_transactions', 'net_amount')) {
            $data['net_amount'] = $signedAmount;
        }

        if (Schema::hasColumn('wallet_transactions', 'user_id')) {
            $data['user_id'] = $wallet->user_id;
            $data['wallet_type'] = $wallet->wallet_type ?: 'courier';
            $data['direction'] = $direction;
            $data['amount_idr'] = $amountIdr;
            $data['balance_after'] = $balanceAfter;
            $data['idempotency_key'] = $idempotencyKey ?: Str::uuid()->toString();
            $data['mode'] = $mode;
            $data['metadata'] = $metadata;
        }

        return WalletTransaction::create($data);
    }

    public function writeAuditLog(
        ?int $actorUserId,
        string $actorType,
        ?int $walletId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): WalletAuditLog {
        return WalletAuditLog::create([
            'actor_user_id' => $actorUserId,
            'actor_type' => $actorType,
            'wallet_id' => $walletId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? Str::limit($userAgent, 250) : null,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
