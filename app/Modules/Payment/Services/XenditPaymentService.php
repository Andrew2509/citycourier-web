<?php

namespace App\Modules\Payment\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Capability adapter khusus Xendit Payments API v3 (Money-In):
 * - Top-Up City Wallet (VA, QRIS, E-Wallet)
 * - Pembayaran Pesanan / Checkout (VA, QRIS, E-Wallet)
 *
 * Dokumentasi resmi docs.xendit.co:
 * - POST /v3/payment_requests
 * - GET  /v3/payment_requests/{payment_request_id}
 * - POST /v3/payment_requests/{payment_request_id}/cancel
 * - Webhook verification: x-callback-token
 */
class XenditPaymentService
{
    public function __construct(
        private readonly GatewayCredentialsResolver $credentials
    ) {}

    public function isConfigured(): bool
    {
        $creds = $this->credentials->xendit();

        return $creds['enabled'] && $creds['configured'];
    }

    /**
     * Daftar kanal Money-In resmi Xendit v3, difilter oleh konfigurasi `allowed_channels`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableChannels(string $mode = 'sandbox'): array
    {
        $all = [
            [
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
                'label' => 'QRIS (Xendit)',
                'name' => 'QRIS',
                'group' => 'qris',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
                'label' => 'BCA Virtual Account',
                'name' => 'BCA Virtual Account',
                'group' => 'virtual_account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BNI_VIRTUAL_ACCOUNT',
                'label' => 'BNI Virtual Account',
                'name' => 'BNI Virtual Account',
                'group' => 'virtual_account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BRI_VIRTUAL_ACCOUNT',
                'label' => 'BRI Virtual Account',
                'name' => 'BRI Virtual Account',
                'group' => 'virtual_account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'MANDIRI_VIRTUAL_ACCOUNT',
                'label' => 'Mandiri Virtual Account',
                'name' => 'Mandiri Virtual Account',
                'group' => 'virtual_account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'PERMATA_VIRTUAL_ACCOUNT',
                'label' => 'Permata Virtual Account',
                'name' => 'Permata Virtual Account',
                'group' => 'virtual_account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_DANA',
                'label' => 'DANA E-Wallet',
                'name' => 'DANA',
                'group' => 'ewallet',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_OVO',
                'label' => 'OVO E-Wallet',
                'name' => 'OVO',
                'group' => 'ewallet',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_SHOPEEPAY',
                'label' => 'ShopeePay E-Wallet',
                'name' => 'ShopeePay',
                'group' => 'ewallet',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_LINKAJA',
                'label' => 'LinkAja E-Wallet',
                'name' => 'LinkAja',
                'group' => 'ewallet',
                'fee_idr' => 0,
            ],
        ];

        $allowed = array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            (array) config('payment_wallet.topup.allowed_channels', [])
        );

        if (empty($allowed)) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn (array $ch) => in_array(strtoupper($ch['channel_code']), $allowed, true)
        ));
    }

    /**
     * Format daftar metode untuk endpoint checkout `/api/payment/methods` (kompatibel dengan model PaymentMethod Flutter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableCheckoutMethods(string $mode = 'sandbox'): array
    {
        return array_map(fn (array $ch) => [
            'code' => $ch['channel_code'],
            'channel_code' => $ch['channel_code'],
            'name' => $ch['name'],
            'group' => $ch['group'],
            'payment_type' => $ch['payment_type'],
            'fee' => (int) ($ch['fee_idr'] ?? 0),
            'is_available' => true,
            'provider' => 'xendit',
        ], $this->availableChannels($mode));
    }

    public function normalizeChannelCode(string $paymentType, ?string $rawChannel): string
    {
        $type = strtolower(trim($paymentType));
        $upper = strtoupper(trim((string) $rawChannel));

        if ($type === 'qris' || $upper === 'QRIS') {
            return 'QRIS';
        }

        if ($type === 'va' || $type === 'virtual_account' || $type === 'bank_transfer') {
            $clean = str_replace(['_VIRTUAL_ACCOUNT', 'ID_'], '', $upper);

            return match ($clean) {
                'BCA' => 'BCA_VIRTUAL_ACCOUNT',
                'BNI' => 'BNI_VIRTUAL_ACCOUNT',
                'BRI' => 'BRI_VIRTUAL_ACCOUNT',
                'MANDIRI' => 'MANDIRI_VIRTUAL_ACCOUNT',
                'PERMATA' => 'PERMATA_VIRTUAL_ACCOUNT',
                default => str_ends_with($upper, '_VIRTUAL_ACCOUNT') ? $upper : ($clean !== '' ? $clean.'_VIRTUAL_ACCOUNT' : 'BCA_VIRTUAL_ACCOUNT'),
            };
        }

        return match ($upper) {
            'DANA', 'ID_DANA' => 'ID_DANA',
            'OVO', 'ID_OVO' => 'ID_OVO',
            'SHOPEEPAY', 'ID_SHOPEEPAY', 'SPAY' => 'ID_SHOPEEPAY',
            'LINKAJA', 'ID_LINKAJA' => 'ID_LINKAJA',
            default => str_starts_with($upper, 'ID_') ? $upper : ($upper !== '' ? 'ID_'.$upper : 'ID_DANA'),
        };
    }

    /**
     * Buat Payment Request di Xendit v3 (`POST /v3/payment_requests`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPaymentRequest(array $payload): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['enabled']) {
            throw new RuntimeException('PROVIDER_DISABLED:Gateway Xendit sedang dinonaktifkan oleh Admin.');
        }
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $apiVersion = $creds['api_version'];
        $timeout = $creds['timeout_seconds'];

        $reference = (string) $payload['reference'];
        $rawType = strtolower((string) ($payload['payment_type'] ?? 'qris'));
        $paymentType = match ($rawType) {
            'va', 'virtual_account', 'bank_transfer' => 'va',
            'ewallet' => 'ewallet',
            default => 'qris',
        };
        $channelCode = $this->normalizeChannelCode($paymentType, (string) ($payload['channel_code'] ?? ''));

        $allowedCodes = array_map(
            fn (array $ch) => strtoupper((string) $ch['channel_code']),
            $this->availableChannels()
        );
        if (! in_array($channelCode, $allowedCodes, true)) {
            throw new RuntimeException("Kanal pembayaran {$channelCode} belum didukung atau dinonaktifkan.");
        }

        $amount = (int) $payload['amount'];
        $expiredAt = Carbon::parse($payload['expired_at'] ?? now()->addMinutes(60));
        $customerName = (string) ($payload['customer_name'] ?? 'City Courier Customer');

        $channelProperties = match ($paymentType) {
            'va' => [
                'display_name' => mb_substr($customerName, 0, 50),
                'expires_at' => $expiredAt->toIso8601String(),
            ],
            'qris' => [
                'expires_at' => $expiredAt->toIso8601String(),
            ],
            'ewallet' => [
                'success_return_url' => (string) ($payload['success_url'] ?? url('/')),
                'failure_return_url' => (string) ($payload['failure_url'] ?? url('/')),
            ],
            default => [],
        };

        $body = [
            'reference_id' => $reference,
            'type' => 'PAY',
            'country' => 'ID',
            'currency' => 'IDR',
            'request_amount' => $amount,
            'channel_code' => $channelCode,
            'channel_properties' => $channelProperties,
            'description' => (string) ($payload['description'] ?? 'Pembayaran City Courier'),
            'metadata' => array_filter([
                'wallet_id' => $payload['wallet_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'shipment_id' => $payload['shipment_id'] ?? null,
                'order_id' => $payload['order_id'] ?? null,
                'purpose' => $payload['purpose'] ?? 'topup',
            ], fn ($v) => $v !== null),
        ];

        $response = Http::timeout($timeout)
            ->withBasicAuth($secretKey, '')
            ->withHeaders([
                'api-version' => $apiVersion,
                'Idempotency-key' => (string) ($payload['idempotency_key'] ?? $reference),
            ])
            ->post("{$baseUrl}/v3/payment_requests", $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Xendit payment request failed: HTTP '.$response->status().' - '.$response->body()
            );
        }

        $json = $response->json() ?: [];
        $actions = $json['actions'] ?? [];
        $vaNumber = null;
        $qrString = null;
        $paymentUrl = null;

        foreach ($actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;
            if (! $value) {
                continue;
            }
            if ($type === 'PRESENT_TO_CUSTOMER' && $paymentType === 'va') {
                $vaNumber = (string) $value;
            } elseif ($type === 'PRESENT_TO_CUSTOMER' && $paymentType === 'qris') {
                $qrString = (string) $value;
            } elseif ($type === 'REDIRECT_CUSTOMER') {
                $paymentUrl = (string) $value;
            }
        }

        return [
            'provider' => 'xendit',
            'mode' => str_starts_with($secretKey, 'xnd_development_') ? 'sandbox' : 'production',
            'is_simulated' => false,
            'provider_reference' => (string) ($json['payment_request_id'] ?? $json['id'] ?? $reference),
            'channel_code' => $channelCode,
            'va_number' => $vaNumber,
            'qr_string' => $qrString,
            'payment_url' => $paymentUrl,
            'instructions' => [
                'Selesaikan pembayaran sesuai instruksi kanal '.$channelCode.'.',
            ],
            'expired_at' => $expiredAt,
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'REQUIRES_ACTION')),
            'raw_payload' => $json,
        ];
    }

    /**
     * Cek status Payment Request di Xendit (`GET /v3/payment_requests/{id}`).
     *
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $providerReference): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $apiVersion = $creds['api_version'];
        $timeout = $creds['timeout_seconds'];

        $response = Http::timeout($timeout)
            ->withBasicAuth($secretKey, '')
            ->withHeaders(['api-version' => $apiVersion])
            ->get("{$baseUrl}/v3/payment_requests/{$providerReference}");

        if (! $response->successful()) {
            throw new RuntimeException('Xendit payment inquiry failed: HTTP '.$response->status());
        }

        $json = $response->json() ?: [];

        return [
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'PENDING')),
            'provider_status' => (string) ($json['status'] ?? 'PENDING'),
            'provider_reference' => (string) ($json['payment_request_id'] ?? $json['id'] ?? $providerReference),
            'paid_at' => $json['updated'] ?? null,
            'raw_payload' => $json,
        ];
    }

    /**
     * Batalkan Payment Request Xendit v3 yang belum dibayar (`POST /v3/payment_requests/{id}/cancel`).
     *
     * @return array{cancelled: bool, status: string, message: string, raw_payload: array<string, mixed>}
     */
    public function cancelPaymentRequest(string $providerReference): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $apiVersion = $creds['api_version'];
        $timeout = $creds['timeout_seconds'];

