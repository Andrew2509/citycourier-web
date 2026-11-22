<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\DanaConnection;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Services\XenditPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Legacy-compatible Courier Withdrawal Service Layer (/api/courier/withdrawals).
 *
 * Seluruh pencairan dana aktif disalurkan melalui Xendit Payouts v2 (bank & e-wallet).
 * DOKU & iPaymu tidak lagi digunakan; histori transaksi lama pada tabel `withdrawals`
 * tetap dipertahankan dan dapat dibaca secara aman.
 */
class WithdrawalService
{
    private WalletService $walletService;
    private ProviderResolver $providerResolver;
    private XenditPayoutService $xenditPayoutService;

    private const MIN_WITHDRAWAL = 10000;
    private const MAX_WITHDRAWAL = 5000000;
    private const DAILY_LIMIT = 5000000;
    private const ADMIN_FEE_DEFAULT = 2500;
    private const RATE_LIMIT_REQUESTS = 5;
    private const RATE_LIMIT_WINDOW_MIN = 10;

    public function __construct(
        ?WalletService $walletService = null,
        ?ProviderResolver $providerResolver = null,
        ?XenditPayoutService $xenditPayoutService = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
        $this->providerResolver = $providerResolver ?? app(ProviderResolver::class);
        $this->xenditPayoutService = $xenditPayoutService ?? app(XenditPayoutService::class);
    }

    public function getFeeConfig(): array
    {
        return [
            'admin_fee' => config('withdrawals.admin_fee', self::ADMIN_FEE_DEFAULT),
            'minimum' => config('withdrawals.minimum', self::MIN_WITHDRAWAL),
            'maximum' => config('withdrawals.maximum', self::MAX_WITHDRAWAL),
            'daily_limit' => config('withdrawals.daily_limit', self::DAILY_LIMIT),
        ];
    }

    /**
     * Create and process a withdrawal request via Xendit Payouts (bank atau e-wallet).
     *
     * @return array{success: bool, withdrawal: ?Withdrawal, error: ?string, error_code?: ?string, idempotent?: bool}
     */
    public function create(
        int $courierId,
        float $amount,
        string $idempotencyKey,
        ?string $destinationAccount = null,
        ?string $channelCode = null,
        ?string $accountHolderName = null,
        ?string $destinationType = null
    ): array {
        // Fase A: validasi + idempotency + reservasi saldo atomik
        [$result, $needsDisburse] = DB::transaction(function () use (
            $courierId,
            $amount,
            $idempotencyKey,
            $destinationAccount,
            $channelCode,
            $accountHolderName,
            $destinationType
        ) {
            $existing = Withdrawal::where('courier_id', $courierId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return [[
                    'success' => true,
                    'withdrawal' => $existing,
                    'error' => null,
                    'idempotent' => true,
                ], false];
            }

            $validation = $this->validate(
                $courierId,
                $amount,
                $destinationAccount,
                $channelCode,
                $destinationType
            );
            if (! $validation['valid']) {
                return [[
                    'success' => false,
                    'withdrawal' => null,
                    'error' => $validation['error'],
                    'error_code' => $validation['error_code'] ?? null,
                ], false];
            }

            $wallet = $validation['wallet'];
            $feeConfig = $validation['fee_config'];
            $fee = (float) $feeConfig['admin_fee'];
            $netAmount = $amount - $fee;
            $resolvedChannel = $validation['channel_code'];
            $resolvedAccount = $validation['account_number'];
            $resolvedHolder = trim((string) $accountHolderName) ?: $this->resolveHolderFallback($courierId);

            $withdrawal = Withdrawal::create([
                'courier_id' => $courierId,
                'wallet_id' => $wallet->id,
                'dana_connection_id' => null,
                'provider' => 'xendit',
                'bank_name' => $resolvedChannel,
                'account_number' => $resolvedAccount,
                'account_name' => $resolvedHolder,
                'amount' => $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'status' => 'reserved',
                'idempotency_key' => $idempotencyKey,
                'status_history' => $this->buildHistoryEntry('reserved', 'Permintaan penarikan dibuat, saldo ditahan untuk payout Xendit.'),
            ]);

            try {
                $holdDesc = "Penarikan {$resolvedChannel} via Xendit - dana ditahan";
                $this->walletService->reserve($courierId, $amount, $idempotencyKey, $holdDesc);
            } catch (RuntimeException $e) {
                $withdrawal->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $e->getMessage()),
                ]);

                return [
                    ['success' => false, 'withdrawal' => $withdrawal, 'error' => $e->getMessage()],
                    false,
                ];
            }

