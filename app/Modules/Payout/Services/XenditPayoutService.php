<?php

namespace App\Modules\Payout\Services;

use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Capability adapter khusus Xendit Payouts v2 (Money-Out).
 *
 * Endpoint resmi (docs.xendit.co):
 * - GET  /payouts_channels
 * - POST /v2/payouts (dengan header Idempotency-key)
 * - GET  /v2/payouts/{id} & GET /v2/payouts?reference_id=...
 * - Webhook verification via header x-callback-token
 */
class XenditPayoutService
{
    /**
     * Kanal bank Indonesia yang didukung Xendit Payouts v2 (IDR).
     *
     * @var array<string, string>
     */
    public const BANK_CHANNELS = [
        'ID_BCA' => 'Bank BCA',
        'ID_BRI' => 'Bank BRI',
        'ID_BNI' => 'Bank BNI',
        'ID_MANDIRI' => 'Bank Mandiri',
        'ID_PERMATA' => 'Bank Permata',
        'ID_CIMB' => 'Bank CIMB Niaga',
        'ID_BSI' => 'Bank Syariah Indonesia (BSI)',
    ];

    /**
     * Kanal e-wallet Indonesia yang didukung Xendit Payouts v2 (IDR).
     *
     * @var array<string, string>
     */
    public const EWALLET_CHANNELS = [
        'ID_DANA' => 'DANA',
        'ID_OVO' => 'OVO',
        'ID_GOPAY' => 'GoPay',
        'ID_SHOPEEPAY' => 'ShopeePay',
        'ID_LINKAJA' => 'LinkAja',
    ];

    public function __construct(
        private readonly GatewayCredentialsResolver $credentials
    ) {}

    public function isConfigured(): bool
    {
        $creds = $this->credentials->xendit();

        return $creds['enabled'] && $creds['configured'];
    }

    public function normalizeChannelCode(string $code): string
    {
        $upper = strtoupper(trim($code));
        if ($upper === '') {
            return '';
        }

        return str_starts_with($upper, 'ID_') ? $upper : 'ID_'.$upper;
    }

    /**
     * Kembalikan katalog kanal payout yang benar-benar didukung oleh Xendit untuk negara ID / IDR.
     * Bila kredensial Xendit aktif & fetch live `/payouts_channels` diizinkan di config,
     * daftar akan difilter terhadap kanal aktif dari API Xendit; jika offline/belum diset,
     * tetap memakai daftar kanal terverifikasi (tanpa membuka kanal tidak dikenal).
     *
     * @return array{bank: array<string, string>, ewallet: array<string, string>}
     */
    public function availablePayoutChannels(bool $forceRefresh = false): array
    {
        $catalog = [
            'bank' => self::BANK_CHANNELS,
            'ewallet' => self::EWALLET_CHANNELS,
        ];

        $disabledChannels = array_map(
            fn ($c) => $this->normalizeChannelCode((string) $c),
            (array) config('payment_wallet.xendit.disabled_payout_channels', [])
        );

        foreach (['bank', 'ewallet'] as $type) {
            foreach ($disabledChannels as $blocked) {
                unset($catalog[$type][$blocked]);
            }
        }

        $shouldFetchLive = (bool) config('payment_wallet.xendit.sync_payout_channels', false);
        $creds = $this->credentials->xendit();
        if (! $shouldFetchLive || ! $creds['enabled'] || ! $creds['configured']) {
            return $catalog;
        }

        $cacheKey = 'xendit:payout_channels:'.md5($creds['base_url'].'|'.substr($creds['secret_key'], 0, 12));
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $liveCodes = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($creds) {
            try {
                $response = Http::timeout($creds['timeout_seconds'])
                    ->withBasicAuth($creds['secret_key'], '')
                    ->get("{$creds['base_url']}/payouts_channels", ['currency' => 'IDR']);

                if (! $response->successful()) {
                    return null;
                }

                $items = $response->json();
                if (! is_array($items)) {
                    return null;
                }

                $codes = [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $code = strtoupper((string) ($item['channel_code'] ?? ''));
                    if ($code !== '') {
                        $codes[$code] = true;
                    }
                }

                return $codes;
            } catch (\Throwable) {
                return null;
            }
        });

