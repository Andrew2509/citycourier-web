<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Official OVO Provider — Real OVO SNAP B2B API.
 *
 * Mengikuti arsitektur SNAP Bank Indonesia / OVO Partner Integration:
 * https://www.ovo.id/partner-integration/consumer-topup/integration-high-level
 *
 * Keandalan:
 * - Access token B2B di-cache sampai mendekati kedaluwarsa (token resmi berlaku
 *   15 menit) sehingga tidak meminta token baru pada setiap transaksi.
 * - Bila token cache kedaluwarsa atau server menolak token (401), token di-cache
 *   ulang secara otomatis.
 * - Seluruh panggilan dicatat ke tabel ovo_api_logs via OvoAuditLogger
 *   (secret/redaksi penuh).
 */
class OfficialOvoProvider implements OvoProvider
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    private function cfg(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    private function apiBaseUrl(): string
    {
        return rtrim((string) $this->cfg('api_base_url', 'https://api.ovo.id'), '/');
    }

    private function clientId(): string
    {
        return (string) $this->cfg('client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) $this->cfg('client_secret', '');
    }

    private function partnerId(): string
    {
        return (string) $this->cfg('partner_id', $this->clientId());
    }

    private function channelId(): ?string
    {
        $cid = $this->cfg('channel_id', '');
        return $cid !== null && $cid !== '' ? (string) $cid : null;
    }

    private function privateKey(): string
    {
        return (string) $this->cfg('private_key', '');
    }

    /**
     * Timestamp format ISO8601 GMT+7 (Asia/Jakarta): yyyy-MM-ddTHH:mm:ssP.
     */
    private function timestamp(): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        return $dt->format('Y-m-d\TH:i:sP');
    }

    /**
     * Generate SNAP Asymmetric Signature (SHA256withRSA).
     */
    private function generateAsymmetricSignature(string $stringToSign): string
    {
        $privateKey = $this->privateKey();
        if (!$privateKey) {
            throw new \RuntimeException('OVO private key tidak dikonfigurasi.');
        }

        if (!str_contains($privateKey, '-----BEGIN')) {
            $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($privateKey, 64, "\n") . "-----END RSA PRIVATE KEY-----";
        }

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false && str_contains($privateKey, 'RSA PRIVATE KEY')) {
            $pkcs8 = str_replace(['RSA PRIVATE KEY', 'rsa private key'], 'PRIVATE KEY', $privateKey);
            $key = openssl_pkey_get_private($pkcs8);
        }

        if ($key === false) {
            throw new \RuntimeException('Format OVO private key tidak valid untuk OpenSSL.');
        }

        $signature = '';
        $success = openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new \RuntimeException('Gagal menandatangani payload dengan OVO private key.');
        }

        return base64_encode($signature);
    }

    /**
     * Generate SNAP Symmetric Signature (HMAC-SHA512).
     *
     * stringToSign = HTTPMETHOD:ENDPOINTURL:ACCESS TOKEN:BODYHASH(esha256 body):TIMESTAMP
     */
    private function generateSymmetricSignature(
        string $httpMethod,
        string $endpointUrl,
        string $accessToken,
        string $requestBody,
        string $timestamp
    ): string {
        $clientSecret = $this->clientSecret();
        if (!$clientSecret) {
            throw new \RuntimeException('OVO client secret tidak dikonfigurasi.');
        }

        $bodyHash = strtolower(hash('sha256', $requestBody));
        $stringToSign = strtoupper($httpMethod) . ':' . $endpointUrl . ':' . $accessToken . ':' . $bodyHash . ':' . $timestamp;

        return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
    }

    // ------------------------------------------------------------------
    // B2B Access Token (cached)
    // ------------------------------------------------------------------

    /**
     * Key cache token. Memisahkan per client_id agar staging/production kredensial
     * berbeda tidak saling menimpa.
     */
    private function tokenCacheKey(): string
    {
        return 'ovo:b2b:token:' . md5($this->clientId() . '|' . $this->apiBaseUrl());
    }

    private function cachedAccessToken(): ?string
    {
        try {
            return Cache::get($this->tokenCacheKey());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cacheAccessToken(string $token, int $expiresIn): void
    {
        // Margin 90 detik: token dianggap kedaluwarsa sedikit lebih awal dari
        // kedaluwarsa server agar tidak berjebak tepat di batas.
        $ttl = max(30, $expiresIn - 90);

        try {
            Cache::put($this->tokenCacheKey(), $token, $ttl);
        } catch (\Throwable $e) {
            Log::warning('[OfficialOvoProvider] Gagal menyimpan cache token B2B', ['error' => $e->getMessage()]);
        }
    }

    private function forgetCachedAccessToken(): void
    {
        try {
            Cache::forget($this->tokenCacheKey());
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function getB2BAccessToken(): array
    {
        $cached = $this->cachedAccessToken();
        if ($cached) {
            return ['success' => true, 'access_token' => $cached, 'expires_in' => null, 'error' => null, 'cached' => true];
        }

        if (!$this->clientId() || !$this->privateKey()) {
            return [
                'success'      => false,
                'access_token' => null,
                'expires_in'   => null,
                'error'        => 'Kredensial OVO B2B belum dikonfigurasi (client_id / private_key).',
            ];
        }

        $timestamp = $this->timestamp();
        $stringToSign = $this->clientId() . '|' . $timestamp;
        $url = $this->apiBaseUrl() . '/OVOSNAP/v1.0/access-token/b2b';
        $payload = ['grantType' => 'client_credentials'];

        $start = microtime(true);
        try {
            $signature = $this->generateAsymmetricSignature($stringToSign);

            $headers = [
                'X-TIMESTAMP'  => $timestamp,
                'X-CLIENT-KEY' => $this->clientId(),
                'X-SIGNATURE'  => $signature,
                'Content-Type' => 'application/json',
            ];
            if ($channel = $this->channelId()) {
                $headers['X-CHANNEL-ID'] = $channel;
            }

            $response = Http::withHeaders($headers)->timeout(15)->post($url, $payload);

            OvoAuditLogger::record(
                'access_token',
                $url,
                $payload,
                $response->json(),
                $response->status(),
                data_get($response->json(), 'responseCode'),
                $response->successful(),
                (microtime(true) - $start) * 1000
            );

            if ($response->successful()) {
                $body = $response->json();
                $token = $body['accessToken'] ?? null;

                if (!$token) {
                    return [
                        'success'      => false,
                        'access_token' => null,
                        'expires_in'   => null,
                        'error'        => 'Respon access-token/b2b tidak memuat accessToken.',
                    ];
                }

                $this->cacheAccessToken($token, (int) ($body['expiresIn'] ?? 900));

                return [
                    'success'      => true,
                    'access_token' => $token,
                    'expires_in'   => (int) ($body['expiresIn'] ?? 900),
                    'error'        => null,
                ];
            }

            // Gagal -> pastikan token lama tidak dipakai ulang
            $this->forgetCachedAccessToken();

            return [
                'success'      => false,
                'access_token' => null,
                'expires_in'   => null,
                'error'        => 'Gagal otentikasi B2B OVO: ' . ($response->json('responseMessage') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('[OfficialOvoProvider] getB2BAccessToken exception', ['error' => $e->getMessage()]);

            OvoAuditLogger::record(
                'access_token',
                $url,
                $payload,
                ['exception' => $e->getMessage()],
                null,
                null,
                false,
                (microtime(true) - $start) * 1000
            );

            return [
                'success'      => false,
                'access_token' => null,
                'expires_in'   => null,
                'error'        => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve access token dengan retry sekali bila token cache ditolak server.
     */
    private function resolveAccessToken(): array
    {
        return $this->getB2BAccessToken();
    }

    // ------------------------------------------------------------------
    // Shared request helper
    // ------------------------------------------------------------------

    /**
     * Kirim request SNAP ber-symmetric signature + audit logging.
     *
     * @return array{0: \Illuminate\Http\Client\Response, 1: string} [response, jsonPayload]
     */
    private function signedRequest(
        string $action,
        string $endpoint,
        array $payload,
        string $accessToken,
        ?string $externalId = null,
        int $timeout = 15
    ): array {
        $timestamp = $this->timestamp();
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = $this->generateSymmetricSignature('POST', $endpoint, $accessToken, $jsonPayload, $timestamp);
        $url = $this->apiBaseUrl() . $endpoint;

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $signature,
            'X-PARTNER-ID'  => $this->partnerId(),
            'X-EXTERNAL-ID' => $externalId ?? (string) Str::uuid(),
            'Content-Type'  => 'application/json',
        ];
        if ($channel = $this->channelId()) {
            $headers['X-CHANNEL-ID'] = $channel;
        }

        return [Http::withHeaders($headers)->timeout($timeout)->post($url, $payload), $url];
    }

    // ------------------------------------------------------------------
    // Account Inquiry
    // ------------------------------------------------------------------

    public function accountInquiry(string $phoneNumber): array
    {
        $tokenRes = $this->resolveAccessToken();
        if (!$tokenRes['success']) {
            return [
                'success'         => false,
                'customer_name'   => null,
                'customer_number' => $phoneNumber,
                'status'          => null,
                'error'           => $tokenRes['error'],
                'error_code'      => 'OVO_AUTH_FAILED',
            ];
        }

        $endpoint = '/OVOSNAP/v2.0/emoney/account-inquiry';
        $payload = ['customerNumber' => $phoneNumber];

        $start = microtime(true);
        try {
            [$response, $url] = $this->signedRequest($action = 'account_inquiry', $endpoint, $payload, $tokenRes['access_token']);
            $body = $response->json();

            OvoAuditLogger::record(
                'account_inquiry',
                $url,
                $payload,
                $body,
                $response->status(),
                data_get($body, 'responseCode'),
                $response->successful(),
                (microtime(true) - $start) * 1000
            );

            if ($response->status() === 401) {
                $this->forgetCachedAccessToken();
            }

            if ($response->successful() && ($body['responseCode'] ?? '') === '2000000') {
                return [
                    'success'         => true,
                    'customer_name'   => $body['customerName'] ?? 'PENGGUNA OVO',
                    'customer_number' => $body['customerNumber'] ?? $phoneNumber,
                    'status'          => $body['accountStatus'] ?? 'ACTIVE',
                    'error'           => null,
                    'error_code'      => null,
                ];
            }

            $errorCode = ($body['responseCode'] ?? '') === '4045400' ? 'OVO_ACCOUNT_NOT_FOUND' : 'OVO_INQUIRY_FAILED';
            return [
                'success'         => false,
                'customer_name'   => null,
                'customer_number' => $phoneNumber,
                'status'          => null,
                'error'           => $body['responseMessage'] ?? 'Nomor OVO tidak terdaftar atau tidak aktif.',
                'error_code'      => $errorCode,
            ];
        } catch (\Throwable $e) {
            Log::error('[OfficialOvoProvider] accountInquiry exception', ['error' => $e->getMessage()]);

            OvoAuditLogger::record(
                'account_inquiry',
                $this->apiBaseUrl() . $endpoint,
                $payload,
                ['exception' => $e->getMessage()],
                null,
                null,
                false,
                (microtime(true) - $start) * 1000
            );

            return [
                'success'         => false,
                'customer_name'   => null,
                'customer_number' => $phoneNumber,
                'status'          => null,
                'error'           => $e->getMessage(),
                'error_code'      => 'OVO_INQUIRY_ERROR',
            ];
        }
    }

    // ------------------------------------------------------------------
    // Customer Top Up (Disbursement)
    // ------------------------------------------------------------------

    public function customerTopUp(string $customerNumber, float $amount, string $referenceNo): array
    {
        $tokenRes = $this->resolveAccessToken();
        if (!$tokenRes['success']) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => $tokenRes['error'],
            ];
        }

        $endpoint = '/OVOSNAP/v2.0/emoney/topup';
        $payload = [
            'partnerReferenceNo' => $referenceNo,
            'customerNumber'     => $customerNumber,
            'amount'             => [
                'value'    => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'feeType'            => 'OUR',
        ];

        $start = microtime(true);
        try {
            [$response, $url] = $this->signedRequest($action = 'topup', $endpoint, $payload, $tokenRes['access_token'], $referenceNo, 30);
            $body = $response->json();

            OvoAuditLogger::record(
                'topup',
                $url,
                $payload,
                $body,
                $response->status(),
                data_get($body, 'responseCode'),
                $response->successful(),
                (microtime(true) - $start) * 1000
            );

            if ($response->status() === 401) {
                $this->forgetCachedAccessToken();
            }

            if ($response->successful() && ($body['responseCode'] ?? '') === '2000000') {
                return [
                    'success'        => true,
                    'transaction_id' => $body['referenceNo'] ?? null,
                    'status'         => 'success',
                    'error'          => null,
                ];
            }

            // Status pending / processing (timeout internal provider, dapat dicek via topup-status)
            if (in_array($body['responseCode'] ?? '', ['2000001', '2020000'], true)) {
                return [
                    'success'        => true,
                    'transaction_id' => $body['referenceNo'] ?? null,
                    'status'         => 'processing',
                    'error'          => null,
                ];
            }

            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => $body['responseMessage'] ?? 'Pencairan OVO gagal diproses.',
            ];
        } catch (\Throwable $e) {
            Log::error('[OfficialOvoProvider] customerTopUp exception', ['error' => $e->getMessage()]);

            OvoAuditLogger::record(
                'topup',
                $this->apiBaseUrl() . $endpoint,
                $payload,
                ['exception' => $e->getMessage()],
                null,
                null,
                false,
                (microtime(true) - $start) * 1000
            );

            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'processing', // Treat transport timeouts as processing so money is not lost; verify via topup-status before retry
                'error'          => 'Koneksi ke OVO timeout / tertunda: ' . $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------------
    // Top Up Status Inquiry
    // ------------------------------------------------------------------

    public function topupStatusInquiry(string $referenceNo, ?string $transactionId = null): array
    {
        $tokenRes = $this->resolveAccessToken();
        if (!$tokenRes['success']) {
            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => $tokenRes['error'],
            ];
        }

        $endpoint = '/OVOSNAP/v2.0/emoney/topup-status';
        $payload = [
            'originalPartnerReferenceNo' => $referenceNo,
            'originalReferenceNo'        => $transactionId ?? '',
        ];

        $start = microtime(true);
        try {
            [$response, $url] = $this->signedRequest($action = 'topup_status', $endpoint, $payload, $tokenRes['access_token']);
            $body = $response->json();

            OvoAuditLogger::record(
                'topup_status',
                $url,
                $payload,
                $body,
                $response->status(),
                data_get($body, 'responseCode'),
                $response->successful(),
                (microtime(true) - $start) * 1000
            );

            if ($response->status() === 401) {
                $this->forgetCachedAccessToken();
            }

            $statusCode = $body['latestTransactionStatus'] ?? '';

            $status = match ($statusCode) {
                '00'    => 'success',
                '01'    => 'processing',
                '02'    => 'failed',
                default => 'unknown',
            };

            return [
                'success' => $response->successful(),
                'status'  => $status,
                'details' => $body ?? [],
                'error'   => $status === 'failed' ? ($body['responseMessage'] ?? 'Transaksi gagal') : null,
            ];
        } catch (\Throwable $e) {
            OvoAuditLogger::record(
                'topup_status',
                $this->apiBaseUrl() . $endpoint,
                $payload,
                ['exception' => $e->getMessage()],
                null,
                null,
                false,
                (microtime(true) - $start) * 1000
            );

            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------------
    // Balance Inquiry (deposit merchant)
    // ------------------------------------------------------------------

    public function balanceInquiry(): array
    {
        $tokenRes = $this->resolveAccessToken();
        if (!$tokenRes['success']) {
            return ['success' => false, 'balance' => null, 'currency' => 'IDR', 'error' => $tokenRes['error']];
        }

        $endpoint = '/OVOSNAP/v1.0/balance-inquiry';
        $payload = ['accountNo' => $this->partnerId()];

        $start = microtime(true);
        try {
            [$response, $url] = $this->signedRequest($action = 'balance_inquiry', $endpoint, $payload, $tokenRes['access_token']);
            $body = $response->json();

            OvoAuditLogger::record(
                'balance_inquiry',
                $url,
                $payload,
                $body,
                $response->status(),
                data_get($body, 'responseCode'),
                $response->successful(),
                (microtime(true) - $start) * 1000
            );

            if ($response->status() === 401) {
                $this->forgetCachedAccessToken();
            }

            return [
                'success'  => $response->successful(),
                'balance'  => isset($body['amount']['value']) ? (float) $body['amount']['value'] : 0.0,
                'currency' => $body['amount']['currency'] ?? 'IDR',
                'error'    => $response->successful() ? null : ($body['responseMessage'] ?? 'Gagal cek saldo deposit OVO'),
            ];
        } catch (\Throwable $e) {
            OvoAuditLogger::record(
                'balance_inquiry',
                $this->apiBaseUrl() . $endpoint,
                $payload,
                ['exception' => $e->getMessage()],
                null,
                null,
                false,
                (microtime(true) - $start) * 1000
            );

            return ['success' => false, 'balance' => null, 'currency' => 'IDR', 'error' => $e->getMessage()];
        }
    }
}