            return [[
                'success' => true,
                'withdrawal' => $withdrawal,
                'error' => null,
                'validation' => $validation,
            ], true];
        });

        if (! $needsDisburse) {
            return $result;
        }

        // Fase B: panggil Xendit Payouts (atau Mock saat demo / sandbox tanpa secret key) di luar lock DB
        /** @var Withdrawal $withdrawalModel */
        $withdrawalModel = $result['withdrawal'];

        try {
            $payoutProvider = $this->providerResolver->resolvePayoutProvider('xendit');
            $payoutRes = $payoutProvider->createPayout([
                'reference' => $idempotencyKey,
                'idempotency_key' => $idempotencyKey,
                'channel_code' => $withdrawalModel->bank_name ?: 'ID_DANA',
                'destination_type' => $result['validation']['destination_type'],
                'account_number' => $withdrawalModel->account_number,
                'account_holder_name' => $withdrawalModel->account_name ?: 'Kurir City Courier',
                'amount' => (int) round((float) $withdrawalModel->net_amount),
                'description' => "City Courier Courier Withdrawal {$idempotencyKey}",
            ]);

            $rawStatus = strtolower((string) ($payoutRes['status'] ?? 'processing'));
            $isSimulatedMock = $payoutProvider->providerCode() === 'mock' || ! empty($payoutRes['is_simulated']);
            $mappedStatus = match (true) {
                in_array($rawStatus, ['failed', 'reversed'], true) => 'failed',
                $rawStatus === 'completed' && ! $isSimulatedMock => 'success',
                default => 'processing',
            };

            $disbursementResult = [
                'status' => $mappedStatus,
                'transaction_id' => $payoutRes['provider_reference'] ?? null,
                'error' => $payoutRes['failure_reason'] ?? null,
                'note' => 'Payout dikirim ke '.$payoutProvider->providerCode().'.',
            ];
        } catch (\Throwable $e) {
            $isTimeout = str_contains(strtolower($e->getMessage()), 'timed out')
                || str_contains(strtolower($e->getMessage()), 'timeout')
                || str_contains(strtolower($e->getMessage()), 'curl error 28');

            if ($isTimeout) {
                $disbursementResult = [
                    'status' => 'processing',
                    'transaction_id' => null,
                    'error' => null,
                    'note' => 'Timeout menghubungi Xendit — saldo tetap ditahan sampai verifikasi status selesai.',
                ];
            } else {
                $disbursementResult = [
                    'status' => 'failed',
                    'transaction_id' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Fase C: finalisasi atau tahan / kembalikan saldo jika gagal
        $final = $this->applyDisbursementResult($courierId, $idempotencyKey, $disbursementResult);

        return [
            'success' => true,
            'withdrawal' => $final ?? $withdrawalModel,
            'error' => null,
        ];
    }

    public function applyDisbursementResult(int $courierId, string $idempotencyKey, array $disbursementResult): ?Withdrawal
    {
        $withdrawal = $this->getWithdrawalByKey($courierId, $idempotencyKey);
        if (! $withdrawal || $withdrawal->isTerminal()) {
            return $withdrawal;
        }

        $status = $disbursementResult['status'] ?? 'unknown';
        $txId = $disbursementResult['transaction_id'] ?? null;
        $channel = strtoupper((string) ($withdrawal->bank_name ?? 'ID_DANA'));

        return DB::transaction(function () use ($withdrawal, $status, $disbursementResult, $txId, $courierId, $idempotencyKey, $channel) {
            $withdrawal = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->first();

            if (! $withdrawal || $withdrawal->isTerminal()) {
                return $withdrawal?->fresh();
            }

            $amount = (float) $withdrawal->amount;

            if ($status === 'success' && ! empty($txId)) {
                $withdrawal->update([
                    'status' => 'success',
                    'provider_reference' => $txId,
                    'processed_at' => now(),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'success', 'Dana terkirim melalui Xendit.'),
                ]);

                $this->walletService->confirmReservation($courierId, $amount, $idempotencyKey);
                $this->ledgerWithdrawalCompleted($withdrawal, "Penarikan ke {$channel} via Xendit");
            } elseif (in_array($status, ['success', 'processing', 'pending'], true)) {
                $holdNote = $disbursementResult['note'] ?? 'Payout sedang diproses Xendit, menunggu konfirmasi webhook.';
                $withdrawal->update([
                    'status' => 'processing',
                    'provider_reference' => $withdrawal->provider_reference ?? ($txId ?: null),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'processing', $holdNote),
                ]);
            } elseif ($status === 'failed') {
                $failureReason = $disbursementResult['error'] ?? 'Payout ditolak Xendit.';
                $withdrawal->update([
                    'status' => 'failed',
                    'failure_reason' => $failureReason,
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $failureReason),
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $idempotencyKey);
            }

            return $withdrawal->fresh();
        });
    }

    private function validate(
        int $courierId,
        float $amount,
        ?string $destinationAccount = null,
        ?string $channelCode = null,
        ?string $destinationType = null
    ): array {
        if ($this->providerResolver->isPayoutProviderDisabled('xendit')) {
            return [
                'valid' => false,
                'error' => 'Layanan pencairan Xendit sedang dinonaktifkan oleh Admin.',
                'error_code' => 'PROVIDER_DISABLED',
            ];
        }

        $feeConfig = $this->getFeeConfig();

        if ($amount <= 0) {
            return ['valid' => false, 'error' => 'Jumlah penarikan harus lebih dari 0.'];
        }
        if ($amount < $feeConfig['minimum']) {
            return ['valid' => false, 'error' => 'Minimum penarikan Rp'.number_format($feeConfig['minimum'], 0, ',', '.').'.'];
        }
        if ($amount > $feeConfig['maximum']) {
            return ['valid' => false, 'error' => 'Maksimum penarikan Rp'.number_format($feeConfig['maximum'], 0, ',', '.').'.'];
        }

        $normalizedChannel = $this->xenditPayoutService->normalizeChannelCode($channelCode ?: 'ID_DANA');
        $resolvedType = $destinationType ? strtolower(trim($destinationType)) : (
            array_key_exists($normalizedChannel, XenditPayoutService::EWALLET_CHANNELS) ? 'ewallet' : 'bank'
        );

        if (! $this->xenditPayoutService->isChannelSupported($resolvedType, $normalizedChannel)) {
            return [
                'valid' => false,
                'error' => "Kanal tujuan {$normalizedChannel} tidak didukung untuk payout Xendit.",
                'error_code' => 'UNSUPPORTED_CHANNEL',
            ];
        }

        if ($destinationAccount === null || trim($destinationAccount) === '') {
            $destinationAccount = $this->resolveDefaultPhone($courierId);
        }

        if ($resolvedType === 'ewallet') {
            $normalizedAccount = $this->normalizePhoneNumber($destinationAccount);
            if ($normalizedAccount === null) {
                return ['valid' => false, 'error' => 'Nomor HP e-wallet wajib valid (format 08xxxxxxxxxx / 628xxxxxxxxxx).'];
            }
        } else {
            $digits = preg_replace('/\D+/', '', (string) $destinationAccount) ?? '';
            if (strlen($digits) < 6) {
                return ['valid' => false, 'error' => 'Nomor rekening bank minimal 6 digit angka.'];
            }
            $normalizedAccount = $digits;
        }

        $wallet = $this->walletService->getOrCreateWallet($courierId);
        if ($wallet->status === 'not_active') {
            $wallet->update(['status' => 'active']);
            $wallet->refresh();
        }

        if ($wallet->status !== 'active') {
            return ['valid' => false, 'error' => 'Wallet tidak aktif.'];
        }

        if ((float) $wallet->available_balance < $amount) {
            return ['valid' => false, 'error' => 'Saldo tidak mencukupi.'];
        }

        $processing = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['reserved', 'processing'])
            ->count();

        if ($processing > 0) {
            return ['valid' => false, 'error' => 'Penarikan sedang diproses. Mohon tunggu.'];
        }

        $todayStart = now()->startOfDay();
        $todayTotal = Withdrawal::where('courier_id', $courierId)
            ->whereIn('status', ['success', 'completed', 'processing', 'reserved'])
            ->where('created_at', '>=', $todayStart)
            ->sum('amount');

        if ($todayTotal + $amount > $feeConfig['daily_limit']) {
            return ['valid' => false, 'error' => 'Batas penarikan harian tercapai.'];
        }

        $rateWindow = now()->subMinutes((int) config('withdrawals.rate_limit_window_minutes', self::RATE_LIMIT_WINDOW_MIN));
        $rateMax = (int) config('withdrawals.rate_limit_requests', self::RATE_LIMIT_REQUESTS);
        $recentCount = Withdrawal::where('courier_id', $courierId)
            ->where('created_at', '>=', $rateWindow)
            ->count();

        if ($recentCount >= $rateMax) {
            return ['valid' => false, 'error' => 'Terlalu banyak percobaan penarikan. Tunggu beberapa menit lagi.'];
        }

        return [
            'valid' => true,
            'wallet' => $wallet,
            'phone' => $normalizedAccount,
            'account_number' => $normalizedAccount,
            'channel_code' => $normalizedChannel,
            'destination_type' => $resolvedType,
            'fee_config' => $feeConfig,
            'error' => null,
        ];
    }

    private function resolveHolderFallback(int $courierId): string
    {
        $courier = Courier::with('user')->find($courierId);

        return trim((string) ($courier?->user?->name ?? 'Kurir City Courier'));
    }

    private function resolveDefaultPhone(int $courierId): ?string
    {
        $connection = DanaConnection::where('courier_id', $courierId)
            ->where('status', 'connected')
            ->first();

        if ($connection && ! empty($connection->phone)) {
            return $connection->phone;
        }

        $courier = Courier::with('user')->find($courierId);
        if (! $courier) {
            return null;
        }

        return ! empty($courier->phone) ? $courier->phone : $courier->user?->phone;
    }

    public function normalizePhoneNumber(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (! str_starts_with($digits, '8')) {
            return null;
        }

        if (! str_starts_with($digits, '8')) {
            return null;
        }

        $len = strlen($digits);
        if ($len < 9 || $len > 13) {
            return null;
        }

        return '0'.$digits;
    }

    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '-';
        }
        if (str_contains($phone, '****')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '') {
            return '-';
        }

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

        $keep = max(2, 4 - strlen($cc));

        return $cc.substr($subscriber, 0, $keep).'****'.substr($subscriber, -4);
    }

    public function getWithdrawals(int $courierId, int $page = 1, int $perPage = 15): array
    {
        $withdrawals = Withdrawal::where('courier_id', $courierId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'withdrawals' => $withdrawals->items(),
            'total' => $withdrawals->total(),
            'page' => $page,
            'per_page' => $perPage,
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

    public function handleStatusUpdate(string $providerReference, string $newStatus, ?string $failureReason = null): ?Withdrawal
    {
        if (! in_array($newStatus, ['success', 'failed'], true)) {
            return Withdrawal::where('provider_reference', $providerReference)->first()
                ?? Withdrawal::where('idempotency_key', $providerReference)->first();
        }

        return DB::transaction(function () use ($providerReference, $newStatus, $failureReason) {
            $withdrawal = Withdrawal::where(function ($q) use ($providerReference) {
                $q->where('provider_reference', $providerReference)
                    ->orWhere('idempotency_key', $providerReference);
            })
                ->lockForUpdate()
                ->first();

            if (! $withdrawal) {
                return null;
            }

            if ($withdrawal->isTerminal()) {
                return $withdrawal;
            }

            $courierId = $withdrawal->courier_id;
            $amount = (float) $withdrawal->amount;
            $reference = $withdrawal->idempotency_key;
            $channel = strtoupper((string) ($withdrawal->bank_name ?? 'XENDIT'));

            if ($newStatus === 'success') {
                $withdrawal->update([
                    'status' => 'success',
                    'processed_at' => $withdrawal->processed_at ?? now(),
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'success', 'Konfirmasi resmi Xendit Payouts (final).'),
                ]);

                $this->walletService->confirmReservation($courierId, $amount, $reference);
                $this->ledgerWithdrawalCompleted($withdrawal, "Penarikan ke {$channel} via Xendit");
            } else {
                $withdrawal->update([
                    'status' => 'failed',
                    'failure_reason' => $failureReason ?? 'Transaksi ditolak Xendit.',
                    'status_history' => $this->appendHistory($withdrawal->status_history, 'failed', $failureReason ?? 'Transaksi ditolak Xendit.'),
                ]);

                $this->walletService->releaseReservation($courierId, $amount, $reference);
            }

            return $withdrawal->fresh();
        });
    }

    public function checkAndSyncStatus(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->isTerminal()) {
            return $withdrawal;
        }

        $ref = $withdrawal->idempotency_key;
        $txId = (string) ($withdrawal->provider_reference ?? '');

        if ($withdrawal->provider === 'xendit' && $this->xenditPayoutService->isConfigured()) {
            try {
                $res = $this->xenditPayoutService->getPayoutStatus($txId, $ref);
                $status = match (strtolower((string) ($res['status'] ?? 'unknown'))) {
                    'completed' => 'success',
                    'failed', 'reversed' => 'failed',
                    default => 'unknown',
                };
                if ($status === 'success' || $status === 'failed') {
                    $updated = $this->handleStatusUpdate($txId ?: $ref, $status, $res['failure_reason'] ?? null);
                    if ($updated) {
                        return $updated;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[WithdrawalService] Sync status Xendit gagal', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $withdrawal->fresh();
    }

    private function ledgerWithdrawalCompleted(Withdrawal $withdrawal, string $description): void
    {
        WalletTransaction::create([
            'wallet_id' => $withdrawal->wallet_id,
            'courier_id' => $withdrawal->courier_id,
            'order_id' => null,
            'type' => 'withdrawal',
            'amount' => -(float) $withdrawal->amount,
            'fee' => (float) $withdrawal->fee,
            'net_amount' => -(float) ($withdrawal->amount - $withdrawal->fee),
            'status' => 'completed',
            'reference' => $withdrawal->idempotency_key,
            'description' => $description,
        ]);
    }

    private function buildHistoryEntry(string $status, ?string $note = null, ?string $source = 'system'): array
    {
        return [[
            'status' => $status,
            'note' => $note,
            'source' => $source,
            'at' => now()->toIso8601String(),
        ]];
    }

    private function appendHistory($existing, string $status, ?string $note = null, ?string $source = 'system'): array
    {
        $history = is_array($existing) ? $existing : json_decode((string) $existing, true);
        if (! is_array($history)) {
            $history = [];
        }

        return array_values(array_merge($history, $this->buildHistoryEntry($status, $note, $source)));
    }

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
