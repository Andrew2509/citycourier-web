<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PayoutProviderInterface;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XenditPayoutProvider implements PayoutProviderInterface
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

    public function validateDestination(string $channelCode, string $accountNumber, string $accountHolderName): array
    {
        $cleanNumber = preg_replace('/\s+|-/', '', $accountNumber) ?? '';
        $valid = strlen($cleanNumber) >= 6 && preg_match('/^[0-9]+$/', $cleanNumber) === 1;

        return [
            'valid' => $valid,
            'account_holder_name' => $valid ? trim($accountHolderName) : null,
            'message' => $valid ? 'Format rekening tervalidasi untuk kanal '.$channelCode.'.' : 'Nomor rekening/e-wallet tidak valid.',
        ];
    }

    /**
     * Dispatch payout via Xendit Payouts API v2 (POST /v2/payouts).
     */
    public function createPayout(array $payload): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured for payouts.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $timeout = $creds['timeout_seconds'];

        $reference = (string) $payload['reference'];
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? $reference);

        $body = [
            'reference_id' => $reference,
            'channel_code' => $this->normalizeXenditPayoutChannel((string) $payload['channel_code']),
            'channel_properties' => [
                'account_number' => (string) $payload['account_number'],
                'account_holder_name' => (string) $payload['account_holder_name'],
            ],
            'amount' => (int) $payload['amount'],
            'description' => (string) ($payload['description'] ?? 'City Courier Payout '.$reference),
            'currency' => 'IDR',
        ];

        $response = Http::timeout($timeout)
            ->withBasicAuth($secretKey, '')
            ->withHeaders([
                'Idempotency-key' => $idempotencyKey,
            ])
            ->post("{$baseUrl}/v2/payouts", $body);

        if (! $response->successful()) {
            $errJson = $response->json() ?: [];
            throw new RuntimeException(
                'Xendit payout failed: '.($errJson['message'] ?? ('HTTP '.$response->status()))
            );
        }

        $json = $response->json() ?: [];

        return [
            'provider' => 'xendit',
            'mode' => str_starts_with($secretKey, 'xnd_development_') ? 'sandbox' : 'production',
            'is_simulated' => false,
            'provider_reference' => (string) ($json['id'] ?? $reference),
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'ACCEPTED')),
            'failure_code' => $json['failure_code'] ?? null,
            'failure_reason' => null,
            'raw_payload' => $json,
        ];
    }

    public function getPayoutStatus(string $providerReference, ?string $referenceId = null): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured.');
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $timeout = $creds['timeout_seconds'];

        if ($providerReference !== '') {
            $response = Http::timeout($timeout)
                ->withBasicAuth($secretKey, '')
                ->get("{$baseUrl}/v2/payouts/{$providerReference}");
            $json = $response->successful() ? ($response->json() ?: []) : [];
        } else {
            $response = Http::timeout($timeout)
                ->withBasicAuth($secretKey, '')
                ->get("{$baseUrl}/v2/payouts", ['reference_id' => $referenceId]);
            $list = $response->successful() ? ($response->json() ?: []) : [];
            $json = is_array($list['data'] ?? null) ? ($list['data'][0] ?? []) : ($list[0] ?? []);
        }

        if (! $response->successful() || empty($json)) {
            return [
                'status' => 'unknown',
                'provider_reference' => $providerReference,
                'failure_code' => null,
                'failure_reason' => 'Unable to verify payout status from Xendit.',
                'raw_payload' => [],
            ];
        }

        return [
            'status' => $this->normalizeStatus((string) ($json['status'] ?? 'PROCESSING')),
            'provider_reference' => (string) ($json['id'] ?? $providerReference),
            'failure_code' => $json['failure_code'] ?? null,
            'failure_reason' => $json['failure_code'] ?? null,
            'raw_payload' => $json,
        ];
    }

    public function verifyPayoutWebhookSignature(array $headers, string $rawBody, array $payload): bool
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

    public function parsePayoutWebhook(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $event = (string) ($payload['event'] ?? 'xendit.payout');

        $rawStatus = (string) ($data['status'] ?? match ($event) {
            'payout.succeeded', 'v3_payout.succeeded' => 'SUCCEEDED',
            'payout.failed', 'v3_payout.failed' => 'FAILED',
            'payout.reversed', 'v3_payout.reversed' => 'REVERSED',
            default => 'PROCESSING',
        });

        return [
            'event_type' => $event,
            'external_id' => (string) ($data['reference_id'] ?? $payload['reference_id'] ?? ''),
            'provider_reference' => (string) ($data['id'] ?? $payload['id'] ?? ''),
            'status' => $this->normalizeStatus($rawStatus),
            'failure_code' => $data['failure_code'] ?? null,
            'failure_reason' => $data['failure_code'] ?? null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCEEDED', 'COMPLETED' => 'completed',
            'FAILED', 'CANCELLED' => 'failed',
            'REVERSED' => 'reversed',
            default => 'processing',
        };
    }

    private function normalizeXenditPayoutChannel(string $code): string
    {
        $upper = strtoupper(trim($code));

        return str_starts_with($upper, 'ID_') ? $upper : 'ID_'.$upper;
    }
}
