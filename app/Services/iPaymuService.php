<?php

namespace App\Services;

/**
 * iPaymu Service Layer.
 * Handles disbursement/payout to e-wallets including DANA.
 *
 * PRD §59: Disbursement via iPaymu
 * PRD §60: Withdrawal lifecycle
 *
 * Aturan penting:
 * - Kredensial (VA + API Key + secret) di-resolve terpusat lewat credentials():
 *   diprioritaskan dari tabel Settings (Admin > Pengaturan > Provider & Integrasi
 *   Eksternal → iPaymu), bila kosong memakai env (config/ipaymu.php).
 * - Layanan payout iPaymu memerlukan aktivasi merchant. Jika belum aktif,
 *   service mengembalikan status 'not_configured' sehingga WithdrawalService
 *   MENOLAK permintaan SEBELUM saldo direservasi — tidak ada simulasi sukses.
 * - Verdict transaksi dibedakan tegas:
 *   * 'known'   → iPaymu menjawab dengan status resmi (success/failed/pending).
 *   * 'unknown' → tidak ada jawaban (timeout/network). Payout TIDAK BOLEH
 *                 dikirim ulang; status tetap diproses dan disinkronkan
 *                 lewat inquiry/webhook.
 */
class iPaymuService
{
    private string $baseUrl;
    private ?string $merchantId;
    private ?string $va;
    private ?string $apiKey;
    private ?string $secretKey;
    private int $timeout;

    /** Cache credentials untuk satu siklus request. */
    private static ?array $credentialCache = null;

    public function __construct(?array $configOverride = null)
    {
        $cfg = array_merge(self::credentials(), $configOverride ?? []);

        $this->baseUrl   = rtrim((string) ($cfg['base_url'] ?? 'https://sandbox.ipaymu.com'), '/');
        $this->merchantId = (string) ($cfg['merchant_id'] ?? '');
        $this->va        = (string) ($cfg['va'] ?? '');
        $this->apiKey    = (string) ($cfg['api_key'] ?? '');
        $this->secretKey = (string) ($cfg['secret_key'] ?? '');
        $this->timeout   = (int) ($cfg['timeout'] ?? 30);
    }

    /**
     * Resolusi kredensial & konfigurasi iPaymu dalam satu tempat.
     *
     * Prioritas: tabel Settings (diedit dari Admin > Pengaturan > Provider &
     * Integrasi Eksternal) → fallback env via config/ipaymu.php. Webhook
     * controller dan halaman admin memakai fungsi ini juga agar tidak ada
     * dua sumber kebenaran.
     *
     * @return array{base_url:string, merchant_id:string, va:string, api_key:string,
     *               secret_key:string, payout_enabled:bool, webhook_secret:string, timeout:int}
     */
    public static function credentials(): array
    {
        if (self::$credentialCache !== null) {
            return self::$credentialCache;
        }

        $cfg = config('ipaymu', []);
        $fallback = [
            'base_url'       => $cfg['base_url'] ?? 'https://sandbox.ipaymu.com',
            'merchant_id'    => $cfg['merchant_id'] ?? '',
            'va'             => $cfg['va'] ?? '',
            'api_key'        => $cfg['api_key'] ?? '',
            'secret_key'     => $cfg['secret_key'] ?? '',
            'payout_enabled' => (bool) ($cfg['payout_enabled'] ?? false),
            'webhook_secret' => $cfg['webhook_secret'] ?? '',
            'timeout'        => (int) ($cfg['timeout'] ?? 30),
        ];

        $string = function (string $key, string $default): string {
            $value = \App\Models\Setting::get($key, $default);
            return trim((string) ($value ?? $default));
        };

        $credentials = [
            'base_url'       => $string('ipaymu_base_url', $fallback['base_url']),
            'merchant_id'    => $string('ipaymu_merchant_id', $fallback['merchant_id']),
            'va'             => $string('ipaymu_va', $fallback['va']),
            'api_key'        => $string('ipaymu_api_key', $fallback['api_key']),
            'secret_key'     => $string('ipaymu_secret_key', $fallback['secret_key']),
            'webhook_secret' => $string('ipaymu_webhook_secret', $fallback['webhook_secret']),
            'payout_enabled' => filter_var(
                \App\Models\Setting::get('ipaymu_payout_enabled', $fallback['payout_enabled']),
                FILTER_VALIDATE_BOOLEAN
            ),
            'timeout'        => (int) \App\Models\Setting::get('ipaymu_timeout', $fallback['timeout']),
        ];

        return self::$credentialCache = $credentials;
    }

