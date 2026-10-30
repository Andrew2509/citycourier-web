<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
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
    public function __construct(private readonly IpaymuPaymentService $paymentService) {}

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

        $customer = $request->customer ?? [
            'name'  => $user->name ?? 'Customer',
            'email' => $user->email ?? 'customer@example.com',
            'phone' => $user->phone ?? '08123456789',
        ];

        $items = $request->items ?? [[
            'name'     => 'Biaya Pengiriman City Courier',
            'quantity' => 1,
            'price'    => (int) $request->amount,
        ]];

        // Referensi unik per percobaan pembayaran (terkirim sebagai
        // referenceId ke iPaymu; muncul kembali di callback sebagai reference_id).
        $refId = $request->shipment_id ?? ($request->order_id ?? 'REQ');
        $reference = 'CC-' . $refId . '-' . now()->format('YmdHis');

        $result = $this->paymentService->createPayment([
            'payment_type' => $paymentType,
            'channel_code' => $request->channel_code,
            'amount'       => (int) $request->amount,
            'reference'    => $reference,
            'customer'     => $customer,
            'items'        => $items,
            'comments'     => 'Pembayaran pengiriman City Courier (' . $refId . ')',
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Gagal membuat pembayaran',
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
            'va_number'    => $result['va_number'],
            'qr_string'    => $result['qr_string'],
            'payment_url'  => $result['payment_url'],
            'expired_at'   => $result['expired_at'] ? \Illuminate\Support\Carbon::parse($result['expired_at']) : null,
            'metadata'     => [
                'ipaymu_reference_id' => $reference,
                'customer'            => $customer,
                'items'               => $items,
            ],
        ];

        if ($request->shipment_id && Schema::hasColumn('payments', 'shipment_id')) {
            $paymentData['shipment_id'] = $request->shipment_id;
        }

        $payment = Payment::create($paymentData);

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
                'va_number'    => $payment->va_number,
                'qr_string'    => $payment->qr_string,
                'payment_url'  => $payment->payment_url,
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
                'va_number'    => $payment->va_number,
                'qr_string'    => $payment->qr_string,
                'expired_at'   => $payment->expired_at?->toISOString(),
                'paid_at'      => $payment->paid_at?->toISOString(),
            ],
        ]);
    }

    // ─── 4. Batalkan pembayaran ──────────────────────────────────
    /**
     * POST /api/payment/{paymentId}/cancel
     *
     * iPaymu v2 tidak menyediakan API pembatalan payment pending —
     * pembatalan ditandai LOKAL. Bila pelanggan ternyata tetap membayar
     * VA/QRIS, callback resmi iPaymu akan menandai 'paid' (uang sungguhan
     * tetap dihormati); pesanan tidak hilang.
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

        if (!$payment->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pembayaran dengan status PENDING yang bisa dibatalkan',
            ], 422);
        }

        $payment->update(['status' => 'canceled']);

        if ($payment->shipment_id) {
            Shipment::where('id', $payment->shipment_id)->update(['status' => 'cancelled']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dibatalkan',
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
