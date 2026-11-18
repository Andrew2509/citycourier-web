<?php

namespace App\Modules\Payout\Services;

use App\Models\Setting;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cek nama pemilik rekening Bank & akun E-Wallet asli melalui API Kirim DOKU (SNAP).
 *
 * Alur resmi DOKU SNAP:
 * 1. Request B2B Access Token: POST /authorization/v1/access-token/b2b
 *    Header: X-CLIENT-KEY, X-TIMESTAMP, X-SIGNATURE (RSA-SHA256 clientId|timestamp)
 * 2. Cek Nama Tujuan: POST /snap/v1.1/emoney/bank-account-inquiry
 *    Header: Authorization: Bearer <token>, X-PARTNER-ID, X-EXTERNAL-ID,
 *            X-TIMESTAMP, X-SIGNATURE (HMAC-SHA512 Base64), CHANNEL-ID
 */
class DokuKirimAccountInquiryService
{
    public const PROVIDER_CODE = 'doku_kirim';

    /**
     * Pemetaan kode kanal internal ke kode Bank / E-Wallet resmi Kirim DOKU (Channel 07).
     *
     * @var array<string, string>
     */
    public const CHANNEL_CODES = [
        // Bank domestik Indonesia
        'ID_BCA' => '014',
        'ID_BRI' => '002',
        'ID_BNI' => '009',
        'ID_MANDIRI' => '008',
        'ID_PERMATA' => '013',
        'ID_CIMB' => '022',
        'ID_BSI' => '451',
        // E-Wallet Indonesia (Channel 07)
        'ID_DANA' => 'DANA',
        'ID_OVO' => 'OVO',
        'ID_GOPAY' => 'GOPAY',
        'ID_SHOPEEPAY' => 'SHOPEEPAY',
        'ID_LINKAJA' => '911',
    ];

    public function __construct(
        private readonly GatewayCredentialsResolver $credentialsResolver
    ) {}

    public function isAvailable(): bool
    {
        $creds = $this->credentialsResolver->doku();

        return (bool) ($creds['enabled'] ?? false)
            && (bool) ($creds['kirim_inquiry_enabled'] ?? false)
            && (bool) ($creds['kirim_configured'] ?? false);
    }

    /**
     * Cek nama asli rekening bank atau akun e-wallet via Kirim DOKU SNAP.
     *
     * @return array{
     *   supported: bool,
     *   found: bool,
     *   account_holder_name: ?string,
     *   provider: string,
     *   message: ?string,
     *   response_code?: ?string,
     *   raw?: mixed
     * }
     */
    public function inquire(
        string $destinationType,
        string $channelCode,
        string $accountNumber,
        ?string $customerPhone = null,
        ?array $override = null
    ): array {
        $cfg = $this->resolveConfig($override);
        if (! $cfg['enabled']) {
            return $this->unavailable('Layanan DOKU sedang dinonaktifkan oleh Admin.');
        }

        if (! $cfg['kirim_inquiry_enabled']) {
            return $this->unavailable('Fitur cek nama Kirim DOKU belum diaktifkan di Admin.');
        }

        if ($cfg['client_id'] === '' || $cfg['secret_key'] === '' || $cfg['private_key'] === '') {
            return $this->unavailable(
                'Kredensial Kirim DOKU (Client-Id, Secret Key, dan Private Key RSA) belum lengkap di Admin.'
            );
        }

        $normalizedChannel = strtoupper(trim($channelCode));
        if (! str_starts_with($normalizedChannel, 'ID_')) {
            $normalizedChannel = 'ID_'.$normalizedChannel;
        }

        $bankCode = self::CHANNEL_CODES[$normalizedChannel] ?? null;
        if ($bankCode === null) {
            return $this->unavailable("Kanal {$normalizedChannel} belum didukung oleh pemetaan Kirim DOKU.");
        }

        $cleanAccount = $this->normalizeBeneficiaryNumber($destinationType, $accountNumber);
        if ($cleanAccount === '') {
            return $this->unavailable('Nomor rekening atau e-wallet tidak valid.');
        }

        $customerNumber = $this->normalizeCustomerNumber($customerPhone ?: $cfg['customer_number']);
        $inquiryPath = (string) config('payment_wallet.doku.kirim_inquiry_path', '/snap/v1.1/emoney/bank-account-inquiry');

        $tokenResult = $this->getB2BAccessToken($cfg);
        if (! $tokenResult['success'] || empty($tokenResult['access_token'])) {
            return $this->unavailable($tokenResult['message'] ?? 'Gagal mendapatkan token B2B Kirim DOKU.');
        }

        $response = $this->sendInquiryRequest(
            $cfg,
            $inquiryPath,
            (string) $tokenResult['access_token'],
            $customerNumber,
            $cleanAccount,
            $bankCode
        );

        // Bila token ditolak sebelum TTL lokal habis, paksa muat ulang 1 kali.
        if (($response['http_status'] ?? 0) === 401) {
            $this->forgetTokenCache($cfg);
            $freshToken = $this->getB2BAccessToken($cfg, true);
            if ($freshToken['success'] && ! empty($freshToken['access_token'])) {
                $response = $this->sendInquiryRequest(
                    $cfg,
                    $inquiryPath,
                    (string) $freshToken['access_token'],
                    $customerNumber,
                    $cleanAccount,
                    $bankCode
                );
            }
        }

        return $this->interpretInquiryResponse($response);
    }

