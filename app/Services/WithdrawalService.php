<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Withdrawal Service Layer.
 * Handles withdrawal lifecycle: validate -> reserve -> disburse -> confirm/release.
 * Payout channel: iPaymu disbursement ke nomor HP DANA (tanpa binding akun).
 *
 * Prinsip keamanan dana:
 * - SISI PENAHANAN (reserve) dilakukan di transaksi DB FIRST dengan row lock
 *   (available_balance -> pending_balance).
 * - PEMANGGILAN HTTP ke iPaymu dilakukan SETELAH transaksi DB commit,
 *   tidak sambil memegang row lock.
 * - Saldo tidak pernah dipotong permanen sampai ada HASIL RESMI iPaymu
 *   (webhook terverifikasi / inquiry) atau jawaban sinkron yang tegas.
 * - Semua transisi status memakai idempotent guard (isTerminal) dan
 *   mencatat riwayat perubahan status pada kolom status_history.
 */
class WithdrawalService
{
    private iPaymuService $ipaymuService;
    private WalletService $walletService;

    private const MIN_WITHDRAWAL        = 10000;
    private const MAX_WITHDRAWAL        = 5000000;
    private const DAILY_LIMIT           = 5000000;
    private const ADMIN_FEE_DEFAULT     = 2500;
    private const RATE_LIMIT_REQUESTS   = 5;
    private const RATE_LIMIT_WINDOW_MIN = 10;

