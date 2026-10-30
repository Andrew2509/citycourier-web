<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\BuyerIdentity;
use App\Services\IpaymuPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Pembayaran checkout (VA & QRIS) via iPaymu API v2.
 *
 * Kontrak respons dipertahankan sama seperti sebelumnya (dipakai aplikasi
 * Flutter tanpa perubahan): /payment/methods · /payment/create ·
 * /payment/status · /payment/cancel.
 *
 * Callback status resmi datang ke /api/ipaymu/callback (iPaymuWebhookController)
 * — notifyUrl dikirim pada saat create; endpoint ini SUDAH terdaftar di
 * dashboard iPaymu (domain terverifikasi). Status 'paid' HANYA setelah
 * konfirmasi resmi iPaymu (callback ber-signature / inquiry) — tanpa simulasi.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly IpaymuPaymentService $paymentService,
        private readonly \App\Services\PaymentCancellationService $paymentCancellation
    ) {}

    // ─── 1. Ambil daftar metode pembayaran ──────────────────────
    /**
     * GET /api/payment/methods
     * VA (BCA, BRI, BNI, ...) + QRIS dari saluran aktif iPaymu.
     */
    public function methods(): JsonResponse
    {
        $result = $this->paymentService->getPaymentMethods();

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Gagal mengambil metode pembayaran',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['methods'],
        ]);
    }

    // ─── 2. Buat pembayaran baru ─────────────────────────────────
    /**
     * POST /api/payment/create
     * Body: { order_id?, shipment_id, payment_type, channel_code?, amount, customer?, items? }
     */
    public function create(Request $request): JsonResponse
    {
        // Pembuatan pembayaran tidak boleh berakhir sebagai "Server Error"
        // tanpa jejak: transaksi iPaymu bisa saja sudah tercipta, sedangkan
        // penyimpanan lokal gagal. Semua pengecualian dicatat ke log lengkap
        // dengan konteks, dan klien menerima alasan singkat untuk dilaporkan.
        try {
            return $this->createPayment($request);
        } catch (\Throwable $e) {
            Log::error('PaymentController: gagal membuat pembayaran', [
                'user_id'      => optional($request->user())->id,
                'shipment_id'  => $request->input('shipment_id'),
                'payment_type' => $request->input('payment_type'),
                'channel'      => $request->input('channel_code'),
                'amount'       => $request->input('amount'),
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran di server.',
                // Bukan kredensial: pesan pengecualian (mis. nama kolom yang
                // terlalu panjang) supaya masalah bisa langsung diperbaiki.
                'reason'  => \Illuminate\Support\Str::limit($e->getMessage(), 300),
            ], 500);
        }
    }

    /**
     * Isi sebenarnya dari POST /api/payment/create (dibungkus create()).
     */
    private function createPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'order_id'          => 'nullable',
            'shipment_id'       => 'nullable|string',
            'payment_type'      => 'required|string',
            'channel_code'      => 'nullable|string|max:255',
            'amount'            => 'required|integer|min:10000',
            'customer'          => 'nullable|array',
            'customer.name'     => 'required_with:customer|string|max:100',
            'customer.email'    => 'required_with:customer|email',
            'customer.phone'    => 'required_with:customer|string|max:20',
            'items'             => 'nullable|array',
            'items.*.name'      => 'required_with:items|string',
            'items.*.quantity'  => 'required_with:items|integer|min:1',
            'items.*.price'     => 'required_with:items|integer|min:0',
        ]);

        if ($validator->fails()) {
            Log::warning('PaymentController: Validation failed', [
                'request' => $request->all(),
                'errors' => $validator->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // 'virtual_account'|'va' → 'va'; 'qris' → 'qris'.
        $paymentType = in_array(strtolower($request->payment_type), ['va', 'virtual_account', 'bank_transfer'])
            ? 'va'
            : 'qris';

        // Identitas buyer untuk iPaymu. Data shipment (nama/telepon yang
        // diketik pelanggan) lebih dipercaya daripada profil akun yang bisa
        // berisi placeholder seperti "User 1234" / `@citycourier.local`.
        // Placeholder tersebut membuat iPaymu menolak transaksi production
        // dengan pesan "Suspicious buyer".
        $customer = $this->resolveCustomer($request, $user);

        Log::info('PaymentController: identitas buyer iPaymu', [
            'user_id'     => $user->id,
            'shipment_id' => $request->input('shipment_id'),
            'buyer'       => $customer,
        ]);

        $items = $request->items ?? [[
            'name'     => 'Biaya Pengiriman City Courier',
            'quantity' => 1,
            'price'    => (int) $request->amount,
        ]];

        // Referensi unik per percobaan pembayaran (terkirim sebagai
        // referenceId ke iPaymu; muncul kembali di callback sebagai reference_id).
        $refId = $request->shipment_id ?? ($request->order_id ?? 'REQ');
        $reference = 'CC-' . $refId . '-' . now()->format('YmdHis');

        // Mode pembayaran iPaymu (Admin → Pengaturan → iPaymu, default "auto"):
        //   direct   → VA/QRIS dibuat langsung oleh server (perilaku lama)
        //   redirect → halaman pembayaran iPaymu (tanpa identitas buyer)
        //   auto     → coba Direct, jatuh ke Redirect bila diblokir
        //              sistem risiko iPaymu ("Suspicious buyer", 503)
        $mode = strtolower((string) (\App\Services\iPaymuService::credentials()['payment_mode'] ?? 'auto'));
        if (!in_array($mode, ['direct', 'redirect', 'auto'], true)) {
            $mode = 'auto';
        }

        $payload = [
            'payment_type' => $paymentType,
            'channel_code' => $request->channel_code,
            'amount'       => (int) $request->amount,
            'reference'    => $reference,
            'customer'     => $customer,
            'items'        => $items,
            'comments'     => 'Pembayaran pengiriman City Courier (' . $refId . ')',
        ];

        $usedMode = 'direct';
        $result   = null;

        if ($mode !== 'redirect') {
            $result = $this->paymentService->createPayment($payload);
        }

        // Fallback ke Redirect: hanya ketika penolakan berasal dari sistem
        // risiko iPaymu — bukan semua kegagalan, supaya error validasi tetap
        // terlihat apa adanya.
        $blockedByRisk = $result && !$result['success'] && (
            (int) ($result['http_code'] ?? 0) === 503
            || stripos((string) ($result['error'] ?? ''), 'suspicious') !== false
        );

        if ($mode === 'redirect' || ($mode === 'auto' && $blockedByRisk)) {
            if ($blockedByRisk) {
                Log::warning('PaymentController: Direct Payment diblokir iPaymu, beralih ke Redirect Payment', [
                    'reference' => $reference,
                    'http_code' => $result['http_code'] ?? null,
                    'error'     => $result['error'] ?? null,
                ]);
            }

            $redirect = $this->paymentService->createRedirectPayment($payload);

            if ($redirect['success']) {
                $usedMode = 'redirect';
                $result = [
                    'success'     => true,
                    'error'       => null,
                    'payment_id'  => $redirect['session_id'],
                    'va_number'   => null,
                    'qr_string'   => null,
                    'payment_url' => $redirect['payment_url'],
                    'expired_at'  => $redirect['expired_at'],
                    'http_code'   => $redirect['http_code'],
                    'raw'         => $redirect['raw'],
                ];
            } elseif ($mode === 'redirect' || !$result) {
                // Mode redirect murni (atau Direct belum dicoba): laporkan
                // kegagalan redirect apa adanya.
                $result = [
                    'success'     => false,
                    'error'       => $redirect['error'] ?? 'Gagal membuat halaman pembayaran iPaymu',
                    'http_code'   => $redirect['http_code'] ?? null,
                    'raw'         => $redirect['raw'] ?? null,
                ];
            } else {
                // Redirect juga gagal → pertahankan error Direct (penyebab awal).
                Log::error('PaymentController: Redirect Payment juga gagal', [
                    'reference' => $reference,
                    'error'     => $redirect['error'] ?? null,
                    'http_code' => $redirect['http_code'] ?? null,
                ]);
            }
        }

        $result = $result ?? [
            'success'   => false,
            'error'     => 'Gagal membuat pembayaran',
            'http_code' => null,
            'raw'       => null,
        ];

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Gagal membuat pembayaran',
                // Data diagnostik untuk penolakan provider (mis. "Suspicious
                // buyer"): identitas yang kita kirim + jawaban mentah iPaymu.
                // Dikembalikan ke pengguna yang sama pemilik shipment, berisi
                // data yang memang sudah dikirim ke iPaymu — bukan kredensial.
                'debug' => [
                    'buyer'      => $customer,
                    'http_code'  => $result['http_code'] ?? null,
                    'provider'   => $result['raw'] ?? null,
                    'mode'       => $mode,
                    // Endpoint persis yang dipanggil, supaya bisa dipastikan
                    // apakah permintaan benar-benar ke /api/v2/payment/direct
                    // dan ke host yang benar (production vs sandbox).
                    'endpoint' => [
                        'direct'   => rtrim((string) (\App\Services\iPaymuService::credentials()['base_url'] ?? ''), '/')
                            . (string) config('ipaymu.payment_direct_path', '/api/v2/payment/direct'),
                        'redirect' => rtrim((string) (\App\Services\iPaymuService::credentials()['base_url'] ?? ''), '/')
                            . (string) config('ipaymu.payment_redirect_path', '/api/v2/payment'),
                    ],
                ],
            ], 502);
        }

        $paymentData = [
            'user_id'      => $user->id,
            'order_id'     => $request->order_id,
            'payment_id'   => $result['payment_id'],       // TransactionId iPaymu
            'payment_type' => $paymentType === 'va' ? 'bank_transfer' : 'qris',
            'channel_code' => $paymentType === 'va' ? strtoupper((string) $request->channel_code) : 'QRIS',
            'amount'       => (int) $request->amount,
            'status'       => 'pending',
            'va_number'    => $result['va_number'] ?? null,
            'qr_string'    => $result['qr_string'] ?? null,
            'payment_url'  => $result['payment_url'] ?? null,
            'expired_at'   => !empty($result['expired_at']) ? \Illuminate\Support\Carbon::parse($result['expired_at']) : null,
            'metadata'     => [
                'ipaymu_reference_id' => $reference,
                'payment_mode'        => $usedMode,
                'customer'            => $customer,
                'items'               => $items,
            ],
        ];

        if ($request->shipment_id && Schema::hasColumn('payments', 'shipment_id')) {
            $paymentData['shipment_id'] = $request->shipment_id;
        }

        try {
            $payment = Payment::create($paymentData);
        } catch (\Throwable $e) {
            // Transaksi iPaymu sudah dibuat; jangan mengubah status apa pun,
            // catat data yang gagal disimpan agar bisa direkonsiliasi manual.
            Log::error('PaymentController: pembayaran iPaymu gagal disimpan', [
                'reference'    => $reference,
                'payment_data' => $paymentData,
                'provider'     => $result['raw'] ?? null,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Pembayaran dibuat di iPaymu tetapi gagal disimpan di server.',
                'ref'     => $reference,
                'reason'  => \Illuminate\Support\Str::limit($e->getMessage(), 300),
            ], 500);
        }

        // Metode pembayaran yang benar-benar dipakai dicatat ke pesanan, supaya
        // detail pesanan tidak lagi menampilkan "COD" untuk pembayaran QRIS/VA.
        if (!empty($paymentData['shipment_id'])) {
            try {
                \App\Models\Shipment::whereKey($paymentData['shipment_id'])
                    ->update(['payment_method' => $paymentType === 'va' ? 'VA' : 'QRIS']);
            } catch (\Throwable $e) {
                Log::warning('Gagal menyimpan metode pembayaran ke pesanan', [
                    'shipment_id' => $paymentData['shipment_id'],
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dibuat',
            'data'    => [
                'id'           => $payment->id,
                'payment_id'   => $payment->payment_id,
                'payment_type' => $payment->payment_type,
                'channel_code' => $payment->channel_code,
                'amount'       => $payment->amount,
                'status'       => $payment->status,
                // Pesanan pemilik transaksi: dipakai aplikasi agar tombol
                // "Batalkan Pesanan" membatalkan pesanan (satu pintu lewat
                // /shipments/{id}/cancel), bukan hanya menutup pembayaran.
                'shipment_id'  => $payment->shipment_id,
                'va_number'    => $payment->va_number,
                'qr_string'    => $payment->qr_string,
                'payment_url'  => $payment->payment_url,
                'payment_mode' => $usedMode,
                'return_url'   => $usedMode === 'redirect' ? $this->ipaymuReturnUrl() : null,
                'cancel_url'   => $usedMode === 'redirect' ? $this->ipaymuCancelUrl() : null,
                'expired_at'   => $payment->expired_at?->toIso8601String(),
                'instructions' => $this->getInstructions($payment),
            ],
        ], 201);
    }

    // ─── 3. Cek status pembayaran ────────────────────────────────
    /**
     * GET /api/payment/{paymentId}/status
     * Status final sinkron dari Check Transaction iPaymu (POST /api/v2/transaction).
     */
    public function status(Request $request, ?string $paymentId = null): JsonResponse
    {
        $paymentId = $paymentId ?: $request->query('payment_id');

        if (!$paymentId) {
            return response()->json(['success' => false, 'message' => 'Payment ID required'], 400);
        }

        $payment = Payment::where('payment_id', $paymentId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Pembayaran tidak ditemukan'], 404);
        }

        // Sudah final → tidak perlu panggil API.
        if (in_array($payment->status, ['paid', 'expired', 'canceled', 'failed'])) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => $payment->status, 'paid_at' => $payment->paid_at?->toISOString()],
            ]);
        }

        // Redirect Payment: `payment_id` yang tersimpan adalah SessionID iPaymu,
        // bukan TransactionId — Check Transaction belum bisa dipanggil sampai
        // callback resmi memberi `trx_id`. Selama itu status jujur tetap
        // 'pending' (tanpa mengarang hasil); webhook yang akan mengubahnya.
        $mode = (string) ($payment->metadata['payment_mode'] ?? 'direct');
        $trxId = $payment->metadata['ipaymu_trx_id'] ?? null;

        if ($mode === 'redirect' && empty($trxId)) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'id'           => $payment->id,
                    'payment_id'   => $payment->payment_id,
                    'status'       => $payment->status,
                    'amount'       => $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'channel_code' => $payment->channel_code,
                    'payment_url'  => $payment->payment_url,
                    'payment_mode' => 'redirect',
                    'return_url'   => $this->ipaymuReturnUrl(),
                    'cancel_url'   => $this->ipaymuCancelUrl(),
                    'expired_at'   => $payment->expired_at?->toISOString(),
                    'paid_at'      => $payment->paid_at?->toISOString(),
                ],
            ]);
        }

        // Inquiry resmi iPaymu.
        $sync = $this->paymentService->syncStatus($payment);
        $newStatus = $sync['status'];

        if ($newStatus !== $payment->status) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'paid') {
                $updateData['paid_at'] = now();
                $this->markOrderShipmentPaid($payment);
            }
            $payment->update($updateData);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $payment->id,
                'payment_id'   => $payment->payment_id,
                'status'       => $payment->fresh()->status,
                'amount'       => $payment->amount,
                'payment_type' => $payment->payment_type,
                'channel_code' => $payment->channel_code,
                'shipment_id'  => $payment->shipment_id,
                'va_number'    => $payment->va_number,
                'qr_string'    => $payment->qr_string,
                'payment_url'  => $payment->payment_url,
                'payment_mode' => $mode,
                'expired_at'   => $payment->expired_at?->toISOString(),
                'paid_at'      => $payment->paid_at?->toISOString(),
            ],
        ]);
    }

    // ─── 4. Batalkan pembayaran ──────────────────────────────────
    /**
     * POST /api/payment/{paymentId}/cancel
     *
     * iPaymu v2 TIDAK punya API cancel/void/refund. Endpoint ini karena itu:
     *  1. menanyakan status resmi lewat Check Transaction,
     *  2. menutup pembayaran lokal bila iPaymu masih pending (kode bayar akan
     *     kedaluwarsa sendiri di sisi iPaymu),
     *  3. MENOLAK pembatalan biasa bila ternyata sudah dibayar → jalur refund,
     *  4. tidak mengubah apa pun bila status tidak bisa dipastikan.
     *
     * Pembayaran yang sudah dibatalkan tidak dikirim ulang ke iPaymu
     * (idempotent), dan status pesanan tidak diubah dari sini agar pembatalan
     * pesanan tetap satu pintu lewat /shipments/{id}/cancel.
     */
    public function cancel(Request $request, ?string $paymentId = null): JsonResponse
    {
        $paymentId = $paymentId ?: $request->payment_id;

        if (!$paymentId) {
            return response()->json(['success' => false, 'message' => 'Payment ID required'], 400);
        }

        $payment = Payment::where('payment_id', $paymentId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Pembayaran tidak ditemukan'], 404);
        }

        $outcome = $this->paymentCancellation->cancelPayment($payment, 'customer', 'Pelanggan membatalkan pembayaran.');

        if ($outcome['result'] === \App\Services\PaymentCancellationService::UNVERIFIED) {
            return response()->json([
                'success'    => false,
                'error_code' => 'payment_status_unknown',
                'message'    => $outcome['message'],
                'payment'    => $outcome['payment'],
            ], 409);
        }

        if ($outcome['result'] === \App\Services\PaymentCancellationService::REQUIRES_REFUND) {
            return response()->json([
                'success'    => false,
                'error_code' => 'payment_already_paid',
                'message'    => $outcome['message'],
                'payment'    => $outcome['payment'],
            ], 422);
        }

        return response()->json([
            'success'     => true,
            'already_final' => $outcome['result'] === \App\Services\PaymentCancellationService::ALREADY_FINAL,
            'message'     => $outcome['message'],
            'payment'     => $outcome['payment'],
        ]);
    }

    // ─── 5. Callback legacy Komerce (kompatibilitas riwayat) ────
    /**
     * POST /api/payment/callback (publik).
     * Dipertahankan untuk pembayaran Komerce lama yang masih bisa menerima
     * callback terbayar. Pembayaran iPaymu baru tidak memakai endpoint ini.
     */
    public function callback(Request $request): JsonResponse
    {
        $incomingKey = $request->header('x-callback-key') ?? $request->input('callback_api_key');
        $expectedKey = config('services.komerce_payment.callback_key');

        if ($incomingKey !== $expectedKey) {
            Log::warning('Komerce Payment: Callback dengan key tidak valid', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->all();
        Log::info('Komerce Payment: Callback received', $data);

        $paymentId = $data['payment_id'] ?? null;
        $status    = strtolower($data['status'] ?? '');

        if ($paymentId && $status) {
            $payment = Payment::where('payment_id', $paymentId)->first();

            if ($payment && $payment->status !== $status) {
                $updateData = [
                    'status'        => $status,
                    'callback_data' => $data,
                ];

                if ($status === 'paid') {
                    $updateData['paid_at'] = now();
                    $this->markOrderShipmentPaid($payment);
                }

                $payment->update($updateData);
            }
        }

        return response()->json(['message' => 'OK']);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Susun identitas buyer yang aman untuk iPaymu.
     *
     * Urutan prioritas:
     * - name  : customer (request) → shipment.customer_name → shipment.sender_name → user.name
     * - phone : customer (request) → shipment.customer_phone → shipment.sender_phone → user.phone
     * - email : customer (request) → user.email bila valid → alamat valid di domain merchant
     *
     * @return array{name:string,email:string,phone:string}
     */
    private function resolveCustomer(Request $request, $user): array
    {
        $override = $request->input('customer');
        $override = is_array($override) ? $override : [];

        $shipment = $this->findShipment($request, (int) $user->id);

        $name = BuyerIdentity::name(
            $override['name'] ?? null,
            $shipment?->customer_name,
            $shipment?->sender_name,
            $user->name,
        );

        $phone = BuyerIdentity::phone(
            $override['phone'] ?? null,
            $shipment?->customer_phone,
            $shipment?->sender_phone,
            $user->phone,
        );

        $email = BuyerIdentity::email(
            $override['email'] ?? null,
            $user->email,
            $phone,
        );

        return ['name' => $name, 'email' => $email, 'phone' => $phone];
    }

    /** Shipment milik user yang sedang login (null bila tidak ditemukan). */
    private function findShipment(Request $request, int $userId): ?Shipment
    {
        $shipmentId = $request->input('shipment_id');
        if ($shipmentId === null || $shipmentId === '') {
            return null;
        }

        return Shipment::query()
            ->where('id', $shipmentId)
            ->where('user_id', $userId)
            ->first();
    }

    /** Tandai order/shipment terbayar bila relasi tersedia. */
    private function markOrderShipmentPaid(Payment $payment): void
    {
        if ($payment->order_id) {
            Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
        }
        if ($payment->shipment_id) {
            Shipment::where('id', $payment->shipment_id)->update(['status' => 'confirmed']);
        }
    }

    private function ipaymuReturnUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/ipaymu/return';
    }

    private function ipaymuCancelUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/ipaymu/cancel';
    }

    private function getInstructions(Payment $payment): array
    {
        if ($payment->payment_type === 'qris') {
            return [
                'Scan QR code menggunakan aplikasi e-wallet atau m-banking (QRIS)',
                'Masukkan nominal: ' . $payment->formatted_amount,
                'Konfirmasi pembayaran',
                'Status otomatis terupdate setelah pembayaran berhasil',
                'Berlaku hingga: ' . $payment->expired_at?->format('d M Y H:i'),
            ];
        }

        $bankName = match ($payment->channel_code) {
            'BCA'     => 'BCA',
            'BNI'     => 'BNI',
            'BRI'     => 'BRI',
            'MANDIRI' => 'Mandiri',
            'PERMATA' => 'Permata',
            'CIMB'    => 'CIMB Niaga',
            'BSI'     => 'BSI',
            'BTN'     => 'BTN',
            'BAG'     => 'Bank Artha Graha',
            'BMI'     => 'Bank Muamalat Indonesia',
            'BPD_BALI'=> 'BPD Bali',
            'DANAMON' => 'Danamon',
            default   => $payment->channel_code ?? 'Bank',
        };

        return [
            "Transfer ke Virtual Account {$bankName}",
            "Nomor VA: {$payment->va_number}",
            "Nominal: {$payment->formatted_amount}",
            'Pembayaran dikonfirmasi otomatis setelah transfer',
            'Berlaku hingga: ' . $payment->expired_at?->format('d M Y H:i'),
        ];
    }
}