    /**
     * Uji kredensial Kirim DOKU SNAP (Get B2B Access Token) dari form Admin sebelum disimpan.
     *
     * @return array{success:bool, message:string, mode?:string}
     */
    public function probeCredentials(array $override = []): array
    {
        $cfg = $this->resolveConfig($override);
        if ($cfg['client_id'] === '' || $cfg['secret_key'] === '' || $cfg['private_key'] === '') {
            return [
                'success' => false,
                'message' => 'Private Key RSA Kirim DOKU belum diisi.',
            ];
        }

        $res = $this->getB2BAccessToken($cfg, true);
        if (! $res['success']) {
            return [
                'success' => false,
                'message' => (string) ($res['message'] ?? 'Token B2B Kirim DOKU ditolak.'),
                'mode' => $cfg['mode'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Token B2B Kirim DOKU SNAP aktif (RSA-SHA256 terverifikasi).',
            'mode' => $cfg['mode'],
        ];
    }

    public function forgetTokenCache(?array $override = null): void
    {
        $cfg = $this->resolveConfig($override);
        try {
            Cache::forget($this->tokenCacheKey($cfg));
        } catch (\Throwable) {
            // Abaikan kegagalan backend cache.
        }
    }

    /**
     * Buat pasangan kunci RSA-2048 PEM baru untuk pengaturan Kirim DOKU SNAP Admin.
     *
     * @return array{private_key:string, public_key:string}
     */
    public static function generateRsaKeyPair(): array
    {
        $res = @openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($res === false) {
            throw new RuntimeException('OpenSSL tidak dapat membuat keypair RSA pada server ini.');
        }

        $privateKey = '';
        if (! @openssl_pkey_export($res, $privateKey) || trim($privateKey) === '') {
            throw new RuntimeException('Gagal mengekspor Private Key RSA.');
        }

        $details = @openssl_pkey_get_details($res);
        $publicKey = is_array($details) ? trim((string) ($details['key'] ?? '')) : '';
        if ($publicKey === '') {
            throw new RuntimeException('Gagal mengekstrak Public Key RSA.');
        }

        return [
            'private_key' => trim($privateKey),
            'public_key' => $publicKey,
        ];
    }

    /**
     * Bangun Asymmetric Signature SHA256withRSA untuk endpoint access-token/b2b.
     */
    public function generateAsymmetricSignature(string $clientId, string $timestamp, string $privateKeyPem): string
    {
        $pem = str_replace('\n', "\n", trim($privateKeyPem));
        if ($pem === '') {
            throw new RuntimeException('DOKU Private Key belum diisi.');
        }

        if (! str_contains($pem, '-----BEGIN')) {
            $pem = "-----BEGIN RSA PRIVATE KEY-----\n"
                .chunk_split($pem, 64, "\n")
                ."-----END RSA PRIVATE KEY-----";
        }

        $key = @openssl_pkey_get_private($pem);
        if ($key === false && str_contains($pem, 'RSA PRIVATE KEY')) {
            $pkcs8 = str_replace(['RSA PRIVATE KEY', 'rsa private key'], 'PRIVATE KEY', $pem);
            $key = @openssl_pkey_get_private($pkcs8);
        }

        if ($key === false) {
            throw new RuntimeException('Format DOKU Private Key RSA tidak valid.');
        }

        $signature = '';
        $stringToSign = $clientId.'|'.$timestamp;
        if (! @openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani permintaan B2B Token DOKU.');
        }

        return base64_encode($signature);
    }

    /**
     * Bangun Symmetric Signature HMAC-SHA512 Base64 sesuai spesifikasi DOKU SNAP.
     */
    public function generateSymmetricSignature(
        string $httpMethod,
        string $endpointPath,
        string $accessToken,
        string $minifiedBody,
        string $timestamp,
        string $secretKey
    ): string {
        $bodyHash = strtolower(hash('sha256', $minifiedBody));
        $stringToSign = implode(':', [
            strtoupper($httpMethod),
            $endpointPath,
            $accessToken,
            $bodyHash,
            $timestamp,
        ]);

        return base64_encode(hash_hmac('sha512', $stringToSign, $secretKey, true));
    }

    /**
     * @return array{
     *   enabled:bool, kirim_inquiry_enabled:bool, mode:string, base_url:string,
     *   client_id:string, secret_key:string, private_key:string, channel_id:string,
     *   customer_number:string, timeout:int
     * }
     */
    private function resolveConfig(?array $override): array
    {
        $creds = $this->credentialsResolver->doku();
        $mode = Schema::hasTable('settings')
            ? ((string) (Setting::get('payment_wallet_mode') ?: config('payment_wallet.mode', 'sandbox')))
            : (string) config('payment_wallet.mode', 'sandbox');
        $mode = in_array($mode, ['sandbox', 'production'], true) ? $mode : 'sandbox';

        $baseUrl = $mode === 'production'
            ? (string) ($override['production_url'] ?? $creds['production_url'])
            : (string) ($override['sandbox_url'] ?? $creds['sandbox_url']);

        return [
            'enabled' => (bool) ($override['enabled'] ?? $creds['enabled']),
            'kirim_inquiry_enabled' => (bool) ($override['kirim_inquiry_enabled'] ?? $creds['kirim_inquiry_enabled']),
            'mode' => $mode,
            'base_url' => rtrim($baseUrl, '/'),
            'client_id' => trim((string) ($override['client_id'] ?? $creds['client_id'])),
            'secret_key' => trim((string) ($override['secret_key'] ?? $creds['secret_key'])),
            'private_key' => trim((string) ($override['private_key'] ?? $creds['private_key'])),
            'channel_id' => trim((string) ($override['channel_id'] ?? $creds['kirim_channel_id'])) ?: 'H2H',
            'customer_number' => trim((string) ($override['customer_number'] ?? $creds['kirim_customer_number'])) ?: '6281234567890',
            'timeout' => (int) ($override['timeout_seconds'] ?? $creds['timeout_seconds'] ?? 20),
        ];
    }

    /**
     * @return array{success:bool, access_token:?string, message:?string, response?:mixed}
     */
    private function getB2BAccessToken(array $cfg, bool $forceRefresh = false): array
    {
        $cacheKey = $this->tokenCacheKey($cfg);
        if (! $forceRefresh) {
            try {
                $cached = Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    return ['success' => true, 'access_token' => $cached, 'message' => null];
                }
            } catch (\Throwable) {
                // Abaikan error membaca cache.
            }
        }

        $tokenPath = (string) config('payment_wallet.doku.kirim_token_path', '/authorization/v1/access-token/b2b');
        $timestamp = now('Asia/Jakarta')->format('Y-m-d\TH:i:sP');

        try {
            $signature = $this->generateAsymmetricSignature($cfg['client_id'], $timestamp, $cfg['private_key']);
        } catch (RuntimeException $e) {
            return ['success' => false, 'access_token' => null, 'message' => $e->getMessage()];
        }

        try {
            $response = Http::timeout($cfg['timeout'])
                ->withHeaders([
                    'X-CLIENT-KEY' => $cfg['client_id'],
                    'X-TIMESTAMP' => $timestamp,
                    'X-SIGNATURE' => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->post($cfg['base_url'].$tokenPath, [
                    'grantType' => 'client_credentials',
                ]);

            $json = $response->json();
            $token = is_array($json) ? ($json['accessToken'] ?? null) : null;

            if ($response->successful() && is_string($token) && $token !== '') {
                $ttl = max(30, (int) ($json['expiresIn'] ?? 900) - 90);
                try {
                    Cache::put($cacheKey, $token, $ttl);
                } catch (\Throwable) {
                    // Abaikan error penyimpanan cache token.
                }

                return ['success' => true, 'access_token' => $token, 'message' => null, 'response' => $json];
            }

            $errMsg = is_array($json)
                ? (string) ($json['responseMessage'] ?? $json['error_description'] ?? $json['message'] ?? '')
                : '';

            Log::warning('[DokuKirimInquiry] Gagal memperoleh token B2B SNAP', [
                'status' => $response->status(),
                'response_code' => is_array($json) ? ($json['responseCode'] ?? null) : null,
                'message' => $errMsg,
            ]);

            return [
                'success' => false,
                'access_token' => null,
                'message' => 'Token B2B DOKU ditolak (HTTP '.$response->status().')'
                    .($errMsg !== '' ? ': '.$errMsg : '. Pastikan Public Key sudah diunggah ke DOKU Dashboard.'),
                'response' => $json,
            ];
        } catch (\Throwable $e) {
            Log::warning('[DokuKirimInquiry] Exception saat mengambil token B2B', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'access_token' => null,
                'message' => 'Gagal menghubungi DOKU B2B Token: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok:bool, http_status:int, json:?array, error:?string}
     */
    private function sendInquiryRequest(
        array $cfg,
        string $inquiryPath,
        string $accessToken,
        string $customerNumber,
        string $beneficiaryNumber,
        string $bankCode
    ): array {
        $timestamp = now('Asia/Jakarta')->format('Y-m-d\TH:i:sP');
        $externalId = now('Asia/Jakarta')->format('YmdHis').random_int(100000, 999999);

        $payload = [
            'partnerReferenceNo' => 'KDINQ'.now('Asia/Jakarta')->format('YmdHis').Str::upper(Str::random(6)),
            'customerNumber' => $customerNumber,
            'amount' => [
                'value' => '10000.00',
                'currency' => 'IDR',
            ],
            'beneficiaryAccountNumber' => $beneficiaryNumber,
            'additionalInfo' => [
                'beneficiaryBankCode' => $bankCode,
                'senderCountryCode' => 'ID',
            ],
        ];

        $minifiedBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = $this->generateSymmetricSignature(
            'POST',
            $inquiryPath,
            $accessToken,
            $minifiedBody,
            $timestamp,
            $cfg['secret_key']
        );

        try {
            $response = Http::timeout($cfg['timeout'])
                ->withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'X-PARTNER-ID' => $cfg['client_id'],
                    'X-EXTERNAL-ID' => $externalId,
                    'X-TIMESTAMP' => $timestamp,
                    'X-SIGNATURE' => $signature,
                    'CHANNEL-ID' => $cfg['channel_id'],
                    'Content-Type' => 'application/json',
                ])
                ->withBody($minifiedBody, 'application/json')
                ->post($cfg['base_url'].$inquiryPath);

            $json = $response->json();

            return [
                'ok' => $response->successful(),
                'http_status' => $response->status(),
                'json' => is_array($json) ? $json : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('[DokuKirimInquiry] Gagal memanggil bank-account-inquiry', [
                'bank_code' => $bankCode,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'http_status' => 0,
                'json' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function interpretInquiryResponse(array $resp): array
    {
        $status = (int) ($resp['http_status'] ?? 0);
        $json = $resp['json'] ?? [];
        if ($status === 0 || ! is_array($json)) {
            return $this->unavailable(
                'Gagal menghubungi API cek nama Kirim DOKU'
                .(! empty($resp['error']) ? ': '.$resp['error'] : '.')
            );
        }

        $respCode = (string) ($json['responseCode'] ?? '');
        $respMsg = trim((string) ($json['responseMessage'] ?? ''));
        $holder = trim((string) ($json['beneficiaryAccountName'] ?? ''));

        if ($resp['ok'] && ($respCode === '2004200' || str_starts_with($respCode, '200')) && $holder !== '') {
            return [
                'supported' => true,
                'found' => true,
                'account_holder_name' => strtoupper($holder),
                'provider' => self::PROVIDER_CODE,
                'message' => 'Nama pemilik rekening/akun terverifikasi melalui Kirim DOKU.',
                'response_code' => $respCode,
                'raw' => $json,
            ];
        }

        // Bedakan akun tujuan benar-benar tidak ditemukan vs error autentikasi/akses API.
        $lowerMsg = strtolower($respMsg);
        $isNotFound = str_starts_with($respCode, '40442')
            || str_starts_with($respCode, '40042')
            || str_contains($lowerMsg, 'not found')
            || str_contains($lowerMsg, 'invalid account')
            || str_contains($lowerMsg, 'invalid card')
            || str_contains($lowerMsg, 'account not')
            || str_contains($lowerMsg, 'tidak ditemukan');

        if ($isNotFound) {
            return [
                'supported' => true,
                'found' => false,
                'account_holder_name' => null,
                'provider' => self::PROVIDER_CODE,
                'message' => 'Nomor rekening atau akun e-wallet tidak ditemukan di bank/provider tujuan.'
                    .($respMsg !== '' ? " ({$respMsg})" : ''),
                'response_code' => $respCode,
            ];
        }

        Log::warning('[DokuKirimInquiry] Respons Kirim DOKU tidak dapat memverifikasi nama', [
            'http_status' => $status,
            'response_code' => $respCode,
            'response_message' => $respMsg,
        ]);

        return $this->unavailable(
            'Cek nama Kirim DOKU gagal (HTTP '.$status.')'
            .($respMsg !== '' ? ": {$respMsg}" : '.')
        );
    }

    private function normalizeBeneficiaryNumber(string $destType, string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strtolower($destType) === 'ewallet' && str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function normalizeCustomerNumber(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return '6281234567890';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    private function tokenCacheKey(array $cfg): string
    {
        return 'doku:kirim:b2b:token:'.md5($cfg['client_id'].'|'.$cfg['base_url']);
    }

    private function unavailable(string $message): array
    {
        return [
            'supported' => false,
            'found' => false,
            'account_holder_name' => null,
            'provider' => self::PROVIDER_CODE,
            'message' => $message,
        ];
    }
}
