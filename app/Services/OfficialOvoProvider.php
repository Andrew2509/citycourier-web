<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Official OVO Provider — Real OVO SNAP B2B API.
 *
 * Mengikuti arsitektur SNAP Bank Indonesia / OVO Partner Integration:
 * https://www.ovo.id/partner-integration/consumer-topup/integration-high-level
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

    private function privateKey(): string
    {
        return (string) $this->cfg('private_key', '');
    }

    /**
     * Timestamp format ISO8601 GMT+7 (Asia/Jakarta).
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
     */
    private function generateSymmetricSignature(string $httpMethod, string $endpointUrl, string $accessToken, string $requestBody, string $timestamp): string
    {
        $bodyHash = strtolower(hash('sha256', $requestBody));
        $stringToSign = strtoupper($httpMethod) . ':' . $endpointUrl . ':' . $accessToken . ':' . $bodyHash . ':' . $timestamp;

        return base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret(), true));
    }

    public function getB2BAccessToken(): array
    {
        $timestamp = $this->timestamp();
        $stringToSign = $this->clientId() . '|' . $timestamp;

        try {
            $signature = $this->generateAsymmetricSignature($stringToSign);

            $url = $this->apiBaseUrl() . '/OVOSNAP/v1.0/access-token/b2b';
            $response = Http::withHeaders([
                'X-TIMESTAMP'  => $timestamp,
                'X-CLIENT-KEY' => $this->clientId(),
                'X-SIGNATURE'  => $signature,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, [
                'grantType' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $body = $response->json();
                return [
                    'success'      => true,
                    'access_token' => $body['accessToken'] ?? null,
                    'expires_in'   => $body['expiresIn'] ?? 900,
                    'error'        => null,
                ];
            }

            return [
                'success'      => false,
                'access_token' => null,
                'expires_in'   => null,
                'error'        => 'Gagal otentikasi B2B OVO: ' . ($response->json('responseMessage') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('[OfficialOvoProvider] getB2BAccessToken exception', ['error' => $e->getMessage()]);
            return [
                'success'      => false,
                'access_token' => null,
                'expires_in'   => null,
                'error'        => $e->getMessage(),
            ];
        }
    }

    public function accountInquiry(string $phoneNumber): array
    {
        $tokenRes = $this->getB2BAccessToken();
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
        $timestamp = $this->timestamp();
        $payload = [
            'customerNumber' => $phoneNumber,
        ];
        $jsonPayload = json_encode($payload);

        try {
            $signature = $this->generateSymmetricSignature('POST', $endpoint, $tokenRes['access_token'], $jsonPayload, $timestamp);

            $url = $this->apiBaseUrl() . $endpoint;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenRes['access_token'],
                'X-TIMESTAMP'   => $timestamp,
                'X-SIGNATURE'   => $signature,
                'X-PARTNER-ID'  => $this->partnerId(),
                'X-EXTERNAL-ID' => (string) Str::uuid(),
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $body = $response->json();

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

    public function customerTopUp(string $customerNumber, float $amount, string $referenceNo): array
    {
        $tokenRes = $this->getB2BAccessToken();
        if (!$tokenRes['success']) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => $tokenRes['error'],
            ];
        }

        $endpoint = '/OVOSNAP/v2.0/emoney/topup';
        $timestamp = $this->timestamp();
        $payload = [
            'partnerReferenceNo' => $referenceNo,
            'customerNumber'     => $customerNumber,
            'amount'             => [
                'value'    => number_format($amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'feeType'            => 'OUR',
        ];
        $jsonPayload = json_encode($payload);

        try {
            $signature = $this->generateSymmetricSignature('POST', $endpoint, $tokenRes['access_token'], $jsonPayload, $timestamp);

            $url = $this->apiBaseUrl() . $endpoint;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenRes['access_token'],
                'X-TIMESTAMP'   => $timestamp,
                'X-SIGNATURE'   => $signature,
                'X-PARTNER-ID'  => $this->partnerId(),
                'X-EXTERNAL-ID' => $referenceNo,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post($url, $payload);

            $body = $response->json();

            if ($response->successful() && ($body['responseCode'] ?? '') === '2000000') {
                return [
                    'success'        => true,
                    'transaction_id' => $body['referenceNo'] ?? ('OVO-REF-' . strtoupper(Str::random(10))),
                    'status'         => 'success',
                    'error'          => null,
                ];
            }

            // Status pending / processing
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
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'processing', // Treat transport timeouts as processing so money is not lost
                'error'          => 'Koneksi ke OVO timeout / tertunda: ' . $e->getMessage(),
            ];
        }
    }

    public function topupStatusInquiry(string $referenceNo, ?string $transactionId = null): array
    {
        $tokenRes = $this->getB2BAccessToken();
        if (!$tokenRes['success']) {
            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => $tokenRes['error'],
            ];
        }

        $endpoint = '/OVOSNAP/v2.0/emoney/topup-status';
        $timestamp = $this->timestamp();
        $payload = [
            'originalPartnerReferenceNo' => $referenceNo,
            'originalReferenceNo'        => $transactionId ?? '',
        ];
        $jsonPayload = json_encode($payload);

        try {
            $signature = $this->generateSymmetricSignature('POST', $endpoint, $tokenRes['access_token'], $jsonPayload, $timestamp);

            $url = $this->apiBaseUrl() . $endpoint;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenRes['access_token'],
                'X-TIMESTAMP'   => $timestamp,
                'X-SIGNATURE'   => $signature,
                'X-PARTNER-ID'  => $this->partnerId(),
                'X-EXTERNAL-ID' => (string) Str::uuid(),
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $body = $response->json();
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
            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => $e->getMessage(),
            ];
        }
    }

    public function balanceInquiry(): array
    {
        $tokenRes = $this->getB2BAccessToken();
        if (!$tokenRes['success']) {
            return ['success' => false, 'balance' => null, 'currency' => 'IDR', 'error' => $tokenRes['error']];
        }

        $endpoint = '/OVOSNAP/v1.0/balance-inquiry';
        $timestamp = $this->timestamp();
        $payload = [
            'accountNo' => $this->partnerId(),
        ];
        $jsonPayload = json_encode($payload);

        try {
            $signature = $this->generateSymmetricSignature('POST', $endpoint, $tokenRes['access_token'], $jsonPayload, $timestamp);

            $url = $this->apiBaseUrl() . $endpoint;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tokenRes['access_token'],
                'X-TIMESTAMP'   => $timestamp,
                'X-SIGNATURE'   => $signature,
                'X-PARTNER-ID'  => $this->partnerId(),
                'X-EXTERNAL-ID' => (string) Str::uuid(),
                'Content-Type'  => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $body = $response->json();
            return [
                'success'  => $response->successful(),
                'balance'  => isset($body['amount']['value']) ? (float) $body['amount']['value'] : 0.0,
                'currency' => $body['amount']['currency'] ?? 'IDR',
                'error'    => $response->successful() ? null : ($body['responseMessage'] ?? 'Gagal cek saldo deposit OVO'),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'balance' => null, 'currency' => 'IDR', 'error' => $e->getMessage()];
        }
    }
}
