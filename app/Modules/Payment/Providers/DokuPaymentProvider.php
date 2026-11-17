<?php

namespace App\Modules\Payment\Providers;

use App\Models\Setting;
use App\Modules\Payment\Contracts\PaymentProviderInterface;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class DokuPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly GatewayCredentialsResolver $credentials
    ) {}

    public function providerCode(): string
    {
        return 'doku';
    }

    public function isConfigured(): bool
    {
        return $this->credentials->doku()['configured'];
    }

    public function availableChannels(string $mode): array
    {
        return [
            [
                'payment_type' => 'qris',
                'channel_code' => 'QRIS',
                'label' => 'QRIS (DOKU)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'BCA_VIRTUAL_ACCOUNT',
                'label' => 'BCA Virtual Account (DOKU)',
                'fee_idr' => 0,
            ],
            [
                'payment_type' => 'va',
                'channel_code' => 'MANDIRI_VIRTUAL_ACCOUNT',
                'label' => 'Mandiri Virtual Account (DOKU)',
                'fee_idr' => 0,
            ],
        ];
    }

    /**
     * Initiate Jokul Checkout payment (POST /checkout/v1/payment).
     */
    public function createTopupPayment(array $payload): array
    {
        $creds = $this->credentials->doku();
        if (! $creds['configured']) {
            throw new RuntimeException('DOKU Client-Id and Secret Key are not configured.');
        }

        $mode = Schema::hasTable('settings')
            ? ((string) (Setting::get('payment_wallet_mode') ?: config('payment_wallet.mode', 'sandbox')))
            : (string) config('payment_wallet.mode', 'sandbox');
        $baseUrl = $mode === 'production' ? $creds['production_url'] : $creds['sandbox_url'];

        $clientId = $creds['client_id'];
        $secretKey = $creds['secret_key'];
        $timeout = $creds['timeout_seconds'];

        $requestTarget = '/checkout/v1/payment';
        $requestId = (string) Str::uuid();
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');
        $expiredAt = Carbon::parse($payload['expired_at'] ?? now()->addMinutes(60));

        $body = [
            'order' => [
                'amount' => (int) $payload['amount'],
                'invoice_number' => (string) $payload['reference'],
                'currency' => 'IDR',
            ],
            'payment' => [
                'payment_due_date' => max(15, (int) now()->diffInMinutes($expiredAt)),
            ],
            'customer' => [
                'id' => (string) ($payload['user_id'] ?? 'cust'),
                'name' => (string) ($payload['customer_name'] ?? 'City Courier User'),
            ],
        ];

        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $digest = base64_encode(hash('sha256', $rawBody, true));
        $signature = $this->generateSignature($clientId, $requestId, $timestamp, $requestTarget, $digest, $secretKey);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Client-Id' => $clientId,
                'Request-Id' => $requestId,
                'Request-Timestamp' => $timestamp,
                'Signature' => $signature,
                'Content-Type' => 'application/json',
            ])
            ->withBody($rawBody, 'application/json')
            ->post($baseUrl.$requestTarget);

        if (! $response->successful()) {
            throw new RuntimeException('DOKU payment request failed: HTTP '.$response->status());
        }

        $json = $response->json() ?: [];

        return [
            'provider' => 'doku',
            'mode' => $mode === 'production' ? 'production' : 'sandbox',
            'is_simulated' => false,
            'provider_reference' => (string) ($json['response']['order']['invoice_number'] ?? $payload['reference']),
            'va_number' => null,
            'qr_string' => null,
            'payment_url' => $json['response']['payment']['url'] ?? null,
            'instructions' => [
                'Buka tautan pembayaran DOKU Jokul untuk menyelesaikan transaksi.',
            ],
            'expired_at' => $expiredAt,
            'status' => 'pending',
            'raw_payload' => $json,
        ];
    }

    public function getPaymentStatus(string $providerReference): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('DOKU Client-Id and Secret Key are not configured.');
        }

        return [
            'status' => 'pending',
            'provider_reference' => $providerReference,
            'paid_at' => null,
            'raw_payload' => [],
        ];
    }

    public function verifyWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        $creds = $this->credentials->doku();
        $clientId = $creds['client_id'];
        $secretKey = $creds['secret_key'];
        if ($clientId === '' || $secretKey === '') {
            return false;
        }

        $headerMap = array_change_key_case(array_map(
            fn ($v) => is_array($v) ? (string) ($v[0] ?? '') : (string) $v,
            $headers
        ), CASE_LOWER);

        $reqClientId = $headerMap['client-id'] ?? '';
        $requestId = $headerMap['request-id'] ?? '';
        $timestamp = $headerMap['request-timestamp'] ?? '';
        $incomingSig = $headerMap['signature'] ?? '';
        $requestTarget = '/api/v1/webhooks/doku';

        if (! hash_equals($clientId, $reqClientId) || $requestId === '' || $timestamp === '' || $incomingSig === '') {
            return false;
        }

        $digest = base64_encode(hash('sha256', $rawBody, true));
        $expected = $this->generateSignature($clientId, $requestId, $timestamp, $requestTarget, $digest, $secretKey);

        return hash_equals($expected, $incomingSig);
    }

    public function parsePaymentWebhook(array $payload): array
    {
        $status = strtoupper((string) ($payload['transaction']['status'] ?? 'PENDING'));

        return [
            'event_type' => 'doku.payment',
            'external_id' => (string) ($payload['order']['invoice_number'] ?? ''),
            'provider_reference' => (string) ($payload['transaction']['original_request_id'] ?? $payload['order']['invoice_number'] ?? ''),
            'status' => match ($status) {
                'SUCCESS' => 'paid',
                'FAILED' => 'failed',
                'EXPIRED' => 'expired',
                default => 'pending',
            },
            'amount' => (int) ($payload['order']['amount'] ?? 0),
            'paid_at' => $payload['transaction']['date'] ?? now()->toIso8601String(),
            'failure_reason' => null,
        ];
    }

    private function generateSignature(
        string $clientId,
        string $requestId,
        string $timestamp,
        string $requestTarget,
        string $digest,
        string $secretKey
    ): string {
        $component = "Client-Id:{$clientId}\n"
            ."Request-Id:{$requestId}\n"
            ."Request-Timestamp:{$timestamp}\n"
            ."Request-Target:{$requestTarget}\n"
            ."Digest:{$digest}";

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', $component, $secretKey, true));
    }
}
