<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payment\Services\XenditPaymentService;
use App\Services\BuyerIdentity;
use App\Services\PaymentCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembayaran checkout pesanan (VA, QRIS, E-Wallet) menggunakan Xendit sebagai satu-satunya provider aktif.
 * Webhook konfirmasi diterima lewat POST /api/v1/webhooks/xendit/payment.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly XenditPaymentService $xenditPaymentService,
        private readonly ProviderResolver $providerResolver,
        private readonly PaymentCancellationService $paymentCancellation
    ) {}

    /**
     * GET /api/payment/methods
     */
    public function methods(): JsonResponse
    {
        if ($this->providerResolver->isPaymentProviderDisabled('xendit')) {
            return response()->json([
                'success' => true,
                'xendit_enabled' => false,
                'disabled' => true,
                'gateway' => 'xendit',
                'message' => 'Pembayaran Xendit (VA, QRIS & E-Wallet) sedang dinonaktifkan oleh Admin.',
                'data' => [],
            ]);
        }

        $mode = $this->providerResolver->currentMode();
        if ($mode === 'production' && ! $this->xenditPaymentService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Xendit belum dikonfigurasi oleh Admin untuk mode production.',
            ], 502);
        }

        $methods = $this->xenditPaymentService->availableCheckoutMethods($mode);

        return response()->json([
            'success' => true,
            'xendit_enabled' => true,
            'disabled' => false,
            'gateway' => 'xendit',
            'mode' => $mode,
            'data' => $methods,
        ]);
    }

    /**
     * POST /api/payment/create
     */
    public function create(Request $request): JsonResponse
    {
        try {
            return $this->createPayment($request);
        } catch (\Throwable $e) {
            Log::error('PaymentController: gagal membuat pembayaran Xendit', [
                'user_id' => optional($request->user())->id,
                'shipment_id' => $request->input('shipment_id'),
                'payment_type' => $request->input('payment_type'),
                'channel' => $request->input('channel_code'),
                'amount' => $request->input('amount'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran di server.',
                'reason' => Str::limit($e->getMessage(), 300),
            ], 500);
        }
    }

    private function createPayment(Request $request): JsonResponse
    {
        if ($this->providerResolver->isPaymentProviderDisabled('xendit')) {
            return response()->json([
                'success' => false,
                'error_code' => 'PROVIDER_DISABLED',
                'message' => 'Pembayaran Xendit sedang dinonaktifkan oleh Admin.',
            ], 403);
        }

        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'order_id' => 'nullable',
            'shipment_id' => 'nullable|string',
            'payment_type' => 'required|string',
            'channel_code' => 'nullable|string|max:255',
            'amount' => 'required|integer|min:10000',
            'idempotency_key' => 'nullable|string|max:120',
            'customer' => 'nullable|array',
            'customer.name' => 'required_with:customer|string|max:100',
            'customer.email' => 'required_with:customer|email',
            'customer.phone' => 'required_with:customer|string|max:20',
            'items' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rawType = strtolower((string) $request->payment_type);
        $paymentType = match ($rawType) {
            'va', 'virtual_account', 'bank_transfer' => 'va',
            'ewallet' => 'ewallet',
            default => str_contains(strtoupper((string) $request->channel_code), 'DANA')
                || str_contains(strtoupper((string) $request->channel_code), 'OVO')
                || str_contains(strtoupper((string) $request->channel_code), 'SHOPEE')
                || str_contains(strtoupper((string) $request->channel_code), 'LINKAJA')
                ? 'ewallet'
                : 'qris',
        };

        $channelCode = $this->xenditPaymentService->normalizeChannelCode(
            $paymentType,
            $request->channel_code ? (string) $request->channel_code : null
        );

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ($request->input('idempotency_key') ?? ''));

        if ($idempotencyKey !== '') {
            $existing = Payment::where('user_id', $user->id)
                ->where('metadata->idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran mengembalikan transaksi sebelumnya (idempotent).',
                    'data' => $this->formatPaymentResponse($existing, true),
                ], 200);
            }
        }

        $customer = $this->resolveCustomer($request, $user);
        $refId = $request->shipment_id ?? ($request->order_id ?? 'REQ');
        $reference = 'CC-'.$refId.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
        $effectiveIdempotency = $idempotencyKey !== '' ? $idempotencyKey : $reference;
        $expiryMinutes = (int) config('payment_wallet.topup.expiry_minutes', 60);

        try {
            $provider = $this->providerResolver->resolvePaymentProvider('xendit');
            $result = $provider->createTopupPayment([
                'reference' => $reference,
                'idempotency_key' => $effectiveIdempotency,
                'user_id' => $user->id,
                'shipment_id' => $request->input('shipment_id'),
                'order_id' => $request->input('order_id'),
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'payment_type' => $paymentType,
                'channel_code' => $channelCode,
                'amount' => (int) $request->amount,
                'expired_at' => now()->addMinutes($expiryMinutes),
                'purpose' => 'order_payment',
                'description' => 'Pembayaran Pengiriman City Courier ('.$refId.')',
            ]);
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'PROVIDER_DISABLED:')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PROVIDER_DISABLED',
                    'message' => substr($e->getMessage(), strlen('PROVIDER_DISABLED:')),
                ], 403);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        $paymentData = [
            'user_id' => $user->id,
            'order_id' => $request->order_id,
            'payment_id' => (string) ($result['provider_reference'] ?? $reference),
            'payment_type' => match ($paymentType) {
                'va' => 'bank_transfer',
                'ewallet' => 'ewallet',
                default => 'qris',
            },
            'channel_code' => $channelCode,
            'amount' => (int) $request->amount,
            'status' => 'pending',
            'va_number' => $result['va_number'] ?? null,
            'qr_string' => $result['qr_string'] ?? null,
            'payment_url' => $result['payment_url'] ?? null,
            'expired_at' => ! empty($result['expired_at']) ? \Illuminate\Support\Carbon::parse($result['expired_at']) : now()->addMinutes($expiryMinutes),
            'metadata' => [
                'provider' => (string) ($result['provider'] ?? 'xendit'),
                'mode' => (string) ($result['mode'] ?? $this->providerResolver->currentMode()),
                'is_simulated' => (bool) ($result['is_simulated'] ?? false),
                'xendit_reference_id' => $reference,
                'idempotency_key' => $effectiveIdempotency,
                'payment_mode' => ! empty($result['payment_url']) && empty($result['va_number']) && empty($result['qr_string']) ? 'redirect' : 'direct',
                'customer' => $customer,
            ],
        ];

        if ($request->shipment_id && Schema::hasColumn('payments', 'shipment_id')) {
            $paymentData['shipment_id'] = $request->shipment_id;
        }

        $payment = Payment::create($paymentData);

        if (! empty($paymentData['shipment_id'])) {
            try {
                Shipment::whereKey($paymentData['shipment_id'])
                    ->update(['payment_method' => match ($paymentType) {
                        'va' => 'VA',
                        'ewallet' => 'EWALLET',
                        default => 'QRIS',
                    }]);
            } catch (\Throwable $e) {
                Log::warning('Gagal menyimpan metode pembayaran ke pesanan', [
                    'shipment_id' => $paymentData['shipment_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran Xendit berhasil dibuat',
            'data' => $this->formatPaymentResponse($payment),
        ], 201);
    }

    /**
     * GET /api/payment/{paymentId}/status
     */
    public function status(Request $request, ?string $paymentId = null): JsonResponse
    {
        $paymentId = $paymentId ?: $request->query('payment_id');

        if (! $paymentId) {
            return response()->json(['success' => false, 'message' => 'Payment ID required'], 400);
        }

        $payment = Payment::where('payment_id', $paymentId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Pembayaran tidak ditemukan'], 404);
        }

        if (in_array($payment->status, ['paid', 'expired', 'canceled', 'failed'], true)) {
            return response()->json([
                'success' => true,
                'data' => $this->formatPaymentResponse($payment),
            ]);
        }

        $providerName = strtolower((string) ($payment->metadata['provider'] ?? 'xendit'));
        if ($providerName === 'xendit' && $this->xenditPaymentService->isConfigured()) {
            try {
                $sync = $this->xenditPaymentService->getPaymentStatus($payment->payment_id);
                $newStatus = (string) ($sync['status'] ?? 'pending');
                if ($newStatus !== $payment->status) {
                    $updateData = ['status' => $newStatus];
                    if ($newStatus === 'paid') {
                        $updateData['paid_at'] = now();
                        $this->markOrderShipmentPaid($payment);
                    }
                    $payment->update($updateData);
                }
            } catch (\Throwable $e) {
                Log::warning('PaymentController: gagal cek status ke Xendit', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPaymentResponse($payment->fresh()),
        ]);
    }

    /**
     * POST /api/payment/{paymentId}/cancel
     */
    public function cancel(Request $request, ?string $paymentId = null): JsonResponse
    {
        $paymentId = $paymentId ?: $request->payment_id;

        if (! $paymentId) {
            return response()->json(['success' => false, 'message' => 'Payment ID required'], 400);
        }

        $payment = Payment::where('payment_id', $paymentId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Pembayaran tidak ditemukan'], 404);
        }

        $outcome = $this->paymentCancellation->cancelPayment($payment, 'customer', 'Pelanggan membatalkan pembayaran.');

        if ($outcome['result'] === PaymentCancellationService::UNVERIFIED) {
            return response()->json([
                'success' => false,
                'error_code' => 'payment_status_unknown',
                'message' => $outcome['message'],
                'payment' => $outcome['payment'],
            ], 409);
        }

        if ($outcome['result'] === PaymentCancellationService::REQUIRES_REFUND) {
            return response()->json([
                'success' => false,
                'error_code' => 'payment_already_paid',
                'message' => $outcome['message'],
                'payment' => $outcome['payment'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'already_final' => $outcome['result'] === PaymentCancellationService::ALREADY_FINAL,
            'message' => $outcome['message'],
            'payment' => $outcome['payment'],
        ]);
    }

    /**
     * Callback legacy Komerce (dipertahankan read-safe untuk kompatibilitas riwayat lama).
     */
    public function callback(Request $request): JsonResponse
    {
        $incomingKey = $request->header('x-callback-key') ?? $request->input('callback_api_key');
        $expectedKey = config('services.komerce_payment.callback_key');

        if (! $expectedKey || ! hash_equals((string) $expectedKey, (string) $incomingKey)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json(['message' => 'Legacy gateway retired; active payments use Xendit.'], 410);
    }

    private function formatPaymentResponse(Payment $payment, bool $idempotentReplay = false): array
    {
        $metadata = (array) ($payment->metadata ?? []);

        return [
            'id' => $payment->id,
            'payment_id' => $payment->payment_id,
            'provider' => (string) ($metadata['provider'] ?? 'xendit'),
            'payment_type' => $payment->payment_type,
            'channel_code' => $payment->channel_code,
            'method' => $payment->channel_code,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'shipment_id' => $payment->shipment_id,
            'va_number' => $payment->va_number,
            'qr_string' => $payment->qr_string,
            'payment_url' => $payment->payment_url,
            'payment_mode' => (string) ($metadata['payment_mode'] ?? 'direct'),
            'expired_at' => $payment->expired_at?->toIso8601String(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'instructions' => $this->getInstructions($payment),
            'idempotent_replay' => $idempotentReplay,
        ];
    }

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

    private function markOrderShipmentPaid(Payment $payment): void
    {
        if ($payment->order_id && Schema::hasTable('orders')) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
            }
        }
        if ($payment->shipment_id && Schema::hasTable('shipments')) {
            Shipment::where('id', $payment->shipment_id)->update(['status' => 'confirmed']);
        }
    }

    private function getInstructions(Payment $payment): array
    {
        if ($payment->payment_type === 'qris') {
            return [
                'Scan QR code menggunakan aplikasi e-wallet atau m-banking berlogo QRIS',
                'Pastikan nominal pembayaran: '.$payment->formatted_amount,
                'Status otomatis terupdate setelah dikonfirmasi oleh Xendit',
                'Berlaku hingga: '.($payment->expired_at?->format('d M Y H:i') ?? '-'),
            ];
        }

        if ($payment->payment_type === 'ewallet') {
            return [
                'Lanjutkan ke halaman pembayaran '.$payment->channel_code.' (Xendit)',
                'Nominal pembayaran: '.$payment->formatted_amount,
                'Status otomatis terupdate setelah konfirmasi dari Xendit',
            ];
        }

        $channel = str_replace('_VIRTUAL_ACCOUNT', '', (string) $payment->channel_code);
        $bankName = match ($channel) {
            'BCA' => 'BCA',
            'BNI' => 'BNI',
            'BRI' => 'BRI',
            'MANDIRI' => 'Mandiri',
            'PERMATA' => 'Permata',
            default => $channel !== '' ? $channel : 'Bank',
        };

        return [
            "Transfer ke Virtual Account {$bankName}",
            "Nomor VA: {$payment->va_number}",
            "Nominal: {$payment->formatted_amount}",
            'Pembayaran dikonfirmasi otomatis oleh Xendit setelah transfer',
            'Berlaku hingga: '.($payment->expired_at?->format('d M Y H:i') ?? '-'),
        ];
    }
}
