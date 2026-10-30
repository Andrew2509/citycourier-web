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
            'buyer_email'    => $cfg['buyer_email'] ?? '',
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
            'buyer_email'    => $string('ipaymu_buyer_email', $fallback['buyer_email']),
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
     * Headers API iPaymu v2 — sesuai docs.ipaymu.com (Signature Generation):
     * - va        : nomor VA merchant dari menu Integration
     * - timestamp : 14 digit, format YYYYMMDDHHmmss
     * - signature : HMAC-SHA256(StringToSign, APIKey)
     *
     * StringToSign = METHOD + ":" + VA + ":" + RequestBody + ":" + APIKey
     * - POST: RequestBody = SHA-256 hex (huruf kecil) dari body JSON
     * - GET : RequestBody = SHA-256 hex dari JSON query parameters
     *         (tanpa query → hash dari '{}', sesuai sample resmi iPaymu
     *          `payment_channel.php`: hash('sha256', '{}'))
     */
    private function buildHeaders(string $method, ?string $body = null): array
    {
        $method = strtoupper($method);
        $timestamp = \Illuminate\Support\Carbon::now()->format('YmdHis');

        if ($method === 'POST') {
            $requestBody = hash('sha256', (string) ($body ?? ''));
        } else {
            // GET: query parameters di-stringify JSON lalu di-hash.
            // Request tanpa query memakai objek JSON kosong '{}'.
            $queryJson = ($body === null || $body === '') ? '{}' : $body;
            $requestBody = hash('sha256', $queryJson);
        }

        $stringToSign = $method . ':' . $this->merchant() . ':' . $requestBody . ':' . (string) $this->apiKey;
        $signature = hash_hmac('sha256', $stringToSign, (string) $this->apiKey);

        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'va: ' . $this->merchant(),
            'timestamp: ' . $timestamp,
            'signature: ' . $signature,
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

        $path = (string) config('ipaymu.disburse_path', '/api/v2/disbursement');

        $payload = [
            'channel'     => 'dana',
            'destination' => ['phone' => $destinationPhone],
            'amount'      => (int) round($amount),
            'reference'   => $idempotencyKey,   // idempotency reference iPaymu
            'description' => 'Penarikan saldo kurir City Courier',
        ];

        $body = $this->encodeBody($payload);
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

        // Cek status transaksi iPaymu v2: POST {base}/api/v2/transaction
        // dengan body {"transactionId": "..."} (docs.ipaymu.com → check-transaction).
        $path = (string) config('ipaymu.inquiry_path', '/api/v2/transaction');
        $body = $this->encodeBody(['transactionId' => (string) $transactionId]);
        $raw = $this->call('POST', $path, $body);
        return $this->interpretResponse($raw);
    }

    /**
     * Daftar saluran pembayaran masuk (docs.ipaymu.com → Payment Channels):
     * GET {base}/api/v2/payment-channels.
     * Dipakai oleh IpaymuPaymentService untuk daftar VA & QRIS di checkout.
     */
    public function paymentChannels(): array
    {
        $path = (string) config('ipaymu.payment_channels_path', '/api/v2/payment-channels');
        $raw = $this->call('GET', $path, '');
        return $this->normalizeEnvelope($raw);
    }

    /**
     * Buat transaksi pembayaran masuk (docs.ipaymu.com → Payment → Direct):
     * POST {base}/api/v2/payment/direct.
     * VA  → Data.PaymentNo (nomor VA pelanggan).
     * QRIS→ Data berisi QR string / Url pembayaran.
     */
    public function createDirectPayment(array $payload): array
    {
        $path = (string) config('ipaymu.payment_direct_path', '/api/v2/payment/direct');
        $body = $this->encodeBody($payload);
        $raw = $this->call('POST', $path, $body);
        return $this->normalizeEnvelope($raw);
    }

    /**
     * Encode body JSON persis seperti sample resmi iPaymu
     * (ipaymu-payment-v2-sample-php): `json_encode($body, JSON_UNESCAPED_SLASHES)`.
     *
     * PENTING: body yang di-hash untuk signature harus byte-identik dengan body
     * yang dikirim. Tanpa JSON_UNESCAPED_SLASHES, URL (notifyUrl) ter-encode
     * menjadi `https:\/\/...` sehingga hash berbeda dari yang dihitung iPaymu
     * dan request ditolak dengan "unauthorized signature".
     */
    private function encodeBody(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Fallback aman: JSON minimal agar signature tetap konsisten.
            return '{}';
        }
        return $json;
    }

    /**
     * Normalisasi envelope resmi iPaymu v2 ({Status, Success, Message, Data})
     * menjadi bentuk tunggal untuk pemakaian pembayaran masuk.
     */
    private function normalizeEnvelope(array $raw): array
    {
        if (!$raw['reachable']) {
            return [
                'success'   => false,
                'data'      => null,
                'error'     => 'Tidak dapat menghubungi iPaymu: ' . ($raw['error'] ?? 'network error'),
                'http_code' => 0,
                'raw'       => null,
            ];
        }

        $data = $raw['body'];

        if ($data === null || $raw['http_code'] >= 400) {
            $msg = $data['Message'] ?? $data['message'] ?? $data['Msg'] ?? $data['error'] ?? $data['Error']
                ?? $raw['error']
                ?? ('iPaymu menolak permintaan (HTTP ' . $raw['http_code'] . ').');
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            return [
                'success'   => false,
                'data'      => $data,
                'error'     => (string) $msg,
                'http_code' => $raw['http_code'],
                'raw'       => $data,
            ];
        }

        $envelopeSuccess = $data['Success'] ?? ($data['success'] ?? true);
        if ($envelopeSuccess === false) {
            return [
                'success'   => false,
                'data'      => $data,
                'error'     => (string) ($data['Message'] ?? ($data['message'] ?? 'iPaymu menolak permintaan.')),
                'http_code' => $raw['http_code'],
                'raw'       => $data,
            ];
        }

        return [
            'success'   => true,
            'data'      => $data['Data'] ?? $data['data'] ?? null,
            'error'     => null,
            'http_code' => $raw['http_code'],
            'raw'       => $data,
        ];
    }

    /**
     * Inquiry nama pemilik akun e-wallet tujuan (DANA name inquiry).
     *
     * Endpoint-nya TIDAK ada di dokumentasi publik iPaymu v2 → path dibuat
     * configurable (ipaymu.inquiry_account_path) dan default kosong. Selama
     * belum diisi (atau payout belum aktif), metode ini jujur melapor "not
     * available" — TIDAK ada nama fiktif, TIDAK ada panggilan API.
     *
     * @param string $phone Nomor DANA tujuan (akan dinormalisasi ke 628…).
     * @return array{
     *   available: bool,
     *   account_name: ?string,
     *   reason: string,   // ok | not_configured | payout_inactive | endpoint_not_set | lookup_failed
     *   error: ?string,
     *   raw: ?array
     * }
     */
    public function inquireAccountName(string $phone): array
    {
        $none = [
            'available'    => false,
            'account_name' => null,
            'reason'       => '',
            'error'        => null,
            'raw'          => null,
        ];

        if (!self::isConfigured()) {
            $none['reason'] = 'not_configured';
            return $none;
        }

        if (!self::isPayoutActive()) {
            // Name inquiry milik keluarga layanan disbursement — tanpa
            // aktivasi payout, iPaymu pasti menolak; jangan panggil percuma.
            $none['reason'] = 'payout_inactive';
            return $none;
        }

        $path = trim((string) config('ipaymu.inquiry_account_path', ''));
        if ($path === '') {
            // iPaymu belum memberikan spec inquiry nama → fitur jujur belum ada.
            $none['reason'] = 'endpoint_not_set';
            return $none;
        }

        $body = json_encode(['account' => $phone]);
        $raw = $this->call('POST', $path, $body);

        if (!$raw['reachable']) {
            $none['reason']  = 'lookup_failed';
            $none['error']   = $raw['error'] ?? 'Tidak dapat menghubungi iPaymu.';
            return $none;
        }

        if ($raw['http_code'] >= 400 || $raw['body'] === null) {
            $none['reason'] = 'lookup_failed';
            $none['error']  = $raw['error'] ?? ('iPaymu menolak inquiry nama (HTTP ' . $raw['http_code'] . ').');
            $none['raw']    = $raw['body'];
            return $none;
        }

        // Respons dibaca fleksibel sampai spec resmi tersedia:
        // Data.AccountName / account_name / beneficiary_name / Name / name.
        $data     = $raw['body'];
        $dataBlock = $data['Data'] ?? $data['data'] ?? $data;
        $name     = null;
        if (is_array($dataBlock)) {
            $name = $dataBlock['AccountName'] ?? $dataBlock['account_name']
                ?? $dataBlock['beneficiary_name'] ?? $dataBlock['BeneficiaryName']
                ?? $dataBlock['Name'] ?? $dataBlock['name'] ?? null;
        }

        if (!is_string($name) || trim($name) === '') {
            $none['reason'] = 'lookup_failed';
            $none['error']  = 'Nama pemilik akun tidak ditemukan pada respons iPaymu.';
            $none['raw']    = $data;
            return $none;
        }

        return [
            'available'    => true,
            'account_name' => trim($name),
            'reason'       => 'ok',
            'error'        => null,
            'raw'          => $data,
        ];
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
                'http_code'      => 0,
            ];
        }

        $data = $raw['body'];
        if ($data === null || $raw['http_code'] >= 400) {
            $msg = $raw['error'] ?? ('iPaymu menolak permintaan (HTTP ' . $raw['http_code'] . ').');

            // Envelope resmi memakai key awalan kapital ({Message, Status, Success}),
            // beberapa endpoint memakai kecil → cek semua penulisan umum.
            $providerMsg = $data['Message'] ?? $data['message']
                ?? $data['Msg'] ?? $data['msg']
                ?? $data['error'] ?? $data['Error']
                ?? $data['StatusDesc'] ?? null;
            if (is_array($providerMsg)) {
                $providerMsg = json_encode($providerMsg);
            }
            if ($providerMsg) {
                $msg .= ' ' . $providerMsg;
            } elseif (isset($data['Status'])) {
                $msg .= ' (Kode: ' . $data['Status'] . ')';
            }

            return [
                'success'        => false,
                'transaction_id' => $data['transaction_id'] ?? ($data['data']['transaction_id'] ?? null),
                'status'         => 'failed',
                'verdict'        => 'known',
                'error'          => $msg,
                'raw'            => $data,
                'http_code'      => $raw['http_code'],
            ];
        }

        // Envelope resmi iPaymu v2: { Status: 200, Success: true, Message, Data: {...} }
        $dataBlock = $data['Data'] ?? $data['data'] ?? $data;
        $envelopeSuccess = $data['Success'] ?? ($data['success'] ?? true);

        $txId = null;
        if (is_array($dataBlock)) {
            $txId = $dataBlock['TransactionId']
                ?? $dataBlock['transactionId']
                ?? $dataBlock['trx_id']
                ?? $dataBlock['reference_id']
                ?? null;
        }

        // Envelope menyatakan gagal (mis. transaksi tidak ditemukan) padahal
        // HTTP 200 → perlakukan sebagai penolakan resmi provider.
        if ($envelopeSuccess === false) {
            return [
                'success'        => false,
                'transaction_id' => is_scalar($txId) ? (string) $txId : null,
                'status'         => 'failed',
                'verdict'        => 'known',
                'error'          => (string) ($data['Message'] ?? 'iPaymu menolak permintaan.'),
                'raw'            => $data,
                'http_code'      => $raw['http_code'],
            ];
        }

        // Status TRANSAKSI ada di Data.Status (kode resmi 0/1/2/3/4/5/6/7/-2) —
        // envelope Status di level atas (200/400) bukan status transaksi.
        $status = $this->mapRawStatus($dataBlock, null);

        if ($status === null) {
            return [
                'success'        => false,
                'transaction_id' => is_scalar($txId) ? (string) $txId : null,
                'status'         => 'unknown',
                'verdict'        => 'unknown',
                'error'          => 'Status iPaymu tidak dikenali.',
                'raw'            => $data,
                'http_code'      => $raw['http_code'],
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
            'http_code'      => $raw['http_code'],
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

        // Kode status resmi iPaymu v2 (docs.ipaymu.com → check-transaction):
        // 0 pending · 1 success · 2 cancelled · 3 refund · 4 error · 5 failed
        // 6 success (unsettled) · 7 escrow · -2 expired.
        if (is_int($raw) || (is_string($raw) && (ctype_digit($raw) || $raw === '-2'))) {
            return match ((int) $raw) {
                1, 6    => 'success',
                0, 7    => 'pending',
                default => 'failed',
            };
        }

        return match (strtolower((string) $raw)) {
            'success', 'paid', 'settled', 'completed', 'berhasil'  => 'success',
            'processing', 'pending', 'queued', 'awaiting', 'diproses', 'menunggu' => 'pending',
            'expired', 'failed', 'cancelled', 'reversed', 'gagal', 'dibatalkan', 'error'  => 'failed',
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
