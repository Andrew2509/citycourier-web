<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Pembayaran Masuk Checkout via iPaymu API v2 (VA & QRIS).
 *
 * docs.ipaymu.com → Payment → Direct:
 * - POST /api/v2/payment/direct   → VA (Data.PaymentNo) / QRIS (QR/Url)
 * - GET  /api/v2/payment-channels → daftar saluran aktif + fee
 * - POST /api/v2/transaction      → Check Transaction (sumber status resmi)
 *
 * Aturan penting:
 * - Status 'paid' HANYA bila iPaymu menyatakan sukses (kode 1/6) lewat
 *   callback (signature tervalidasi) atau inquiry resmi — TIDAK ada simulasi.
 * - notifyUrl mengarah ke /api/ipaymu/callback (domain terdaftar di iPaymu).
 * - iPaymu tidak punya API cancel untuk payment pending; cancel menandai
 *   lokal, dan bila pelanggan ternyata tetap membayar, callback/inquiry
 *   yang menandai 'paid' (uang sungguhan masuk tetap dihormati).
 */
class IpaymuPaymentService
{
    /** Saluran VA resmi iPaymu v2 (docs → Payment Channels). */
    public const VA_CHANNELS = [
        'bag', 'bpd_bali', 'bca', 'bni', 'cimb', 'mandiri',
        'bmi', 'bri', 'bsi', 'permata', 'danamon', 'btn',
    ];

    public function __construct(private ?iPaymuService $ipaymu = null)
    {
        $this->ipaymu = $this->ipaymu ?? new iPaymuService();
    }

    // ─── Daftar metode pembayaran (untuk GET /payment/methods) ───────

    /**
     * Ambil daftar VA & QRIS aktif dari iPaymu, dinormalisasi ke bentuk
     * yang dikonsumsi model PaymentMethod di Flutter:
     * { code, name, group, icon, fee, is_available }.
     *
     * @return array{success:bool, methods:array, message:?string}
     */
    public function getPaymentMethods(): array
    {
        if (!iPaymuService::isConfigured()) {
            return [
                'success' => false,
                'methods' => [],
                'message' => 'iPaymu belum terkonfigurasi. Set kredensial di Admin > Pengaturan.',
            ];
        }

        $res = $this->ipaymu->paymentChannels();
        if (!$res['success'] || !is_array($res['data'])) {
            return [
                'success' => false,
                'methods' => [],
                'message' => $res['error'] ?? 'Gagal mengambil saluran pembayaran iPaymu.',
            ];
        }

        $methods = [];
        foreach ($res['data'] as $group) {
            $methodCode = strtolower((string) ($group['Code'] ?? ''));
            foreach (($group['Channels'] ?? []) as $channel) {
                $active = strtolower((string) ($channel['FeatureStatus'] ?? '')) === 'active'
                    && strtolower((string) ($channel['HealthStatus'] ?? 'online')) !== 'offline';
                if (!$active) {
                    continue;
                }

                $code = (string) ($channel['Code'] ?? '');
                if ($code === '') {
                    continue;
                }

                if ($methodCode === 'va') {
                    $methods[] = [
                        'code'         => strtolower($code),
                        'name'         => (string) ($channel['Name'] ?? strtoupper($code)) . ' Virtual Account',
                        'group'        => 'virtual_account',
                        'icon'         => (string) ($channel['Logo'] ?? ''),
                        'fee'          => (int) ($channel['TransactionFee']['ActualFee'] ?? 0),
                        'is_available' => true,
                    ];
                } elseif ($methodCode === 'qris') {
                    $methods[] = [
                        // Kode 'QRIS' agar Flutter menampilkan layar QR (payment_method_screen
                        // mencocokkan upperCode.contains('QRIS')); channel asli 'mpm'
                        // dipetakan kembali saat create.
                        'code'         => 'QRIS',
                        'name'         => (string) ($channel['Name'] ?? 'QRIS'),
                        'group'        => 'qris',
                        'icon'         => (string) ($channel['Logo'] ?? ''),
                        'fee'          => (int) ($channel['TransactionFee']['ActualFee'] ?? 0),
                        'is_available' => true,
                    ];
                }
                // cstore / ewallet / dll: sengaja belum ditampilkan — checkout
                // saat ini hanya menawarkan VA + QRIS + COD/Tunai.
            }
        }

        return ['success' => true, 'methods' => $methods, 'message' => null];
    }

    // ─── Buat transaksi pembayaran (POST /payment/create) ────────────

