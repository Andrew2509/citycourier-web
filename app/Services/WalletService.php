<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Modules\Payout\Models\PayoutTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        // Total saldo yang sudah berhasil dicairkan (legacy + v1 payout_transactions)
        $totalWithdrawn = (float) Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['success', 'completed'])
            ->sum('amount');

        if (Schema::hasTable('payout_transactions')) {
            $totalWithdrawn += (float) PayoutTransaction::where('courier_id', $courierId)
                ->whereNull('legacy_withdrawal_id')
                ->whereIn('status', ['success', 'completed'])
                ->sum('amount');
        }

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
                'description'  => $description ?? 'Penarikan - dana ditahan',
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
     * Unified wallet history for the courier app.
     *
     * Satu sumber kebenaran per jenis transaksi:
     * - `withdrawals`  → penarikan (status resmi provider: pending/processing/success/failed)
     * - `wallet_transactions` → pendapatan, bonus, koreksi, dan pengembalian saldo
     *
     * Ledger `withdrawal_hold`/`withdrawal` sengaja tidak dipakai sebagai baris
     * riwayat karena baris penarikan sudah diambil dari tabel `withdrawals` —
     * memakai keduanya akan menghitung penarikan dua kali di UI.
     */
    public function getHistory(
        int $courierId,
        int $page = 1,
        int $perPage = 15,
        ?string $filter = null,
        ?string $status = null
    ): array {
        $wallet = $this->getOrCreateWallet($courierId);

        $items = collect();

        // 1. Pendapatan, bonus, koreksi, pengembalian saldo (ledger).
        $ledgerEntries = WalletTransaction::where('wallet_id', $wallet->id)
            ->whereIn('type', ['earning', 'bonus', 'adjustment', 'withdrawal_release', 'payout_reversal'])
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        foreach ($ledgerEntries as $entry) {
            $items->push($this->normalizeLedgerEntry($entry));
        }

        // 2. Penarikan legacy (`withdrawals`).
        $withdrawals = Withdrawal::where('courier_id', $courierId)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $items->push($this->normalizeWithdrawalEntry($withdrawal));
        }

        // 3. Penarikan & auto-payout v1 (`payout_transactions`).
        if (Schema::hasTable('payout_transactions')) {
            $payoutQuery = PayoutTransaction::where('courier_id', $courierId)
                ->whereNull('legacy_withdrawal_id')
                ->orderByDesc('created_at')
                ->limit(300);

            if (Schema::hasTable('payout_destinations')) {
                $payoutQuery->with('destination');
            }

            foreach ($payoutQuery->get() as $payout) {
                $items->push($this->normalizePayoutEntry($payout));
            }
        }

        // Filter jenis transaksi.
        $filter = $filter ? strtolower($filter) : 'all';
        if ($filter === 'earning' || $filter === 'pendapatan') {
            $items = $items->whereIn('kind', ['earning', 'bonus']);
        } elseif ($filter === 'withdrawal' || $filter === 'penarikan') {
            $items = $items->whereIn('kind', ['withdrawal', 'reversal']);
        }

        // Filter status.
        if ($status) {
            $status = strtolower($status);
            $items = $items->where('status', $status);
        }

        $items = $items->sortByDesc(fn($item) => $item['created_at'])->values();

        $total = $items->count();
        $offset = max(0, ($page - 1) * $perPage);

        return [
            'transactions' => $items->slice($offset, $perPage)->values()->all(),
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'has_more'     => ($offset + $perPage) < $total,
            'filter'       => $filter,
            'status'       => $status,
        ];
    }

    /**
     * Notifikasi dompet — diturunkan dari status transaksi yang sebenarnya,
     * bukan dari daftar statis.
     */
    public function getNotifications(int $courierId, int $limit = 20): array
    {
        $notifications = collect();

        if (Schema::hasTable('dana_connections')) {
            $connection = \App\Models\DanaConnection::where('courier_id', $courierId)
                ->where('status', 'connected')
                ->first();

            if ($connection) {
                $connectedAt = $connection->bound_at ?? $connection->linked_at ?? $connection->updated_at;
                $notifications->push([
                    'id'         => 'dana-connect-' . $connection->id,
                    'type'       => 'dana_connected',
                    'title'      => 'Akun DANA terhubung',
                    'message'    => 'Pencairan saldo kini dikirim ke ' . ($connection->masked_phone ?? 'akun DANA Anda') . '.',
                    'level'      => 'success',
                    'created_at' => $connectedAt?->toIso8601String(),
                ]);
            }
        }

        $withdrawals = Withdrawal::where('courier_id', $courierId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            [$title, $message, $level] = $this->withdrawalNotification($withdrawal);
            $createdAt = $withdrawal->processed_at ?? $withdrawal->updated_at ?? $withdrawal->created_at;

            $notifications->push([
                'id'         => 'withdrawal-' . $withdrawal->id,
                'type'       => 'withdrawal_' . $withdrawal->status,
                'title'      => $title,
                'message'    => $message,
                'level'      => $level,
                'created_at' => $createdAt?->toIso8601String(),
            ]);
        }

        if (Schema::hasTable('payout_transactions')) {
            $payoutQuery = PayoutTransaction::where('courier_id', $courierId)
                ->whereNull('legacy_withdrawal_id')
                ->orderByDesc('created_at')
                ->limit(20);

            if (Schema::hasTable('payout_destinations')) {
                $payoutQuery->with('destination');
            }

            foreach ($payoutQuery->get() as $payout) {
                [$title, $message, $level] = $this->payoutNotification($payout);
                $createdAt = $payout->completed_at ?? $payout->failed_at ?? $payout->updated_at ?? $payout->created_at;

                $notifications->push([
                    'id'         => 'payout-' . $payout->id,
                    'type'       => 'withdrawal_' . $this->normalizeWithdrawalStatus($payout->status),
                    'title'      => $title,
                    'message'    => $message,
                    'level'      => $level,
                    'created_at' => $createdAt?->toIso8601String(),
                ]);
            }
        }

        $reversals = WalletTransaction::where('courier_id', $courierId)
            ->whereIn('type', ['withdrawal_release', 'payout_reversal'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        foreach ($reversals as $reversal) {
            $notifications->push([
                'id'         => 'reversal-' . $reversal->id,
                'type'       => 'balance_returned',
                'title'      => 'Saldo dikembalikan',
                'message'    => 'Saldo penarikan yang gagal sudah kembali ke saldo tersedia Anda.',
                'level'      => 'info',
                'created_at' => $reversal->created_at?->toIso8601String(),
            ]);
        }

        return $notifications
            ->filter(fn($item) => !empty($item['created_at']))
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Normalisasi satu baris ledger menjadi bentuk riwayat aplikasi.
     */
    private function normalizeLedgerEntry(WalletTransaction $entry): array
    {
        $amount = (float) $entry->amount;

        $kind = match ($entry->type) {
            'bonus'                              => 'bonus',
            'adjustment'                         => 'adjustment',
            'withdrawal_release', 'payout_reversal' => 'reversal',
            default                              => 'earning',
        };

        $title = match ($kind) {
            'bonus'      => $entry->description ?: 'Bonus',
            'adjustment' => $entry->description ?: 'Penyesuaian saldo',
            'reversal'   => 'Pengembalian saldo',
            default      => $entry->description ?: 'Pendapatan pengiriman',
        };

        $status = match ($entry->status) {
            'pending'  => 'pending',
            'reversed' => 'cancelled',
            default    => 'success',
        };

        $createdAt = $entry->created_at;

        return [
            'id'             => 'tx-' . $entry->id,
            'kind'           => $kind,
            'title'          => $title,
            'description'    => $entry->description,
            'amount'         => $amount,
            'fee'            => (float) $entry->fee,
            'net_amount'     => (float) $entry->net_amount,
            'status'         => $status,
            'status_label'   => $this->statusLabel($status),
            'reference'      => $entry->reference,
            'destination'    => null,
            'provider'       => null,
            'order_id'       => $entry->order_id,
            'withdrawal_id'  => null,
            'created_at'     => $createdAt?->toIso8601String(),
            'date'           => $createdAt?->format('d M Y'),
            'date_label'     => $createdAt?->format('Y-m-d'),
            'time'           => $createdAt?->format('H:i'),
        ];
    }

    /**
     * Normalisasi satu baris penarikan menjadi bentuk riwayat aplikasi.
     */
    private function normalizeWithdrawalEntry(Withdrawal $withdrawal): array
    {
        $status = $this->normalizeWithdrawalStatus((string) $withdrawal->status);

        $provider = strtoupper((string) ($withdrawal->provider ?? 'DANA'));
        $createdAt = $withdrawal->created_at;

        return [
            'id'             => 'wd-' . $withdrawal->id,
            'kind'           => 'withdrawal',
            'title'          => 'Penarikan ke ' . $provider,
            'description'    => $withdrawal->failure_reason,
            'amount'         => -(float) $withdrawal->amount,
            'fee'            => (float) $withdrawal->fee,
            'net_amount'     => (float) $withdrawal->net_amount,
            'status'         => $status,
            'status_label'   => $this->statusLabel($status),
            'reference'      => $withdrawal->provider_reference ?: $withdrawal->idempotency_key,
            'destination'    => $withdrawal->account_number
                ? WithdrawalService::maskPhone($withdrawal->account_number)
                : $withdrawal->danaConnection?->masked_phone,
            'account_name'   => $withdrawal->account_name,
            'provider'       => $provider,
            'order_id'       => null,
            'withdrawal_id'  => $withdrawal->id,
            'payout_id'      => null,
            'created_at'     => $createdAt?->toIso8601String(),
            'date'           => $createdAt?->format('d M Y'),
            'date_label'     => $createdAt?->format('Y-m-d'),
            'time'           => $createdAt?->format('H:i'),
            'processed_at'   => $withdrawal->processed_at?->toIso8601String(),
        ];
    }

    /**
     * Normalisasi transaksi payout/penarikan v1 (`payout_transactions`).
     */
    private function normalizePayoutEntry(PayoutTransaction $payout): array
    {
        $status = $this->normalizeWithdrawalStatus((string) $payout->status);
        $channelLabel = $this->formatPayoutChannelLabel($payout);
        $createdAt = $payout->created_at ?? $payout->scheduled_at;
        $processedAt = $payout->completed_at ?? $payout->failed_at;

        return [
            'id'             => 'po-' . $payout->id,
            'kind'           => 'withdrawal',
            'title'          => 'Penarikan ke ' . $channelLabel,
            'description'    => $payout->failure_reason,
            'amount'         => -(float) $payout->amount,
            'fee'            => (float) $payout->fee,
            'net_amount'     => (float) $payout->net_amount,
            'status'         => $status,
            'status_label'   => $this->statusLabel($status),
            'reference'      => $payout->provider_reference ?: $payout->reference,
            'destination'    => $payout->account_number_masked,
            'account_name'   => $payout->account_holder_name,
            'provider'       => $channelLabel,
            'order_id'       => null,
            'withdrawal_id'  => $payout->legacy_withdrawal_id,
            'payout_id'      => $payout->id,
            'created_at'     => $createdAt?->toIso8601String(),
            'date'           => $createdAt?->format('d M Y'),
            'date_label'     => $createdAt?->format('Y-m-d'),
            'time'           => $createdAt?->format('H:i'),
            'processed_at'   => $processedAt?->toIso8601String(),
        ];
    }

    private function normalizeWithdrawalStatus(string $rawStatus): string
    {
        return match (strtolower(trim($rawStatus))) {
            'success', 'completed'           => 'success',
            'processing', 'approved'         => 'processing',
            'failed', 'rejected', 'reversed' => 'failed',
            'cancelled'                      => 'cancelled',
            default                          => 'pending',
        };
    }

    private function formatPayoutChannelLabel(PayoutTransaction $payout): string
    {
        if ($payout->relationLoaded('destination') && ! empty($payout->destination?->channel_name)) {
            return (string) $payout->destination->channel_name;
        }

        $code = strtoupper(trim((string) $payout->channel_code));
        if ($code === '') {
            return strtoupper((string) ($payout->provider ?? 'PAYOUT'));
        }

        return match ($code) {
            'ID_BCA'       => 'Bank BCA',
            'ID_BRI'       => 'Bank BRI',
            'ID_BNI'       => 'Bank BNI',
            'ID_MANDIRI'   => 'Bank Mandiri',
            'ID_CIMB'      => 'CIMB Niaga',
            'ID_PERMATA'   => 'Bank Permata',
            'ID_BSI'       => 'BSI',
            'ID_DANA'      => 'DANA',
            'ID_OVO'       => 'OVO',
            'ID_GOPAY'     => 'GoPay',
            'ID_SHOPEEPAY' => 'ShopeePay',
            'ID_LINKAJA'   => 'LinkAja',
            default        => str_starts_with($code, 'ID_') ? substr($code, 3) : $code,
        };
    }

    /**
     * Judul & pesan notifikasi untuk satu transaksi payout v1.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function payoutNotification(PayoutTransaction $payout): array
    {
        $amount  = 'Rp' . number_format((float) $payout->amount, 0, ',', '.');
        $channel = $this->formatPayoutChannelLabel($payout);
        $dest    = $payout->account_number_masked ?: '-';

        return match ($this->normalizeWithdrawalStatus((string) $payout->status)) {
            'success'    => ['Penarikan berhasil', 'Penarikan ' . $amount . ' sudah dikirim ke ' . $channel . ' (' . $dest . ').', 'success'],
            'processing' => ['Penarikan sedang diproses', 'Penarikan ' . $amount . ' ke ' . $channel . ' (' . $dest . ') sedang diproses.', 'info'],
            'failed'     => ['Penarikan gagal', 'Penarikan ' . $amount . ' gagal: ' . ($payout->failure_reason ?: 'ditolak provider') . '. Saldo dikembalikan.', 'error'],
            'cancelled'  => ['Penarikan dibatalkan', 'Penarikan ' . $amount . ' dibatalkan. Saldo kembali ke dompet Anda.', 'warning'],
            default      => ['Penarikan diajukan', 'Permintaan penarikan ' . $amount . ' ke ' . $channel . ' (' . $dest . ') menunggu diproses.', 'info'],
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'success'    => 'Berhasil',
            'processing' => 'Diproses',
            'pending'    => 'Menunggu',
            'failed'     => 'Gagal',
            'cancelled'  => 'Dibatalkan',
            default      => 'Menunggu',
        };
    }

    /**
     * Judul & pesan notifikasi untuk satu penarikan.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function withdrawalNotification(Withdrawal $withdrawal): array
    {
        $amount   = 'Rp' . number_format((float) $withdrawal->amount, 0, ',', '.');
        $provider     = strtoupper((string) ($withdrawal->provider ?? 'DANA'));
        $successTitle = $provider === 'DANA' ? 'Dana berhasil dikirim' : 'Penarikan berhasil';

        return match ($withdrawal->status) {
            'success', 'completed' => [$successTitle, 'Penarikan ' . $amount . ' sudah dikirim ke akun ' . $provider . ' Anda.', 'success'],
            'processing'           => ['Penarikan sedang diproses', 'Penarikan ' . $amount . ' sedang diproses oleh ' . $provider . '.', 'info'],
            'failed', 'rejected'   => ['Penarikan gagal', 'Penarikan ' . $amount . ' gagal: ' . ($withdrawal->failure_reason ?: 'ditolak provider') . '. Saldo dikembalikan.', 'error'],
            'cancelled'            => ['Penarikan dibatalkan', 'Penarikan ' . $amount . ' dibatalkan. Saldo kembali ke dompet Anda.', 'warning'],
            default                => ['Penarikan diajukan', 'Permintaan penarikan ' . $amount . ' menunggu diproses.', 'info'],
        };
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