    /** Reset cache credentials (dipakai setelah admin menyimpan pengaturan). */
    public static function flushCredentialsCache(): void
    {
        self::$credentialCache = null;
    }

    /** Merchant identity (fallback: va === merchant_id). */
    private function merchant(): string
    {
        return !empty($this->va) ? $this->va : (string) $this->merchantId;
    }

    /**
     * Headers Direct API iPaymu v2:
     * - X-Api-Key : API key merchant
     * - va        : Virtual account (merchant id)
     * - timestamp : UNIX time (WIB)
     * - Sign      : HMAC-SHA256 hex dari "<METHOD> : <requestBody> : <X-Api-Key> : <timestamp>"
     */
    private function buildHeaders(string $method, ?string $body = null): array
    {
        $now = time();
        $signBase = strtoupper($method) . ' : ' . ($body ?? '') . ' : ' . (string) $this->apiKey . ' : ' . $now;
        $sign = hash_hmac('sha256', $signBase, (string) $this->apiKey);

        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Api-Key: ' . (string) $this->apiKey,
            'va: ' . $this->merchant(),
            'timestamp: ' . $now,
            'Sign: ' . $sign,
        ];
    }

    /**
     * Create a disbursement/payout transaction to DANA destination phone.
     *
     * @return array{
     *   success: bool,
     *   transaction_id: ?string,
     *   status: string,           // success | processing | pending | failed | not_configured
     *   verdict: 'known'|'unknown',
     *   error: ?string,
     *   raw: ?array
     * }
     */
    public function disburse(string $destinationPhone, float $amount, string $idempotencyKey): array
    {
        if (!self::isPayoutActive()) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'not_configured',
                'verdict'        => 'known',
                'error'          => 'Layanan penarikan DANA (payout iPaymu) belum diaktifkan. '
                    . 'Set kredensial IPAYMU_* dan aktifkan IPAYMU_PAYOUT_ENABLED setelah merchant iPaymu disetujui.',
                'raw'            => null,
            ];
        }

        $path = (string) config('ipaymu.disburse_path', '/v2/disbursement');

        $payload = [
            'channel'     => 'dana',
            'destination' => ['phone' => $destinationPhone],
            'amount'      => (int) round($amount),
            'reference'   => $idempotencyKey,   // idempotency reference iPaymu
            'description' => 'Penarikan saldo kurir City Courier',
        ];

        $body = json_encode($payload);
        $raw = $this->call('POST', $path, $body);
        return $this->interpretResponse($raw);
    }

    /**
     * Inquiry status of a disbursement transaction by provider reference
     * (fallback: idempotency key) resmi iPaymu.
     */
    public function inquire(string $transactionId): array
    {
        if (!self::isConfigured()) {
            return [
                'status'  => 'unknown',
                'verdict' => 'unknown',
                'error'   => 'iPaymu belum terkonfigurasi.',
                'raw'     => null,
            ];
        }

        // Cek status transaksi iPaymu v2 adalah POST {base}/api/v2/transaction
        // dengan body {"trx_id": "..."} — endpoint ini menolak metode GET.
        $path = (string) config('ipaymu.inquiry_path', '/api/v2/transaction');
        $body = json_encode(['trx_id' => $transactionId]);
        $raw = $this->call('POST', $path, $body);
        return $this->interpretResponse($raw);
    }

    /** HTTP call via curl. Never throws; returns raw envelope. */
    private function call(string $method, string $path, ?string $body): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $this->buildHeaders($method, $body),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (strtoupper($method) === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            // Jaringan gagal sebelum mendapat jawaban → hasil TIDAK diketahui.
            return ['reachable' => false, 'http_code' => 0, 'body' => null, 'error' => $error ?: "curl_error($errno)"];
        }

        $data = json_decode((string) $response, true);
        return [
            'reachable' => true,
            'http_code' => $httpCode,
            'body'      => json_last_error() === JSON_ERROR_NONE && is_array($data) ? $data : null,
            'error'     => json_last_error() === JSON_ERROR_NONE ? null : 'Respons iPaymu tidak valid: ' . substr((string) $response, 0, 500),
        ];
    }

    /** Normalize provider response into a predictable envelope. */
    private function interpretResponse(array $raw): array
    {
        if (!$raw['reachable']) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'unknown',
                'verdict'        => 'unknown',
                'error'          => 'Tidak dapat menghubungi iPaymu: ' . $raw['error'],
                'raw'            => null,
            ];
        }

        $data = $raw['body'];
        if ($data === null || $raw['http_code'] >= 400) {
            $msg = $raw['error'] ?? ('iPaymu menolak permintaan (HTTP ' . $raw['http_code'] . ').');
            $providerMsg = $data['message'] ?? ($data['error'] ?? ($data['Status'] ?? null));
            if ($providerMsg) {
                $msg .= ' ' . (is_string($providerMsg) ? $providerMsg : json_encode($providerMsg));
            }

            return [
                'success'        => false,
                'transaction_id' => $data['transaction_id'] ?? ($data['data']['transaction_id'] ?? null),
                'status'         => 'failed',
                'verdict'        => 'known',
                'error'          => $msg,
                'raw'            => $data,
            ];
        }

        // iPaymu v2 mengembalikan { "Status": 0, "Data": { ... } } atau polos.
        $dataBlock = $data['Data'] ?? $data['data'] ?? $data;
        $statusNum = is_array($data) ? ($data['Status'] ?? $data['status'] ?? null) : null;

        $txId = null;
        if (is_array($dataBlock)) {
            $txId = $dataBlock['transaction_id']
                ?? $dataBlock['TransactionId']
                ?? $dataBlock['trx_id']
                ?? $dataBlock['reference_id']
                ?? null;
        }

        $status = $this->mapRawStatus($dataBlock, $statusNum);

        if ($status === null) {
            return [
                'success'        => false,
                'transaction_id' => is_scalar($txId) ? (string) $txId : null,
                'status'         => 'unknown',
                'verdict'        => 'unknown',
                'error'          => 'Status iPaymu tidak dikenali.',
                'raw'            => $data,
            ];
        }

        $knownFailure = $status === 'failed';
        $providerError = is_array($dataBlock) ? ($dataBlock['message'] ?? null) : null;

        return [
            'success'        => $status === 'success' || $status === 'processing' || $status === 'pending',
            'transaction_id' => is_scalar($txId) ? (string) $txId : null,
            'status'         => $status,
            'verdict'        => 'known',
            'error'          => $knownFailure ? ($providerError ?? 'Payout ditolak oleh iPaymu.') : null,
            'raw'            => $data,
        ];
    }

    /** Map iPaymu raw status (angka/kata) ke status internal. */
    private function mapRawStatus($dataBlock, $statusNum): ?string
    {
        $raw = null;
        if (is_array($dataBlock)) {
            $raw = $dataBlock['status'] ?? $dataBlock['Status'] ?? null;
        } elseif (is_scalar($dataBlock)) {
            $raw = $dataBlock;
        }
        if ($raw === null) {
            $raw = $statusNum;
        }

        if ($raw === null) {
            return null;
        }

        // Angka status iPaymu: 0 = sukses; 1 = pending; nilai lain umumnya gagal.
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            return (int) $raw === 0 ? 'success' : ((int) $raw === 1 ? 'pending' : 'failed');
        }

        return match (strtolower((string) $raw)) {
            'success', 'paid', 'settled', 'completed', 'berhasil'  => 'success',
            'processing', 'pending', 'queued', 'awaiting', 'diproses', 'menunggu' => 'pending',
            'expired', 'failed', 'cancelled', 'reversed', 'gagal'  => 'failed',
            default                                                => null,
        };
    }

    /**
     * Check if iPaymu is properly configured (credentials present).
     */
    public static function isConfigured(): bool
    {
        $cfg = self::credentials();

        return !empty($cfg['api_key']) && !empty($cfg['merchant_id'] ?: $cfg['va']);
    }

    /**
     * Check if the PAYOUT service is activated by iPaymu for City Courier.
     * This requires: credentials + explicit activation flag
     * (settings `ipaymu_payout_enabled` atau IPAYMU_PAYOUT_ENABLED=true).
     */
    public static function isPayoutActive(): bool
    {
        $cfg = self::credentials();

        return self::isConfigured() && (bool) $cfg['payout_enabled'];
    }
}