    public function __construct(
        ?iPaymuService $ipaymuService = null,
        ?WalletService $walletService = null
    ) {
        $this->ipaymuService   = $ipaymuService ?? new iPaymuService();
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
     * Create and process a withdrawal request (iPaymu DANA payout, no binding).
     *
     * fLifecycle:
     *  A. Transaksi DB: idempotency + validasi + buat baris penarikan + reservasi saldo.
     *  B. Luar transaksi DB: panggil iPaymu disbursement.
     *  C. Transaksi DB singkat: finalisasi status berdasarkan hasil iPaymu.
     *
     * @return array{success: bool, withdrawal: ?Withdrawal, error: ?string, idempotent?: bool}
     */
    public function create(int $courierId, float $amount, string $idempotencyKey, ?string $destinationPhone = null): array
    {
        // ── Fase A: reservasi atomik + idempotency (transaksi DB) ──
        [$result, $needsDisburse] = DB::transaction(function () use ($courierId, $amount, $idempotencyKey, $destinationPhone) {
            // 1. Idempotency: permintaan ulang dengan key sama mengembalikan transaksi lama.
            $existing = Withdrawal::where('courier_id', $courierId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [[
                    'success'    => true,
                    'withdrawal' => $existing,
                    'error'      => null,
                    'idempotent' => true,
                ], false];
            }

            // 2. Validasi lengkap (nominal, nomor, wallet, limit, rate limit, provider aktif).
            $validation = $this->validate($courierId, $amount, $destinationPhone);
            if (!$validation['valid']) {
                return [[
                    'success'    => false,
                    'withdrawal' => null,
                    'error'      => $validation['error'],
                ], false];
            }

            $wallet = $validation['wallet'];
            $feeConfig = $validation['fee_config'];
            $fee = (float) $feeConfig['admin_fee'];
            $netAmount = $amount - $fee;
            $provider = 'dana'; // payout iPaymu selalu dikirim ke DANA
            $normalizedPhone = $validation['phone'];

            // 3. Buat baris penarikan (status reserved = dana ditahan).
            $withdrawal = Withdrawal::create([
                'courier_id'         => $courierId,
                'wallet_id'          => $wallet->id,
                'dana_connection_id' => null, // tanpa binding: nomor HP diketik per transaksi
                'provider'           => $provider,
                'bank_name'          => strtoupper($provider),
                'account_number'     => $normalizedPhone,
                'account_name'       => 'Kurir',
                'amount'             => $amount,
                'fee'                => $fee,
                'net_amount'         => $netAmount,
                'status'             => 'reserved',
                'idempotency_key'    => $idempotencyKey,
                'status_history'     => $this->buildHistoryEntry('reserved', 'Permintaan penarikan dibuat, saldo ditahan.'),
            ]);

            // 4. Reservasi atomik (available -> pending, dengan baris lock).
            try {
                $holdDesc = 'Penarikan ' . strtoupper($provider) . ' - dana ditahan via iPaymu';
                $this->walletService->reserve($courierId, $amount, $idempotencyKey, $holdDesc);
            } catch (RuntimeException $e) {
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $e->getMessage()),
                ]);
                return [
                    ['success' => false, 'withdrawal' => $withdrawal, 'error' => $e->getMessage()],
                    false,
                ];
            }

            return [[
                'success'    => true,
                'withdrawal' => $withdrawal,
                'error'      => null,
                'validation' => $validation,
            ], true];
        });

        if (!$needsDisburse) {
            return $result;
        }

        // ── Fase B: payout ke iPaymu (JANGAN di dalam transaksi DB) ──
        if (iPaymuService::isPayoutActive()) {
            Log::info('[WithdrawalService] Sending payout to iPaymu', [
                'courier_id' => $courierId,
                'amount'     => $amount,
                'ref'        => $idempotencyKey,
            ]);

            $disbursementResult = $this->ipaymuService->disburse(
                $result['validation']['phone'],
                $amount,
                $idempotencyKey,
            );
        } else {
            // Payout belum diaktifkan (kredensial/aktivasi merchant iPaymu belum ada).
            // TIDAK ADA simulasi sukses: saldo tetap DITAHAN, status 'processing',
            // finalisasi akan ditentukan lewat verifikasi manual admin / webhook /
            // inquiry iPaymu setelah layanan aktif.
            $disbursementResult = [
                'success'        => true,
                'transaction_id' => null,
                'status'         => 'processing',
                'verdict'        => 'known',
                'error'          => null,
                'raw'            => null,
                'note'           => 'Payout iPaymu belum diaktifkan untuk merchant ini — '
                    . 'penarikan menunggu verifikasi final (admin/inquiry), saldo ditahan.',
            ];

            Log::warning('[WithdrawalService] Payout iPaymu inactive - withdrawal stays held pending verification', [
                'courier_id' => $courierId,
                'ref'        => $idempotencyKey,
            ]);
        }

        // ── Fase C: finalisasi status berdasarkan hasil resmi iPaymu ──
        $final = $this->applyDisbursementResult($courierId, $idempotencyKey, $disbursementResult);

        return [
            'success'    => true,
            'withdrawal' => $final ?? $result['withdrawal'],
            'error'      => null,
        ];
    }

    /**
     * Apply the sync disbursement result after Phase A reservation committed.
     * Called by the controller right after create() returns (Phase C).
     */
    public function applyDisbursementResult(int $courierId, string $idempotencyKey, array $disbursementResult): ?Withdrawal
    {
        $withdrawal = $this->getWithdrawalByKey($courierId, $idempotencyKey);
        if (!$withdrawal || $withdrawal->isTerminal()) {
            return $withdrawal;
        }

        $status = $disbursementResult['status'] ?? 'unknown';
        $verdict = $disbursementResult['verdict'] ?? 'unknown';
        $txId = $disbursementResult['transaction_id'] ?? null;
        $fee = (float) $withdrawal->fee;
        $provider = strtolower((string) ($withdrawal->provider ?? 'dana'));

        return DB::transaction(function () use ($withdrawal, $status, $verdict, $txId, $courierId, $fee, $idempotencyKey, $provider) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->first();

            if ($withdrawal->isTerminal()) {
                return $withdrawal->fresh(); // webhook bisa saja sudah lebih dulu
            }

            $amount = (float) $withdrawal->amount;

            if ($status === 'success' && !empty($txId)) {
                // Ada HASIL RESMI iPaymu (sync success + transaction id).
                $withdrawal->update([
                    'status'             => 'success',
                    'provider_reference' => $txId,
                    'processed_at'       => now(),
                    'status_history'     => $this->appendHistory($withdrawal->status_history, 'success', 'Dana terkirim via iPaymu (konfirmasi sinkron).'),
                ]);

                $this->walletService->confirmReservation($courierId, $amount, $idempotencyKey);
                $this->ledgerWithdrawalCompleted($withdrawal, 'Penarikan ke DANA via iPaymu');

                Log::info('[WithdrawalService] Withdrawal succeeded via iPaymu (sync)', [
                    'courier_id' => $courierId, 'amount' => $amount, 'ref' => $idempotencyKey, 'tx' => $txId,
                ]);
            } elseif ($status === 'success' || $status === 'processing' || $status === 'pending') {
                // Tanpa transaction id / masih diproses: TAHAN saldo, tunggu
                // webhook atau inquiry resmi. JANGAN tandai success.
                $holdNote = $disbursementResult['note'] ?? 'Payout sedang diproses iPaymu, menunggu konfirmasi.';
                $withdrawal->update([
                    'status'             => 'processing',
                    'provider_reference' => $withdrawal->provider_reference ?? ($txId ?: null),
                    'status_history'     => $this->appendHistory($withdrawal->status_history, 'processing', $holdNote),
                ]);

                Log::info('[WithdrawalService] Withdrawal queued/processing via iPaymu', [
                    'courier_id' => $courierId, 'amount' => $amount, 'ref' => $idempotencyKey,
                ]);
            } elseif ($status === 'failed') {
                $failureReason = $disbursementResult['error'] ?? 'Payout ditolak iPaymu.';
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $failureReason,
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $failureReason),
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $idempotencyKey);

                Log::warning('[WithdrawalService] Withdrawal disbursement failed via iPaymu', [
                    'courier_id' => $courierId, 'error' => $failureReason, 'ref' => $idempotencyKey,
                ]);
            } else {
                // status 'unknown' / 'not_configured': hasil tidak pasti.
                // Saldo TETAP ditahan (reserved), tunggu inquiry/webhook.
                // Jangan rilis saldo dan jangan kirim payout ulang.
                if ($status === 'not_configured') {
                    $reason = $disbursementResult['error'] ?? 'Payout provider tidak tersedia.';
                    // Fail-safe: kalau iPaymu belum diaktifkan, tidak ada kemungkinan
                    // uang keluar — aman untuk melepas reservasi.
                    $withdrawal->update([
                        'status'         => 'failed',
                        'failure_reason' => $reason,
                        'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $reason),
                    ]);
                    $this->walletService->releaseReservation($courierId, $amount, $idempotencyKey);
                }
                Log::warning('[WithdrawalService] Disbursement verdict unknown', [
                    'courier_id' => $courierId, 'ref' => $idempotencyKey, 'status' => $status,
                    'verdict' => $verdict, 'fee' => $fee, 'provider' => $provider,
                ]);
            }

            return $withdrawal->fresh();
        });
    }

    // ─────────────────────────── VALIDATION ───────────────────────────

    /**
     * Validate a withdrawal request (nominal, wallet, limits, phone).
     * On validity, returns wallet so Phase A never writes wallet_id=0.
     */
    private function validate(int $courierId, float $amount, ?string $destinationPhone = null): array
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

        if (($destinationPhone === null || trim($destinationPhone) === '')) {
            $destinationPhone = $this->resolveDefaultPhone($courierId);
        }

        $normalized = $this->normalizePhoneNumber($destinationPhone);
        if ($normalized === null) {
            return ['valid' => false, 'error' => 'Nomor HP DANA tujuan wajib diisi dengan format 08xxxxxxxxxx / 628xxxxxxxxxx.'];
        }

        // Wallet: buat jika belum ada; aktivasi otomatis untuk alur tanpa-binding.
        // Status 'suspended' tetap diblokir — hanya kurir yang disuspend.
        $wallet = $this->walletService->getOrCreateWallet($courierId);
        if ($wallet->status === 'not_active') {
            $wallet->update(['status' => 'active']);
            $wallet->refresh();
            Log::info('[WithdrawalService] Wallet auto-activated for iPaymu no-binding flow', ['courier_id' => $courierId]);
        }

        if ($wallet->status !== 'active') {
            return ['valid' => false, 'error' => 'Wallet tidak aktif.'];
        }

        if ((float) $wallet->available_balance < $amount) {
            return ['valid' => false, 'error' => 'Saldo tidak mencukupi.'];
        }

        // Cegah double submission: hanya satu penarikan berproses per kurir.
        $processing = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($processing > 0) {
            return ['valid' => false, 'error' => 'Penarikan sedang diproses. Mohon tunggu.'];
        }

        // Batas harian.
        $todayStart = now()->startOfDay();
        $todayTotal = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['success', 'completed', 'processing', 'reserved'])
            ->where('created_at', '>=', $todayStart)
            ->sum('amount');

        if ($todayTotal + $amount > $feeConfig['daily_limit']) {
            return ['valid' => false, 'error' => 'Batas penarikan harian tercapai.'];
        }

        // Rate limit per kurir (mencegah percobaan penarikan beruntun).
        $rateWindow = now()->subMinutes((int) config('withdrawals.rate_limit_window_minutes', self::RATE_LIMIT_WINDOW_MIN));
        $rateMax = (int) config('withdrawals.rate_limit_requests', self::RATE_LIMIT_REQUESTS);
        $recentCount = Withdrawal::where('courier_id', $courierId)
            ->where('created_at', '>=', $rateWindow)
            ->count();

        if ($recentCount >= $rateMax) {
            return ['valid' => false, 'error' => 'Terlalu banyak percobaan penarikan. Tunggu beberapa menit lagi.'];
        }

        return [
            'valid'      => true,
            'wallet'     => $wallet,
            'phone'      => $normalized,
            'fee_config' => $feeConfig,
            'error'      => null,
        ];
    }

    /**
     * Fallback nomor HP tujuan ketika request tidak mengirim kameranya sendiri:
     * 1. phone_encrypted dari akun DANA terhubung (dekripsi).
     * 2. Nomor HP kurir terdaftar (user/courier).
     * Nomor ini tetap ditampilkan (disamarkan) sebelum penarikan diproses.
     */
    private function resolveDefaultPhone(int $courierId): ?string
    {
        $connection = \App\Models\DanaConnection::where('courier_id', $courierId)
            ->where('status', 'connected')
            ->first();

        if ($connection && !empty($connection->phone)) {
            return $connection->phone;
        }

        $courier = \App\Models\Courier::with('user')->find($courierId);

        if (!$courier) {
            return null;
        }

        if (!empty($courier->phone)) {
            return $courier->phone;
        }

        return $courier->user?->phone;
    }

    /**
     * Normalize Indonesian phone number for DANA payout.
     * Accepts: 08xxxxxxxxxx | 628xxxxxxxxxx | 8xxxxxxxxxx.
     * Returns: normalized 628********** format or null if invalid.
     */
    public function normalizePhoneNumber(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        // Strip leading country code "62" then expect "8" + 9..13 digit subscriber.
        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            // already bare subscriber
        } else {
            return null;
        }

        if (!str_starts_with($digits, '8')) {
            return null;
        }

        $len = strlen($digits);
        if ($len < 9 || $len > 13) {
            return null;
        }

        return '62' . $digits;
    }

    /** Mask an Indonesian phone. Already-masked inputs pass through unchanged. */
    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '-';
        }

        // Nilai yang sudah disamarkan (legacy) tidak di-mask ulang.
        if (str_contains($phone, '****')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return '-';
        }

        // Akhiran subscriber dipisah dari prefix tampilan agar output
        // konsisten: 62812… → 6281****7890, 0812… → 0812****7890 (ala
        // nilai legacy).
        if (str_starts_with($digits, '62')) {
            $cc = '62';
            $subscriber = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $cc = '0';
            $subscriber = substr($digits, 1);
        } else {
            $cc = '';
            $subscriber = $digits;
        }

        if (strlen($subscriber) <= 4) {
            return $phone;
        }

        // Total digit yang ditampilkan sebelum '****' = 4 (mengikuti format
        // legacy "0812****7890"): 62 → 2 digit, 0 → 3 digit, tanpa prefix → 4.
        $keep = 4 - strlen($cc);

        return $cc . substr($subscriber, 0, $keep) . '****' . substr($subscriber, -4);
    }

    // ─────────────────────────── QUERIES ───────────────────────────

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

    // ─────────────────────────── STATUS SYNC ───────────────────────────

    /**
     * Handle status update from webhook callback or status polling.
     * IDEMPOTENT: terminal withdraw status tidak berubah walau callback datang lagi.
     */
    public function handleStatusUpdate(string $providerReference, string $newStatus, ?string $failureReason = null): ?Withdrawal
    {
        if (!in_array($newStatus, ['success', 'failed'], true)) {
            // Bukan status final: tidak ada yang diubah.
            return Withdrawal::where('provider_reference', $providerReference)->first()
                ?? Withdrawal::where('idempotency_key', $providerReference)->first();
        }

        return DB::transaction(function () use ($providerReference, $newStatus, $failureReason) {
            // Lock baris + guard bersamaan: callback dobel TIDAK diproses dua kali.
            $withdrawal = Withdrawal::where(function ($q) use ($providerReference) {
                    $q->where('provider_reference', $providerReference)
                      ->orWhere('idempotency_key', $providerReference);
                })
                ->lockForUpdate()
                ->first();

            if (!$withdrawal) {
                Log::warning('[WithdrawalService] Status update for unknown transaction', [
                    'provider_reference' => $providerReference,
                ]);
                return null;
            }

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
            $provider  = strtoupper((string) ($withdrawal->provider ?? 'dana'));

            if ($newStatus === 'success') {
                $withdrawal->update([
                    'status'       => 'success',
                    'processed_at' => $withdrawal->processed_at ?? now(),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'success', 'Konfirmasi resmi iPaymu (final).'),
                ]);

                $this->walletService->confirmReservation($courierId, $amount, $reference);
                $this->ledgerWithdrawalCompleted($withdrawal, 'Penarikan ke ' . $provider . ' via iPaymu');

                Log::info('[WithdrawalService] Status updated to success', ['withdrawal_id' => $withdrawal->id]);
            } else {
                $withdrawal->update([
                    'status'         => 'failed',
                    'failure_reason' => $failureReason ?? 'Transaksi ditolak provider.',
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $failureReason ?? 'Transaksi ditolak provider.'),
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $reference);

                Log::info('[WithdrawalService] Status updated to failed', ['withdrawal_id' => $withdrawal->id]);
            }

            return $withdrawal->fresh();
        });
    }

    /**
     * Inquire status resmi dari iPaymu dan sinkronkan status penarikan.
     */
    public function checkAndSyncStatus(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->isTerminal()) {
            return $withdrawal;
        }

        $ref = $withdrawal->idempotency_key;
        $txId = $withdrawal->provider_reference;

        $res = $this->ipaymuService->inquire($txId ?: $ref);

        $status = $res['status'] ?? 'unknown';
        if ($status === 'success' || $status === 'failed') {
            $updated = $this->handleStatusUpdate($txId ?: $ref, $status, $res['error'] ?? null);
            if ($updated) {
                return $updated;
            }
        }

        return $withdrawal->fresh();
    }

    // ─────────────────────────── LEDGER ───────────────────────────

    private function ledgerWithdrawalCompleted(Withdrawal $withdrawal, string $description): void
    {
        \App\Models\WalletTransaction::create([
            'wallet_id'   => $withdrawal->wallet_id,
            'courier_id'  => $withdrawal->courier_id,
            'order_id'    => null,
            'type'        => 'withdrawal',
            'amount'      => -(float) $withdrawal->amount,
            'fee'         => (float) $withdrawal->fee,
            'net_amount'  => -(float) ($withdrawal->amount - $withdrawal->fee),
            'status'      => 'completed',
            'reference'   => $withdrawal->idempotency_key,
            'description' => $description,
        ]);
    }

    // ─────────────────────────── STATUS HISTORY ───────────────────────────

    private function buildHistoryEntry(string $status, ?string $note = null, ?string $source = 'system'): array
    {
        return [[
            'status'    => $status,
            'note'      => $note,
            'source'    => $source,
            'at'        => now()->toIso8601String(),
        ]];
    }

    private function appendHistory($existing, string $status, ?string $note = null, ?string $source = 'system'): array
    {
        $history = is_array($existing) ? $existing : json_decode((string) $existing, true);
        if (!is_array($history)) {
            $history = [];
        }
        return array_values(array_merge($history, $this->buildHistoryEntry($status, $note, $source)));
    }

    /** API helper yang bisa dipakai controller webhook. */
    public function pushHistory(Withdrawal $withdrawal, string $status, ?string $note, ?string $source = 'system'): void
    {
        $withdrawal->status_history = $this->appendHistory(
            $withdrawal->status_history,
            $status,
            $note,
            $source,
        );
        $withdrawal->update(['status_history' => $withdrawal->status_history]);
    }
}
