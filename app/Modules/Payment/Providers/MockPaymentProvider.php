<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PaymentProviderInterface;
use App\Modules\Payment\Contracts\PayoutProviderInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class MockPaymentProvider implements PaymentProviderInterface, PayoutProviderInterface
{
    public function providerCode(): string
    {
        return 'mock';
    }

    public function isConfigured(): bool
    {
        return $this->currentMode() !== 'production';
    }

    public function availableChannels(string $mode): array
    {
        if ($mode === 'production') {
            return [];
        }

        return [
            [
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
                'label' => 'QRIS (Demo/Sandbox Simulasi)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
                'label' => 'BCA Virtual Account (Simulasi)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BRI_VIRTUAL_ACCOUNT',
                'label' => 'BRI Virtual Account (Simulasi)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'MANDIRI_VIRTUAL_ACCOUNT',
                'label' => 'Mandiri Virtual Account (Simulasi)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_DANA',
                'label' => 'DANA E-Wallet (Simulasi)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_OVO',
                'label' => 'OVO E-Wallet (Simulasi)',
                'fee_idr' => 0,
            ],
        ];
    }

    public function createTopupPayment(array $payload): array
    {
        $this->assertNotProduction();

        $reference = (string) $payload['reference'];
        $channelCode = strtoupper((string) $payload['channel_code']);
        $paymentType = strtolower((string) $payload['payment_type']);
        $amount = (int) $payload['amount'];
        $expiredAt = Carbon::parse($payload['expired_at'] ?? now()->addMinutes(60));

        $providerRef = 'MOCK-PR-'.strtoupper(substr(md5($reference), 0, 12));
        $vaNumber = $paymentType === 'va'
            ? '8808'.substr(preg_replace('/\D+/', '', md5($reference)) ?: '1234567890', 0, 10)
            : null;
        $qrString = $paymentType === 'qris'
            ? '00020101021226580014ID.CO.CITYCOURIER.SIM520442155303360540'.strlen((string) $amount).$amount.'5802ID5912CITY COURIER6007JAKARTA62140110'.$providerRef.'6304SIMU'
            : null;
        $paymentUrl = $paymentType === 'ewallet'
            ? url("/api/v1/webhooks/mock-checkout/{$providerRef}")
            : null;

        $instructions = match ($paymentType) {
            'va' => [
                'Buka aplikasi m-Banking / ATM sesuai bank pilihan Anda.',
                'Pilih menu Transfer Virtual Account dan masukkan nomor: '.$vaNumber,
                'Pastikan nominal tepat Rp '.number_format($amount, 0, ',', '.').'.',
                'Pada mode Sandbox/Demo, tekan tombol "Simulasikan Pembayaran Berhasil" untuk pengujian.',
            ],
            'qris' => [
                'Scan QRIS simulasi di bawah ini atau gunakan tombol Simulasi Pembayaran di Sandbox/Demo.',
                'Nominal pembayaran: Rp '.number_format($amount, 0, ',', '.').'.',
            ],
            default => [
                'Selesaikan pembayaran melalui aplikasi e-wallet '.$channelCode.' (Simulasi Sandbox/Demo).',
            ],
        };

        return [
            'provider' => 'mock',
            'mode' => $this->currentMode(),
            'is_simulated' => true,
            'provider_reference' => $providerRef,
            'va_number' => $vaNumber,
            'qr_string' => $qrString,
            'payment_url' => $paymentUrl,
            'instructions' => $instructions,
            'expired_at' => $expiredAt,
            'status' => 'pending',
            'raw_payload' => [
                'simulated' => true,
                'reference_id' => $reference,
                'channel_code' => $channelCode,
                'amount' => $amount,
            ],
        ];
    }

    public function getPaymentStatus(string $providerReference): array
    {
        $this->assertNotProduction();

        return [
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'paid_at' => null,
            'raw_payload' => ['simulated' => true, 'provider_reference' => $providerReference],
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        return $this->currentMode() !== 'production';
    }

    public function parsePaymentWebhook(array $payload): array
    {
        return [
            'event_type' => (string) ($payload['event'] ?? 'mock.payment'),
            'external_id' => (string) ($payload['reference_id'] ?? ''),
            'provider_reference' => (string) ($payload['provider_reference'] ?? ''),
            'status' => strtolower((string) ($payload['status'] ?? 'paid')),
            'amount' => (int) ($payload['amount'] ?? 0),
            'paid_at' => $payload['paid_at'] ?? now()->toIso8601String(),
            'failure_reason' => $payload['failure_reason'] ?? null,
        ];
    }

    public function validateDestination(string $channelCode, string $accountNumber, string $accountHolderName): array
    {
        $cleanNumber = preg_replace('/\s+|-/', '', $accountNumber) ?? '';
        $valid = strlen($cleanNumber) >= 6 && preg_match('/^[0-9]+$/', $cleanNumber) === 1;

        return [
            'valid' => $valid,
            'account_holder_name' => $valid ? trim($accountHolderName) : null,
            'message' => $valid ? 'Format rekening valid (Sandbox/Demo check).' : 'Nomor rekening/e-wallet harus berisi minimal 6 digit angka.',
        ];
    }

    public function createPayout(array $payload): array
    {
        $this->assertNotProduction();

        $accountNumber = (string) ($payload['account_number'] ?? '');
        $description = (string) ($payload['description'] ?? '');
        $reference = (string) $payload['reference'];
        $providerRef = 'MOCK-PO-'.strtoupper(substr(md5($reference), 0, 12));

        // Deterministic sandbox test hooks for timeout / failure scenarios
        if (str_contains($description, '[SIM_FAIL]') || str_ends_with($accountNumber, '999')) {
            return [
                'provider' => 'mock',
                'mode' => $this->currentMode(),
                'is_simulated' => true,
                'provider_reference' => $providerRef,
                'status' => 'failed',
                'failure_code' => 'SANDBOX_SIMULATED_FAILURE',
                'failure_reason' => 'Simulated payout rejection by destination channel.',
                'raw_payload' => ['simulated' => true, 'status' => 'FAILED'],
            ];
        }

        if (str_contains($description, '[SIM_TIMEOUT]')) {
            return [
                'provider' => 'mock',
                'mode' => $this->currentMode(),
                'is_simulated' => true,
                'provider_reference' => $providerRef,
                'status' => 'processing',
                'failure_code' => 'PROVIDER_TIMEOUT',
                'failure_reason' => 'Simulated request timeout; awaiting status inquiry.',
                'raw_payload' => ['simulated' => true, 'status' => 'ACCEPTED'],
            ];
        }

        return [
            'provider' => 'mock',
            'mode' => $this->currentMode(),
            'is_simulated' => true,
            'provider_reference' => $providerRef,
            'status' => 'completed',
            'failure_code' => null,
            'failure_reason' => null,
            'raw_payload' => ['simulated' => true, 'status' => 'SUCCEEDED', 'id' => $providerRef],
        ];
    }

    public function getPayoutStatus(string $providerReference, ?string $referenceId = null): array
    {
        $this->assertNotProduction();

        if (str_contains($providerReference, 'FAIL')) {
            return [
                'status' => 'failed',
                'provider_reference' => $providerReference,
                'failure_code' => 'SANDBOX_SIMULATED_FAILURE',
                'failure_reason' => 'Simulated failure during status inquiry.',
                'raw_payload' => ['simulated' => true, 'status' => 'FAILED'],
            ];
        }

        return [
            'status' => 'completed',
            'provider_reference' => $providerReference,
            'failure_code' => null,
            'failure_reason' => null,
            'raw_payload' => ['simulated' => true, 'status' => 'SUCCEEDED'],
        ];
    }

    public function verifyPayoutWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        return $this->currentMode() !== 'production';
    }

    public function parsePayoutWebhook(array $payload): array
    {
        return [
            'event_type' => (string) ($payload['event'] ?? 'mock.payout'),
            'external_id' => (string) ($payload['reference_id'] ?? ''),
            'provider_reference' => (string) ($payload['provider_reference'] ?? ''),
            'status' => strtolower((string) ($payload['status'] ?? 'completed')),
            'failure_code' => $payload['failure_code'] ?? null,
            'failure_reason' => $payload['failure_reason'] ?? null,
        ];
    }

    private function assertNotProduction(): void
    {
        if ($this->currentMode() === 'production') {
            throw new RuntimeException('MockPaymentProvider is strictly forbidden in production mode.');
        }
    }

    private function currentMode(): string
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            $dbMode = \App\Models\Setting::get('payment_wallet_mode');
            if (is_string($dbMode) && in_array($dbMode, ['demo', 'sandbox', 'production'], true)) {
                return $dbMode;
            }
        }

        return (string) config('payment_wallet.mode', 'sandbox');
    }
}
