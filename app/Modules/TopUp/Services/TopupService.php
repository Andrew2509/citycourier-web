<?php

namespace App\Modules\TopUp\Services;

use App\Models\User;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class TopupService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function getAvailableMethods(): array
    {
        $mode = $this->providerResolver->currentMode();
        $provider = $this->providerResolver->resolvePaymentProvider();

        return [
            'mode' => $mode,
            'active_provider' => $provider->providerCode(),
            'is_simulated' => $provider->providerCode() === 'mock',
            'min_amount_idr' => (int) config('payment_wallet.topup.min_amount_idr', 10000),
            'max_amount_idr' => (int) config('payment_wallet.topup.max_amount_idr', 10000000),
            'methods' => $provider->availableChannels($mode),
        ];
    }

    public function createTopup(
        User $user,
        int $amount,
        string $paymentType,
        string $channelCode,
        string $idempotencyKey
    ): array {
        $min = (int) config('payment_wallet.topup.min_amount_idr', 10000);
        $max = (int) config('payment_wallet.topup.max_amount_idr', 10000000);

        if ($amount < $min || $amount > $max) {
            throw new InvalidArgumentException(
                "Nominal top-up harus antara Rp ".number_format($min, 0, ',', '.')." dan Rp ".number_format($max, 0, ',', '.').'.'
            );
        }

        $existing = TopupTransaction::where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return ['topup' => $existing, 'idempotent_replay' => true];
        }

        $wallet = $this->walletManager->getOrCreateCustomerWallet($user);
        $this->walletManager->assertNotFrozen($wallet);

        $mode = $this->providerResolver->currentMode();
        $provider = $this->providerResolver->resolvePaymentProvider();
        $expiryMinutes = (int) config('payment_wallet.topup.expiry_minutes', 60);
        $expiredAt = now()->addMinutes($expiryMinutes);
        $reference = 'TOPUP-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));

        $providerResponse = $provider->createTopupPayment([
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'payment_type' => $paymentType,
            'channel_code' => $channelCode,
            'amount' => $amount,
            'expired_at' => $expiredAt,
            'description' => "Top-Up Wallet City Courier ({$reference})",
        ]);

        $topup = TopupTransaction::create([
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'provider' => $providerResponse['provider'],
            'mode' => $providerResponse['mode'] ?: $mode,
            'is_simulated' => (bool) ($providerResponse['is_simulated'] ?? false),
            'payment_type' => strtolower($paymentType),
            'channel_code' => strtoupper($channelCode),
            'amount' => $amount,
            'fee' => 0,
            'net_amount' => $amount,
            'status' => $providerResponse['status'] ?? 'pending',
            'provider_reference' => $providerResponse['provider_reference'] ?? null,
            'va_number' => $providerResponse['va_number'] ?? null,
            'qr_string' => $providerResponse['qr_string'] ?? null,
            'payment_url' => $providerResponse['payment_url'] ?? null,
            'instructions' => $providerResponse['instructions'] ?? [],
            'provider_payload' => $providerResponse['raw_payload'] ?? [],
            'expired_at' => $providerResponse['expired_at'] ?? $expiredAt,
        ]);

        $this->walletManager->writeAuditLog(
            $user->id,
            'customer',
            $wallet->id,
            'topup_created',
            TopupTransaction::class,
            $topup->id,
            null,
            ['reference' => $topup->reference, 'amount' => $amount, 'provider' => $topup->provider, 'mode' => $topup->mode]
        );

        return ['topup' => $topup, 'idempotent_replay' => false];
    }

    public function refreshAndSyncStatus(TopupTransaction $topup): TopupTransaction
    {
        if (in_array($topup->status, ['settled', 'paid', 'failed', 'expired'], true)) {
            return $topup;
        }

        if ($topup->expired_at && now()->greaterThan($topup->expired_at) && ! $topup->is_simulated) {
            return $this->markFailedOrExpired($topup, 'expired', 'Top-up payment expired.');
        }

        if ($topup->provider !== 'mock' && $topup->provider_reference) {
            try {
                $provider = $this->providerResolver->resolveWebhookPaymentProvider($topup->provider);
                $statusInfo = $provider->getPaymentStatus($topup->provider_reference);
                if (($statusInfo['status'] ?? 'pending') === 'paid') {
                    return $this->markPaidAndSettle($topup, $topup->provider_reference, $statusInfo['raw_payload'] ?? null);
                }
                if (in_array($statusInfo['status'] ?? '', ['failed', 'expired'], true)) {
                    return $this->markFailedOrExpired($topup, (string) $statusInfo['status'], 'Provider status: '.$statusInfo['status']);
                }
            } catch (\Throwable) {
                // Keep current pending status if transient network inquiry fails
            }
        }

        return $topup;
    }

    public function simulatePaid(TopupTransaction $topup): TopupTransaction
    {
        $currentMode = $this->providerResolver->currentMode();
        if ($currentMode === 'production') {
            throw new RuntimeException('Simulasi pembayaran top-up dilarang pada mode production.');
        }
        if (! $topup->is_simulated && $topup->provider !== 'mock' && $currentMode !== 'demo' && $currentMode !== 'sandbox') {
            throw new RuntimeException('Transaksi ini bukan transaksi simulasi demo/sandbox.');
        }

        return $this->markPaidAndSettle($topup, $topup->provider_reference, ['simulated_by' => 'sandbox_endpoint']);
    }

    public function markPaidAndSettle(
        TopupTransaction $topup,
        ?string $providerReference = null,
        ?array $rawPayload = null
    ): TopupTransaction {
        return DB::transaction(function () use ($topup, $providerReference, $rawPayload) {
            $locked = TopupTransaction::where('id', $topup->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['settled', 'paid'], true)) {
                return $locked;
            }

            $now = now();
            if ($providerReference) {
                $locked->provider_reference = $providerReference;
            }
            if ($rawPayload !== null) {
                $locked->provider_payload = array_merge($locked->provider_payload ?? [], ['callback' => $rawPayload]);
            }

            $locked->status = 'settled';
            $locked->paid_at = $locked->paid_at ?? $now;
            $locked->settled_at = $now;
            $locked->save();

            $wallet = $locked->wallet()->firstOrFail();

            $this->walletManager->creditAvailable(
                $wallet,
                (int) $locked->net_amount,
                WalletLedgerEntry::ACCOUNT_SYSTEM_GATEWAY_CLEARING,
                'topup',
                $locked->reference,
                'topup-settle-'.$locked->id,
                "Top-up wallet via {$locked->channel_code} ({$locked->reference})",
                TopupTransaction::class,
                $locked->id,
                $locked->mode,
                [
                    'topup_id' => $locked->id,
                    'provider' => $locked->provider,
                    'channel_code' => $locked->channel_code,
                    'is_simulated' => (bool) $locked->is_simulated,
                ]
            );

            $this->walletManager->writeAuditLog(
                $locked->user_id,
                'system',
                $wallet->id,
                'topup_settled',
                TopupTransaction::class,
                $locked->id,
                ['status' => 'pending'],
                ['status' => 'settled', 'amount' => (int) $locked->net_amount]
            );

            return $locked->fresh();
        });
    }

    public function markFailedOrExpired(
        TopupTransaction $topup,
        string $status,
        ?string $reason = null,
        ?Carbon $occurredAt = null
    ): TopupTransaction {
        return DB::transaction(function () use ($topup, $status, $reason) {
            $locked = TopupTransaction::where('id', $topup->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['settled', 'paid'], true)) {
                return $locked;
            }

            $locked->status = $status === 'expired' ? 'expired' : 'failed';
            $locked->failure_reason = $reason;
            $locked->save();

            return $locked->fresh();
        });
    }
}