    /**
     * @param array{
     *   payment_type:string,      // 'va' | 'qris'
     *   channel_code:?string,     // 'bca' dsb (VA) — QRIS memakai 'mpm'
     *   amount:int,
     *   reference:string,         // referensi unik merchant (idempoten record)
     *   customer:array{name:string,email:string,phone:string},
     *   items:array,
     *   comments:?string
     * } $input
     * @return array{
     *   success:bool, error:?string, payment_id:?string, va_number:?string,
     *   qr_string:?string, payment_url:?string, expired_at:?string, raw:?array
     * }
     */
    public function createPayment(array $input): array
    {
        $none = [
            'success' => false, 'error' => null, 'payment_id' => null,
            'va_number' => null, 'qr_string' => null, 'payment_url' => null,
            'expired_at' => null, 'http_code' => null, 'raw' => null,
        ];

        if (!iPaymuService::isConfigured()) {
            $none['error'] = 'iPaymu belum terkonfigurasi. Set kredensial di Admin > Pengaturan.';
            return $none;
        }

        $isQris = $input['payment_type'] === 'qris';
        $channel = $isQris
            ? (string) config('ipaymu.qris_channel', 'mpm')
            : strtolower((string) ($input['channel_code'] ?? ''));

        if (!$isQris && !in_array($channel, self::VA_CHANNELS, true)) {
            $none['error'] = "Saluran VA tidak dikenal: '{$channel}'.";
            return $none;
        }

        $notifyUrl = rtrim((string) config('app.url'), '/') . '/api/ipaymu/callback';

        if ($isQris) {
            $minutes = max(5, (int) config('ipaymu.qris_expired_minutes', 60));
            $expired = ['expired' => $minutes, 'expiredType' => 'minutes'];
            $expiresAt = now()->addMinutes($minutes);
        } else {
            $hours = max(1, (int) config('ipaymu.payment_expired_hours', 24));
            $expired = ['expired' => $hours, 'expiredType' => 'hours'];
            $expiresAt = now()->addHours($hours);
        }

        $items = $input['items'];
        $product = [];
        $qty = [];
        $price = [];
        foreach ($items as $item) {
            $product[] = (string) ($item['name'] ?? 'Biaya Pengiriman City Courier');
            $qty[]     = (int) ($item['quantity'] ?? 1);
            $price[]   = (int) ($item['price'] ?? $input['amount']);
        }

        $payload = array_merge([
            'name'           => (string) ($input['customer']['name'] ?? 'Customer'),
            'phone'          => (string) ($input['customer']['phone'] ?? '08123456789'),
            'email'          => (string) ($input['customer']['email'] ?? 'customer@example.com'),
            'amount'         => (int) $input['amount'],
            'notifyUrl'      => $notifyUrl,
            'referenceId'    => (string) $input['reference'],
            'paymentMethod'  => $isQris ? 'qris' : 'va',
            'paymentChannel' => $channel,
            'product'        => $product,
            'qty'            => $qty,
            'price'          => $price,
        ], $expired);

        if (!empty($input['comments'])) {
            $payload['comments'] = (string) $input['comments'];
        }

        $res = $this->ipaymu->createDirectPayment($payload);
        if (!$res['success'] || !is_array($res['data'])) {
            // Simpan payload + respons mentah supaya penolakan seperti
            // "Suspicious buyer" bisa didiagnosis dari log server, bukan
            // ditebak dari aplikasi.
            Log::warning('iPaymu payment/direct ditolak', [
                'reference' => $payload['referenceId'],
                'http_code' => $res['http_code'] ?? null,
                'error'     => $res['error'] ?? null,
                'provider'  => $res['raw']['body'] ?? $res['raw'] ?? null,
                'payload'   => $payload,
            ]);

            $none['error'] = $res['error'] ?? 'iPaymu menolak pembayaran.';
            $none['http_code'] = $res['http_code'] ?? null;
            $none['raw'] = $res['raw'];
            return $none;
        }

        $data = $res['data'];

        // iPaymu kadang mengirim payload QRIS (deretan karakter EMVCo, bukan
        // URL halaman) pada Data.Url. Kolom payment_url hanya 255 karakter,
        // sedangkan payload QRIS lebih panjang — bila disimpan di sana MySQL
        // menolak dengan "Data too long for column 'payment_url'" dan seluruh
        // pembuatan pembayaran QRIS gagal. Payload seperti itu disimpan
        // sebagai qr_string (kolom TEXT), bukan sebagai payment_url.
        $url      = isset($data['Url']) ? trim((string) $data['Url']) : '';
        $qrString = $this->extractQrString($data);

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $qrString = $qrString ?: $url;
            $url = '';
        }