        if (is_array($liveCodes) && ! empty($liveCodes)) {
            foreach (['bank', 'ewallet'] as $type) {
                $catalog[$type] = array_filter(
                    $catalog[$type],
                    fn ($label, $code) => isset($liveCodes[$code]),
                    ARRAY_FILTER_USE_BOTH
                );
            }
        }

        return $catalog;
    }

    public function isChannelSupported(string $destinationType, string $channelCode): bool
    {
        $type = strtolower(trim($destinationType));
        if (! in_array($type, ['bank', 'ewallet'], true)) {
            return false;
        }

        $normalized = $this->normalizeChannelCode($channelCode);
        $channels = $this->availablePayoutChannels();

        return array_key_exists($normalized, $channels[$type] ?? []);
    }

    /**
     * @return array{valid: bool, account_holder_name: string|null, message: string|null}
     */
    public function validateDestination(
        string $channelCode,
        string $accountNumber,
        string $accountHolderName,
        ?string $destinationType = null
    ): array {
        $normalized = $this->normalizeChannelCode($channelCode);
        $cleanNumber = preg_replace('/\s+|-/', '', $accountNumber) ?? '';

        $type = $destinationType ? strtolower(trim($destinationType)) : (
            array_key_exists($normalized, self::EWALLET_CHANNELS) ? 'ewallet' : 'bank'
        );

        if (! $this->isChannelSupported($type, $normalized)) {
            return [
                'valid' => false,
                'account_holder_name' => null,
                'message' => "Kanal {$normalized} tidak didukung oleh layanan payout Xendit.",
            ];
        }

        $minDigits = $type === 'ewallet' ? 9 : 6;
        $valid = strlen($cleanNumber) >= $minDigits && preg_match('/^[0-9]+$/', $cleanNumber) === 1;

        return [
            'valid' => $valid,
            'account_holder_name' => $valid ? trim($accountHolderName) : null,
            'message' => $valid ? 'Format rekening tervalidasi untuk kanal '.$normalized.'.' : 'Nomor rekening/e-wallet tidak valid.',
        ];
    }

    /**
     * Kirim payout via Xendit Payouts API v2 (POST /v2/payouts).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayout(array $payload): array
    {
        $creds = $this->credentials->xendit();
        if (! $creds['enabled']) {
            throw new RuntimeException('PROVIDER_DISABLED:Gateway Xendit sedang dinonaktifkan oleh Admin.');
        }
        if (! $creds['configured']) {
            throw new RuntimeException('Xendit secret key is not configured for payouts.');
        }

        $channelCode = $this->normalizeChannelCode((string) ($payload['channel_code'] ?? ''));
        $isEwallet = array_key_exists($channelCode, self::EWALLET_CHANNELS);
        $destType = (string) ($payload['destination_type'] ?? ($isEwallet ? 'ewallet' : 'bank'));

        if (! $this->isChannelSupported($destType, $channelCode)) {
            throw new RuntimeException("Kanal {$channelCode} tidak didukung untuk payout Xendit.");
        }

        $baseUrl = $creds['base_url'];
        $secretKey = $creds['secret_key'];
        $timeout = $creds['timeout_seconds'];

        $reference = (string) $payload['reference'];
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? $reference);
        $accountNumber = $this->formatAccountNumberForPayout($destType, (string) $payload['account_number']);

        $body = [
            'reference_id' => $reference,
            'channel_code' => $channelCode,
            'channel_properties' => [
                'account_number' => $accountNumber,
                'account_holder_name' => trim((string) ($payload['account_holder_name'] ?? 'Pemilik Akun')),
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

    /**
     * Cek status payout ke Xendit (GET /v2/payouts/{id} atau ?reference_id=...).
     *
     * @return array<string, mixed>
     */
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

    private function formatAccountNumberForPayout(string $destType, string $rawNumber): string
    {
        $digits = preg_replace('/\D+/', '', $rawNumber) ?? '';
        if ($destType === 'ewallet' && str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCEEDED', 'COMPLETED' => 'completed',
            'FAILED', 'CANCELLED', 'CANCELED' => 'failed',
            'REVERSED' => 'reversed',
            default => 'processing',
        };
    }
}