        $response = Http::timeout($timeout)
            ->withBasicAuth($secretKey, '')
            ->withHeaders(['api-version' => $apiVersion])
            ->post("{$baseUrl}/v3/payment_requests/{$providerReference}/cancel");

        $json = $response->json() ?: [];

        if (! $response->successful()) {
            return [
                'cancelled' => false,
                'status' => 'unknown',
                'message' => $json['message'] ?? ('Gagal membatalkan di Xendit (HTTP '.$response->status().')'),
                'raw_payload' => $json,
            ];
        }

        $rawStatus = (string) ($json['status'] ?? 'CANCELED');

        return [
            'cancelled' => true,
            'status' => $this->normalizeStatus($rawStatus),
            'message' => 'Transaksi pembayaran berhasil dibatalkan di Xendit.',
            'raw_payload' => $json,
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        $configuredToken = $this->credentials->xendit()['callback_token'];
        if ($configuredToken === '') {
            return false;
        }

        $incoming = '';
        foreach ($headers as $key => $val) {
            if (strtolower((string) $key) === 'x-callback-token') {
                $incoming = is_array($val) ? (string) ($val[0] ?? '') : (string) $val;
                break;
            }
        }

        return $incoming !== '' && hash_equals($configuredToken, $incoming);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parsePaymentWebhook(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $event = (string) ($payload['event'] ?? 'xendit.payment');
        $rawStatus = (string) ($data['status'] ?? ($event === 'payment.succeeded' ? 'SUCCEEDED' : 'PENDING'));

        return [
            'event_type' => $event,
            'external_id' => (string) ($data['reference_id'] ?? $payload['reference_id'] ?? ''),
            'provider_reference' => (string) ($data['payment_request_id'] ?? $data['id'] ?? ''),
            'status' => $this->normalizeStatus($rawStatus),
            'amount' => (int) ($data['request_amount'] ?? $data['amount'] ?? 0),
            'paid_at' => $data['updated'] ?? $payload['created'] ?? now()->toIso8601String(),
            'failure_reason' => $data['failure_code'] ?? null,
        ];
    }

    private function normalizeStatus(string $xenditStatus): string
    {
        return match (strtoupper($xenditStatus)) {
            'SUCCEEDED', 'PAID', 'SETTLED' => 'paid',
            'CANCELLED', 'CANCELED' => 'canceled',
            'FAILED' => 'failed',
            'EXPIRED' => 'expired',
            default => 'pending',
        };
    }
}
