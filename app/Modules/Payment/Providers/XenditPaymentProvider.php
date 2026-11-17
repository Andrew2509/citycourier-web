<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PaymentProviderInterface;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XenditPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly GatewayCredentialsResolver $credentials
    ) {}

    public function providerCode(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        $creds = $this->credentials->xendit();

        return $creds['enabled'] && $creds['configured'];
    }

    public function availableChannels(string $mode): array
    {
        return [
            [
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
                'label' => 'QRIS (Xendit)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
                'label' => 'BCA Virtual Account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BNI_VIRTUAL_ACCOUNT',
                'label' => 'BNI Virtual Account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BRI_VIRTUAL_ACCOUNT',
                'label' => 'BRI Virtual Account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'MANDIRI_VIRTUAL_ACCOUNT',
                'label' => 'Mandiri Virtual Account',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_DANA',
                'label' => 'DANA E-Wallet',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'ewallet',
                'channel_code' => 'ID_OVO',
                'label' => 'OVO E-Wallet',
                'fee_idr' => 0,
            ],
        ];
    }

    /**
     * Create payment request using Xendit Payment Requests API v3 (POST /v3/payment_requests).
     */
    public function createTopupPayment(array $payload): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $apiVersion = $creds['api_version'];
        $timeout = $creds['timeout_seconds'];

        $reference = (string) $payload['reference'];
        $paymentType = strtolower((string) $payload['payment_type']);
        $channelCode = strtoupper((string) $payload['channel_code']);
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
            'description' => (string) ($payload['description'] ?? 'Top-up City Courier Wallet'),
            'metadata' => [
                'wallet_id' => $payload['wallet_id'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
            ],
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
            'provider_reference' => (string) ($json['payment_request_id'] ?? $json['id'] ?? $providerReference),
            'paid_at' => $json['updated'] ?? null,
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
            'FAILED', 'CANCELLED', 'CANCELED' => 'failed',
            'EXPIRED' => 'expired',
            default => 'pending',
        };
    }
}
