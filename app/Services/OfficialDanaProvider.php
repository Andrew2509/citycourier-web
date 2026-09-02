<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Official DANA Provider — real DANA Widget Binding API (SNAP asymmetric auth).
 *
 * Base reference:
 *  - Deeplink Binding: GET https://m.sandbox.dana.id/n/link/binding
 *  - Apply Token:      POST {base}/v1.0/access-token/b2b2c.htm
 *  - Query User Profile: /dana/member/query/queryUserProfile.htm
 *  - Balance Inquiry:  POST {base}/v1.0/balance-inquiry.htm
 *  - Unbinding:        POST {base}/v1.0/registration-account-unbinding.htm
 *
 * Credentials sourced from Setting (DB) via DanaService::providerConfig().
 */
class OfficialDanaProvider implements DanaProvider
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
        return rtrim((string) $this->cfg('api_base_url', 'https://api.sandbox.dana.id'), '/');
    }

    private function bindingUrl(): string
    {
        $env = $this->cfg('env', 'sandbox');
        return $env === 'production'
            ? 'https://m.dana.id/n/link/binding'
            : 'https://m.sandbox.dana.id/n/link/binding';
    }

    private function clientId(): string
    {
        return (string) $this->cfg('client_id', '');
    }

    private function merchantId(): string
    {
        return (string) $this->cfg('merchant_id', '');
    }

    private function privateKey(): string
    {
        return (string) $this->cfg('private_key', '');
    }

    private function redirectUrl(): string
    {
        return (string) $this->cfg('callback_url', '');
    }

    /**
     * Timestamp dalam format GMT+7 (Jakarta), e.g. 2026-09-02T10:00:00+07:00.
     */
    private function timestamp(): string
    {
        $dt = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        return $dt->format('Y-m-d\TH:i:sP');
    }

    /**
     * SHA256withRSA signature, base64 encoded.
     */
    private function sign(string $stringToSign): string
    {
        $privateKey = $this->normalizePrivateKey($this->privateKey());
        if (!$privateKey) {
            throw new \RuntimeException('DANA private key tidak dikonfigurasi.');
        }

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            Log::error('[OfficialDANA] private key gagal dimuat (metadata)', [
                'has_pem_header' => str_contains($this->privateKey(), '-----BEGIN'),
                'has_pem_end'    => str_contains($this->privateKey(), '-----END'),
                'single_line'    => !str_contains($this->privateKey(), "\n") && !str_contains($this->privateKey(), '\\n'),
                'literal_backslash_n' => str_contains($this->privateKey(), '\\n'),
                'length_chars'   => strlen($this->privateKey()),
                'head'           => substr(trim($this->privateKey()), 0, 40),
            ]);
            throw new \RuntimeException('DANA private key tidak valid. Pastikan key tersimpan dengan format PEM lengkap (-----BEGIN PRIVATE KEY----- ... -----END PRIVATE KEY-----).');
        }

        openssl_sign($stringToSign, $signature, $key, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * Normalisasi private key PEM yang rusak karena penyimpanan 1 baris
     * (newline hilang / literal "\n") sehingga openssl tidak bisa membacanya.
     */
    private function normalizePrivateKey(string $key): string
    {
        $key = trim((string) $key);

        // Literal escape "\n" dari form → newline asli.
        if (str_contains($key, '\\n')) {
            $key = str_replace('\\n', "\n", $key);
        }

        // PEM tanpa newline sama sekali (satu baris penuh) → bungkus ulang.
        if (!str_contains($key, "\n") && str_contains($key, '-----')) {
            $hasRsa = str_contains($key, 'BEGIN RSA PRIVATE KEY');
            $body = preg_replace('/^.*?-----BEGIN (RSA )?PRIVATE KEY-----/', '', $key);
            $body = preg_replace('/-----END (RSA )?PRIVATE KEY-----.*$/', '', $body);
            $body = preg_replace('/[^A-Za-z0-9+\/=\n]/', '', $body);
            $wrapped = trim(chunk_split($body, 64, "\n"));

            if ($wrapped !== '') {
                $key = ($hasRsa ? '-----BEGIN RSA PRIVATE KEY-----' : '-----BEGIN PRIVATE KEY-----')
                    . "\n" . $wrapped . "\n"
                    . ($hasRsa ? '-----END RSA PRIVATE KEY-----' : '-----END PRIVATE KEY-----');
            }
        }

        return $key;
    }

    /**
     * Apply Token signature: X-CLIENT-KEY|X-TIMESTAMP (Applica method).
     */
    public function applyToken(string $authCode): array
    {
        try {
            $ts = $this->timestamp();
            $stringToSign = $this->clientId() . '|' . $ts;
            $signature = $this->sign($stringToSign);

            $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'X-TIMESTAMP'   => $ts,
                'X-CLIENT-KEY'  => $this->clientId(),
                'X-SIGNATURE'   => $signature,
                'X-PARTNER-ID'  => $this->clientId(),
            ])->post($this->apiBaseUrl() . '/v1.0/access-token/b2b2c.htm', [
                'grantType'      => 'AUTHORIZATION_CODE',
                'authCode'       => $authCode,
                'refreshToken'   => '',
                'additionalInfo' => (object) [],
            ]);

            $data = $response->json() ?? [];

            if (!in_array($data['responseCode'] ?? '', ['2007400'], true)) {
                Log::error('[OfficialDANA] applyToken failed', [
                    'responseCode'    => $data['responseCode'] ?? null,
                    'responseMessage' => $data['responseMessage'] ?? null,
                    'status'          => $response->status(),
                ]);
                return [
                    'success'       => false,
                    'access_token'  => null,
                    'refresh_token' => null,
                    'error'         => $data['responseMessage'] ?? 'Gagal menukar kode otorisasi DANA.',
                ];
            }

            return [
                'success'       => true,
                'access_token'  => $data['accessToken'] ?? null,
                'refresh_token' => $data['refreshToken'] ?? null,
                'expires_at'    => $data['accessTokenExpiryTime'] ?? null,
                'error'         => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] applyToken exception', ['error' => $e->getMessage()]);
            return [
                'success'       => false,
                'access_token'  => null,
                'refresh_token' => null,
                'error'         => 'Gagal menghubungi DANA (apply token).',
            ];
        }
    }

    /**
     * Generate Deeplink Binding URL (ditampilkan sebagai redirect URL).
     * applyOTT disini memakai Deeplink Binding sesuai dokumentasi.
     */
    public function applyOTT(?string $phoneNumber = null): array
    {
        try {
            $externalId = (string) Str::uuid();
            $state = substr(md5(uniqid((string) mt_rand(), true)), 0, 24);

            $scopes = 'AGREEMENT_PAY,QUERY_BALANCE,DEFAULT_BASIC_PROFILE';

            $params = [
                'timestamp'        => $this->timestamp(),
                'partnerId'        => $this->clientId(),
                'externalId'       => $externalId,
                'channelId'        => 'DANAID',
                'merchantId'       => $this->merchantId(),
                'scopes'           => $scopes,
                'redirectUrl'      => $this->redirectUrl(),
                'state'            => $state,
                'allowRegistration'=> 'true',
            ];

            // Seamless binding bila nomor HP kurir tersedia
            if ($phoneNumber && preg_match('/^\d{9,15}$/', $phoneNumber)) {
                $seamlessData = json_encode([
                    'mobileNumber' => $this->normalizePhone($phoneNumber),
                    'bizScenario'  => 'PAYMENT',
                    'verifiedTime' => (new \DateTime('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d\TH:i:sP'),
                    'externalUid'  => (string) Str::uuid(),
                    'deviceId'     => (string) Str::uuid(),
                ], JSON_UNESCAPED_SLASHES);
                $params['seamlessData'] = urlencode($seamlessData);
                $params['seamlessSign'] = $this->seamlessSign($seamlessData);
            }

            $redirectUrl = $this->bindingUrl() . '?' . http_build_query($params);

            return [
                'success'      => true,
                'ott'          => $state,
                'redirect_url' => $redirectUrl,
                'error'        => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] applyOTT exception', ['error' => $e->getMessage()]);
            return [
                'success'      => false,
                'ott'          => null,
                'redirect_url' => null,
                'error'        => 'Gagal membuat tautan penghubungan DANA.',
            ];
        }
    }

    private function seamlessSign(string $seamlessData): string
    {
        return urlencode(base64_encode($this->sign($seamlessData)));
    }

    private function normalizePhone(string $phone): string
    {
        // 08xx -> 62 8xx
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }

    /**
     * Transactional signature: METHOD:path:lowercase(sha256(body)):timestamp
     */
    private function transactionalSignature(string $method, string $path, string $body, string $ts): string
    {
        $hash = strtolower(hash('sha256', $body));
        $stringToSign = $method . ':' . $path . ':' . $hash . ':' . $ts;
        return $this->sign($stringToSign);
    }

    /**
     * SNAP POST helper dengan X-SIGNATURE transactional + Authorization-Customer.
     */
    private function snapPost(string $path, array $payload, ?string $accessToken = null): array
    {
        $ts = $this->timestamp();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = $this->transactionalSignature('POST', $path, $body, $ts);

        $headers = [
            'Content-Type'  => 'application/json',
            'X-TIMESTAMP'   => $ts,
            'X-CLIENT-KEY'  => $this->clientId(),
            'X-SIGNATURE'   => $signature,
            'X-PARTNER-ID'  => $this->clientId(),
        ];

        if ($accessToken) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
            $headers['Authorization-Customer'] = 'Bearer ' . $accessToken;
        }

        $response = Http::withHeaders($headers)->post($this->apiBaseUrl() . $path, $payload);

        $data = $response->json() ?? [];
        if (!in_array($data['responseCode'] ?? '', ['2000000', '2007400', '2005400', '2005300'], true)) {
            Log::error('[OfficialDANA] snapPost failed', [
                'path'            => $path,
                'responseCode'    => $data['responseCode'] ?? null,
                'responseMessage' => $data['responseMessage'] ?? null,
                'status'          => $response->status(),
            ]);
        }

        return $data;
    }

    public function accountUnbinding(string $accessToken): array
    {
        try {
            $data = $this->snapPost('/v1.0/registration-account-unbinding.htm', [
                'partnerReferenceNo' => (string) Str::uuid(),
                'additionalInfo'     => (object) [],
            ], $accessToken);

            if (!in_array($data['responseCode'] ?? '', ['2000000', '2007400'], true)) {
                return ['success' => false, 'error' => $data['responseMessage'] ?? 'Gagal memutuskan DANA.'];
            }
            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] accountUnbinding exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Gagal memutuskan DANA.'];
        }
    }

    public function queryUserProfile(string $accessToken): array
    {
        try {
            $data = $this->snapPost('/v1.0/query-user-profile.htm', [
                'partnerReferenceNo' => (string) Str::uuid(),
                'additionalInfo'     => (object) [],
            ], $accessToken);

            if (!in_array($data['responseCode'] ?? '', ['2000000', '2007400'], true)) {
                return ['success' => false, 'profile' => null, 'error' => $data['responseMessage'] ?? 'Gagal mengambil profil DANA.'];
            }

            $profile = $data['additionalInfo']['userInfo'] ?? [];
            return [
                'success' => true,
                'profile' => [
                    'masked_phone'         => $profile['maskedPhoneNumber'] ?? $profile['maskedIdentifier'] ?? null,
                    'name'                 => $profile['displayName'] ?? null,
                    'is_kyc'               => $profile['isKyc'] ?? null,
                    'dana_user_reference'  => $profile['danaUserId'] ?? $profile['userReference'] ?? $profile['userId'] ?? null,
                ],
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] queryUserProfile exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'profile' => null, 'error' => 'Gagal mengambil profil DANA.'];
        }
    }

    public function balanceInquiry(string $accessToken): array
    {
        try {
            $data = $this->snapPost('/v1.0/balance-inquiry.htm', [
                'partnerReferenceNo' => (string) Str::uuid(),
                'additionalInfo'     => ['accessToken' => $accessToken],
            ], $accessToken);

            if (!in_array($data['responseCode'] ?? '', ['2000000', '2007400'], true)) {
                return ['success' => false, 'balance' => null, 'currency' => null, 'error' => $data['responseMessage'] ?? 'Gagal mengecek saldo DANA.'];
            }

            $balances = $data['balanceInfo'] ?? [];
            $balance = $balances[0]['balanceAmount']['value'] ?? 0;

            return [
                'success'  => true,
                'balance'  => (float) $balance,
                'currency' => $balances[0]['balanceAmount']['currency'] ?? 'IDR',
                'error'    => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] balanceInquiry exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'balance' => null, 'currency' => null, 'error' => 'Gagal mengecek saldo DANA.'];
        }
    }

    public function customerTopUp(string $accountIdentifier, float $amount, string $referenceNo): array
    {
        try {
            $data = $this->snapPost('/v1.0/customer-topup.htm', [
                'partnerReferenceNo' => $referenceNo,
                'amount'             => [
                    'value'    => (string) number_format($amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'beneficiaryAccount' => ['maskedAccountNo' => $accountIdentifier],
                'additionalInfo'     => (object) [],
            ]);

            if (!in_array($data['responseCode'] ?? '', ['2000000', '2005300', '2005400'], true)) {
                return [
                    'success'        => false,
                    'transaction_id' => null,
                    'status'         => 'failed',
                    'error'          => $data['responseMessage'] ?? 'Gagal mengirim dana ke DANA.',
                ];
            }

            return [
                'success'        => true,
                'transaction_id' => $data['referenceNo'] ?? $data['originalReferenceNo'] ?? null,
                'status'         => 'pending',
                'error'          => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] customerTopUp exception', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => 'Gagal mengirim dana ke DANA.',
            ];
        }
    }

    public function customerTopUpInquiry(string $transactionId): array
    {
        try {
            $data = $this->snapPost('/v1.0/customer-topup-inquiry.htm', [
                'partnerReferenceNo' => $transactionId,
                'additionalInfo'     => (object) [],
            ]);

            $status = 'unknown';
            $code = $data['responseCode'] ?? '';
            if ($code === '2000000' || $code === '2007400') {
                $status = 'success';
            } elseif (in_array($code, ['2005300', '2005400'], true)) {
                $status = 'pending';
            } elseif (str_starts_with($code, '400') || $code === '5007400') {
                $status = 'failed';
            }

            return [
                'success' => true,
                'status'  => $status,
                'details' => $data,
                'error'   => null,
            ];
        } catch (\Exception $e) {
            Log::error('[OfficialDANA] customerTopUpInquiry exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'status' => 'unknown', 'details' => [], 'error' => 'Gagal memeriksa status transaksi.'];
        }
    }
}