        $paymentUrl = $url !== '' && strlen($url) <= 255 ? $url : null;
        if ($paymentUrl === null && $url !== '') {
            $qrString = $qrString ?: $url;
        }

        return [
            'success'     => true,
            'error'       => null,
            'payment_id'  => (string) ($data['TransactionId'] ?? ''),
            'va_number'   => isset($data['PaymentNo']) && (string) $data['PaymentNo'] !== ''
                ? (string) $data['PaymentNo']
                : null,
            'qr_string'   => $qrString,
            'payment_url' => $paymentUrl,
            'expired_at'  => isset($data['Expired']) && (string) $data['Expired'] !== ''
                ? $this->toIso((string) $data['Expired'], $expiresAt)
                : $expiresAt->toIso8601String(),
            'raw'         => $res['raw'],
        ];
    }

    // ─── Buat sesi Redirect Payment (halaman iPaymu) ──────────────────

    /**
     * Redirect Payment: POST /api/v2/payment → Data.SessionID + Data.Url.
     *
     * Dipakai bila Direct Payment diblokir sistem risiko iPaymu ("Suspicious
     * buyer", Status 503). Body redirect TIDAK memuat name/phone/email buyer —
     * identitas bukan parameter wajib di jalur ini — sehingga pemeriksaan yang
     * memblokir Direct tidak ikut berjalan. Pelanggan memilih kanal pembayaran
     * di halaman iPaymu, lalu status dikonfirmasi lewat callback + inquiry.
     *
     * @param array{
     *   amount:int, reference:string, items:array, comments:?string
     * } $input
     * @return array{
     *   success:bool, error:?string, session_id:?string, payment_url:?string,
     *   expired_at:?string, http_code:?int, raw:?array
     * }
     */
    public function createRedirectPayment(array $input): array
    {
        $none = [
            'success' => false, 'error' => null, 'session_id' => null,
            'payment_url' => null, 'expired_at' => null, 'http_code' => null, 'raw' => null,
        ];

        if (!iPaymuService::isConfigured()) {
            $none['error'] = 'iPaymu belum terkonfigurasi. Set kredensial di Admin > Pengaturan.';
            return $none;
        }

        $base     = rtrim((string) config('app.url'), '/');
        $notify   = $base . '/api/ipaymu/callback';
        $return   = $base . '/api/ipaymu/return';
        $cancel   = $base . '/api/ipaymu/cancel';
        $hours    = max(1, (int) config('ipaymu.payment_expired_hours', 24));

        $product = [];
        $qty = [];
        $price = [];
        $description = [];
        foreach ((array) $input['items'] as $item) {
            $product[]     = (string) ($item['name'] ?? 'Biaya Pengiriman City Courier');
            $qty[]         = (int) ($item['quantity'] ?? 1);
            $price[]       = (int) ($item['price'] ?? $input['amount']);
            $description[] = (string) ($input['comments'] ?? 'Pengiriman City Courier');
        }

        $payload = [
            'product'     => $product,
            'qty'         => $qty,
            'price'       => $price,
            'description' => $description,
            'returnUrl'   => $return,
            'notifyUrl'   => $notify,
            'cancelUrl'   => $cancel,
            'referenceId' => (string) $input['reference'],
            'expired'     => $hours,
        ];

        $res = $this->ipaymu->createRedirectPayment($payload);
        if (!$res['success'] || !is_array($res['data'])) {
            Log::warning('iPaymu payment/redirect ditolak', [
                'reference' => $payload['referenceId'],
                'http_code' => $res['http_code'] ?? null,
                'error'     => $res['error'] ?? null,
                'provider'  => $res['raw'] ?? null,
                'payload'   => $payload,
            ]);

            $none['error']     = $res['error'] ?? 'iPaymu menolak pembuatan sesi pembayaran.';
            $none['http_code'] = $res['http_code'] ?? null;
            $none['raw']       = $res['raw'];
            return $none;
        }

        $data = $res['data'];
        $url  = (string) ($data['Url'] ?? $data['url'] ?? '');
        $sid  = (string) ($data['SessionID'] ?? $data['SessionId'] ?? $data['session_id'] ?? '');

        if ($url === '') {
            $none['error'] = 'iPaymu tidak mengembalikan URL pembayaran.';
            $none['raw']   = $res['raw'];
            return $none;
        }

        return [
            'success'    => true,
            'error'      => null,
            'session_id' => $sid !== '' ? $sid : null,
            'payment_url' => $url,
            'expired_at' => now()->addHours($hours)->toIso8601String(),
            'http_code'  => $res['http_code'] ?? null,
            'raw'        => $res['raw'],
        ];
    }

    // ─── Sinkronisasi status (GET /payment/status & webhook) ─────────

    /**
     * Inquiry resmi ke iPaymu (Check Transaction) untuk satu Payment,
     * lalu petakan ke status internal payments: pending|paid|expired|canceled|failed.
     *
     * @return array{status:string, known:bool, error:?string}
     */
    public function syncStatus(Payment $payment): array
    {
        if (!iPaymuService::isConfigured() || empty($payment->payment_id)) {
            return ['status' => $payment->status, 'known' => false, 'error' => 'iPaymu belum terkonfigurasi.'];
        }

        $inquiry = $this->ipaymu->inquire((string) $payment->payment_id);
        $rawCode = $inquiry['raw']['Data']['Status']
            ?? $inquiry['raw']['data']['Status']
            ?? $inquiry['raw']['Data']['status']
            ?? $inquiry['raw']['data']['status']
            ?? null;

        return [
            'status' => $this->mapProviderStatus($inquiry, $payment->status),
            'known'  => $inquiry['verdict'] === 'known',
            'error'  => $inquiry['error'] ?? null,
            // Kode status resmi iPaymu (1/6 sukses · 0/7 pending · 2 cancel
            // · -2 expired · 3 refund · 4 error · 5 failed) untuk audit admin.
            'provider_code' => $rawCode !== null ? (string) $rawCode : null,
            'raw'           => $inquiry['raw'] ?? null,
        ];
    }

    /**
     * Status final untuk callback webhook: inquiry dianggap sumber kebenaran;
     * bila inquiry gagal/unknown, kembalikan status webhook (endpoint sudah
     * terautentikasi signature).
     */
    public function webhookFinalStatus(string $transactionId, string $webhookStatus): string
    {
        if (!iPaymuService::isConfigured()) {
            return $this->webhookStatusToPaymentStatus($webhookStatus);
        }

        $inquiry = $this->ipaymu->inquire($transactionId);
        if (($inquiry['verdict'] ?? 'unknown') !== 'known') {
            return $this->webhookStatusToPaymentStatus($webhookStatus);
        }

        return $this->mapProviderStatus($inquiry, $this->webhookStatusToPaymentStatus($webhookStatus));
    }

    /**
     * Peta hasil inquiry (envelope resmi) ke status payments.
     * Kode resmi: 1/6 success · 0/7 pending · 2 cancelled · -2 expired
     * · 3 refund · 4 error · 5 failed.
     */
    private function mapProviderStatus(array $inquiry, string $fallback): string
    {
        if (($inquiry['verdict'] ?? 'unknown') !== 'known') {
            return $fallback;
        }

        $internal = $inquiry['status'] ?? 'unknown';
        if ($internal === 'success') {
            return 'paid';
        }
        if ($internal === 'pending') {
            return 'pending';
        }

        // failed: bedakan expired (-2) dan cancelled (2) dari raw envelope.
        $rawNum = $inquiry['raw']['Data']['Status']
            ?? $inquiry['raw']['data']['Status']
            ?? $inquiry['raw']['Data']['status']
            ?? $inquiry['raw']['data']['status']
            ?? null;

        if ($rawNum !== null && (ctype_digit((string) $rawNum) || (string) $rawNum === '-2')) {
            return match ((int) $rawNum) {
                -2      => 'expired',
                2       => 'canceled',
                default => 'failed',
            };
        }

        return 'failed';
    }

    /** Status teks webhook → status payments. */
    private function webhookStatusToPaymentStatus(string $webhookStatus): string
    {
        return match ($webhookStatus) {
            'success'   => 'paid',
            'failed'    => 'failed',
            'pending', 'processing' => 'pending',
            default     => 'pending',
        };
    }

    /** Ambil QR string dari berbagai kemungkinan key respons iPaymu. */
    private function extractQrString(array $data): ?string
    {
        foreach (['QrString', 'QRString', 'QrCode', 'QRCode', 'qr_string', 'qrString', 'qris', 'Qris'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }
        return null;
    }

    /** Konversi "Y-m-d H:i:s" iPaymu ke ISO8601 (fallback: waktu lokal). */
    private function toIso(string $value, \Illuminate\Support\Carbon $fallback): string
    {
        try {
            return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return $fallback->toIso8601String();
        }
    }
}
