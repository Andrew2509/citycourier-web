<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Services\KomercePaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(private readonly KomercePaymentService $paymentService) {}

    // ─── 1. Ambil daftar metode pembayaran ──────────────────────
    /**
     * GET /api/payment/methods
     * Mengembalikan daftar VA dan QRIS yang tersedia.
     */
    public function methods(): JsonResponse
    {
        $result = $this->paymentService->getPaymentMethods();

        if (($result['meta']['code'] ?? 0) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['meta']['message'] ?? 'Gagal mengambil metode pembayaran',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data'    => $result['data'] ?? [],
        ]);
    }

    // ─── 2. Buat pembayaran baru ─────────────────────────────────
    /**
     * POST /api/payment/create
     * Body: { order_id?, payment_type, channel_code?, amount, customer, items? }
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'order_id'          => 'nullable',
            'shipment_id'       => 'nullable|string',
            'payment_type'      => 'required|string',
            'channel_code'      => 'required_if:payment_type,bank_transfer,virtual_account|nullable|string|max:255',
            'amount'            => 'required|integer|min:10000',
            'customer'          => 'nullable|array',
            'customer.name'     => 'required_with:customer|string|max:100',
            'customer.email'    => 'required_with:customer|email',
            'customer.phone'    => 'required_with:customer|string|max:20',
            'items'             => 'nullable|array',
            'items.*.name'      => 'required_with:items|string',
            'items.*.quantity'  => 'required_with:items|integer|min:1',
            'items.*.price'     => 'required_with:items|integer|min:0',
            'expiry_duration'   => 'nullable|integer|min:3600',
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

        // Ambil data customer dari request atau fallback ke user yang login
        $customer = $request->customer ?? [
            'name'  => $user->name ?? 'Customer',
            'email' => $user->email ?? 'customer@example.com',
            'phone' => $user->phone ?? '08123456789', // Fallback phone jika kosong
        ];

        // Jika items tidak dikirim, buat otomatis dari amount
        $items = $request->items ?? [[
            'name'     => 'Biaya Pengiriman City Courier',
            'quantity' => 1,
            'price'    => $request->amount,
        ]];

        // Generate order_id unik untuk Komerce (bisa berasal dari order atau shipment)
        $refId = $request->shipment_id ?? $request->order_id ?? 'REQ';
        $orderId = 'CC-' . $refId . '-' . now()->format('YmdHis');

        // Map payment_type to Komerce types if needed
        $komercePaymentType = match ($request->payment_type) {
            'virtual_account', 'va' => 'bank_transfer',
            'ewallet', 'e_wallet', 'qris' => 'qris',
            default => $request->payment_type,
        };

        $mappedChannel = null;
        $payload = [
            'order_id'     => $orderId,
            'payment_type' => $komercePaymentType,
            'amount'       => $request->amount,
            'customer'     => $customer,
            'items'        => $items,
        ];

        // Include channel_code for bank_transfer (VA)
        if ($komercePaymentType === 'bank_transfer' || $request->payment_type === 'virtual_account' || $request->payment_type === 'va') {
            $rawChannel = strtolower($request->channel_code);
            $mappedChannel = match ($rawChannel) {
                'bank_negara_indonesia', 'bni_va', 'bni' => 'BNI',
                'bank_central_asia', 'bca_va', 'bca'     => 'BCA',
                'bank_rakyat_indonesia', 'bri_va', 'bri' => 'BRI',
                'bank_mandiri', 'mandiri_va', 'mandiri'  => 'MANDIRI',
                'bank_permata', 'permata_va', 'permata'  => 'PERMATA',
                'bank_danamon', 'danamon_va', 'danamon'  => 'DANAMON',
                'bank_cimb_niaga', 'cimb_va', 'cimb'     => 'CIMB',
                default => strtoupper($request->channel_code),
            };
            $payload['channel_code'] = $mappedChannel;
        }

        if ($request->expiry_duration) {
            $payload['expiry_duration'] = $request->expiry_duration;
        }

        // Tambahkan callback_url jika ada di config
        $callbackUrl = config('app.url') . '/api/payment/callback';
        $payload['callback_url'] = $callbackUrl;

        $result = $this->paymentService->createPayment($payload);

        if (($result['meta']['code'] ?? 0) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['meta']['message'] ?? 'Gagal membuat pembayaran',
            ], 502);
        }

        $apiData = $result['data'] ?? [];

        // Simpan ke database lokal
        $paymentData = [
            'user_id'      => $user->id,
            'order_id'     => $request->order_id,
            'payment_id'   => $apiData['payment_id'] ?? null,
            'payment_type' => $komercePaymentType,
            'channel_code' => $mappedChannel,
            'amount'       => $request->amount,
            'status'       => 'pending',
            'va_number'    => $apiData['va_number'] ?? null,
            'qr_string'    => $apiData['qr_string'] ?? null,
            'payment_url'  => isset($apiData['payment_id'])
                ? $this->getPaymentPageUrl($apiData['payment_id'])
                : null,
            'expired_at'   => now()->addSeconds($request->payment_type === 'qris' ? 300 : ($request->expiry_duration ?? 86400)),
            'metadata'     => [
                'komerce_order_id' => $orderId,
                'customer'         => $request->customer,
                'items'            => $items,
            ],
        ];

        // Only add shipment_id if column exists (handles cases where migration is not yet run)
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

        // Jika sudah paid/expired, kembalikan status dari DB tanpa hit API
        if (in_array($payment->status, ['paid', 'expired', 'canceled'])) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => $payment->status, 'paid_at' => $payment->paid_at?->toISOString()],
            ]);
        }

        // Hit API Komerce untuk status terbaru
        $result = $this->paymentService->getPaymentStatus($paymentId);
        $apiData = $result['data'] ?? [];
        $newStatus = strtolower($apiData['status'] ?? 'pending');

        // Update status di DB jika berubah
        if ($newStatus !== $payment->status) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'paid') {
                $updateData['paid_at'] = now();
                // Update order status ke paid jika ada relasi
                if ($payment->order_id) {
                    Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
                }
                if ($payment->shipment_id) {
                    Shipment::where('id', $payment->shipment_id)->update(['status' => 'confirmed']);
                }
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
                'expired_at'   => $payment->expired_at?->toISOString(),
                'paid_at'      => $payment->paid_at?->toISOString(),
            ],
        ]);
    }

    // ─── 4. Batalkan pembayaran ──────────────────────────────────
    /**
     * POST /api/payment/{paymentId}/cancel
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

        $result = $this->paymentService->cancelPayment($paymentId, $request->reason ?? 'Canceled by user');

        if (($result['meta']['code'] ?? 0) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['meta']['message'] ?? 'Gagal membatalkan pembayaran',
            ], 502);
        }

        $payment->update(['status' => 'canceled']);

        // Update shipment status jika ada
        if ($payment->shipment_id) {
            Shipment::where('id', $payment->shipment_id)->update(['status' => 'cancelled']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dibatalkan',
        ]);
    }

    // ─── 5. Callback / Webhook dari Komerce ─────────────────────
    /**
     * POST /api/payment/callback  (public, tidak butuh auth)
     * Komerce mengirim notifikasi pembayaran ke endpoint ini.
     */
    public function callback(Request $request): JsonResponse
    {
        // Verifikasi callback_api_key
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
                    // Sync ke order jika ada
                    if ($payment->order_id) {
                        Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
                    }
                    if ($payment->shipment_id) {
                        Shipment::where('id', $payment->shipment_id)->update(['status' => 'confirmed']);
                    }
                }

                $payment->update($updateData);
            }
        }

        return response()->json(['message' => 'OK']);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function getPaymentPageUrl(string $paymentId): string
    {
        $env = config('services.komerce_payment.env', 'sandbox');
        $basePayUrl = $env === 'production'
            ? 'https://pay.komerce.id'
            : 'https://pay-sandbox.komerce.id';

        return "{$basePayUrl}/{$paymentId}";
    }

    private function getInstructions(Payment $payment): array
    {
        if ($payment->payment_type === 'qris') {
            return [
                'Scan QR code menggunakan aplikasi e-wallet (GoPay, OVO, Dana, dll)',
                'Masukkan nominal: ' . $payment->formatted_amount,
                'Konfirmasi pembayaran',
                'QR code berlaku 5 menit',
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
