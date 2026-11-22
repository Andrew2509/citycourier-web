<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shipment;
use App\Modules\Payment\Services\XenditPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Layanan pembatalan transaksi pembayaran pesanan menggunakan Xendit API v3.
 */
class PaymentCancellationService
{
    public const NO_PAYMENT = 'no_payment';
    public const EXPIRED = 'expired';
    public const PROVIDER_CANCELED = 'provider_canceled';
    public const PROVIDER_PENDING = 'provider_pending';
    public const REQUIRES_REFUND = 'requires_refund';
    public const UNVERIFIED = 'unverified';
    public const ALREADY_FINAL = 'already_final';

    public function __construct(
        private readonly XenditPaymentService $xenditPaymentService
    ) {}

    /**
     * @return array{result:string,message:string,payment:?array}
     */
    public function cancelForShipment(Shipment $shipment, string $actor, ?string $reason = null): array
    {
        $payments = Payment::where('shipment_id', $shipment->id)
            ->orderByDesc('id')
            ->get();

        if ($payments->isEmpty()) {
            return $this->response(self::NO_PAYMENT, 'Tidak ada transaksi pembayaran pada pesanan ini.');
        }

        $target = $payments->firstWhere('status', 'pending') ?? $payments->first();

        return $this->cancelPayment($target, $actor, $reason, $payments);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Payment>|null  $siblings
     * @return array{result:string,message:string,payment:?array}
     */
    public function cancelPayment(Payment $payment, string $actor, ?string $reason = null, $siblings = null): array
    {
        return DB::transaction(function () use ($payment, $actor, $reason, $siblings) {
            /** @var Payment|null $locked */
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return $this->response(self::UNVERIFIED, 'Transaksi pembayaran tidak ditemukan.');
            }

            $actor = $actor === 'admin' ? 'admin' : 'customer';

            if (in_array($locked->status, ['canceled', 'cancelled', 'expired', 'failed'], true)) {
                return $this->response(
                    self::ALREADY_FINAL,
                    'Transaksi pembayaran ini sudah final.',
                    $locked
                );
            }

            if ($locked->status === 'paid') {
                return $this->requiresRefund($locked, $actor, $reason);
            }

            $verification = $this->verifyAndCancelAtProvider($locked);

            if ($verification['local_status'] === 'paid') {
                return $this->requiresRefund($locked, $actor, $reason, $verification);
            }

            if ($verification['local_status'] === self::UNVERIFIED) {
                Log::warning('Pembatalan ditahan: status Xendit tidak dapat dipastikan', [
                    'payment_id' => $locked->payment_id,
                    'error' => $verification['error'] ?? null,
                ]);

                return $this->response(
                    self::UNVERIFIED,
                    'Status pembayaran belum bisa dipastikan dari Xendit. Pesanan belum dibatalkan — silakan coba lagi beberapa saat lagi.',
                    $locked
                );
            }

            $finalStatus = $verification['local_status'];
            $metadata = (array) ($locked->metadata ?? []);
            $metadata['cancellation'] = [
                'requested_at' => now()->toIso8601String(),
                'actor' => $actor,
                'reason' => $reason,
                'result' => $verification['result'],
                'provider' => (string) ($metadata['provider'] ?? 'xendit'),
                'provider_cancel_api' => $verification['provider_cancel_api'] ?? false,
                'provider_status' => $verification['provider_status'],
                'provider_status_desc' => $verification['provider_status_desc'],
                'provider_expired_at' => $verification['provider_expired_at'],
                'refund_required' => false,
                'refund_requested_at' => null,
            ];

            $locked->update([
                'status' => $finalStatus,
                'metadata' => $metadata,
            ]);

            $this->closeSiblings($locked->id, $siblings, $metadata['cancellation']);

            return $this->response($verification['result'], $verification['message'], $locked->fresh());
        });
    }

    private function requiresRefund(Payment $payment, string $actor, ?string $reason, ?array $verification = null): array
    {
        $metadata = (array) ($payment->metadata ?? []);
        $cancellation = (array) ($metadata['cancellation'] ?? []);

        $metadata['cancellation'] = array_merge($cancellation, [
            'requested_at' => $cancellation['requested_at'] ?? now()->toIso8601String(),
            'actor' => $actor,
            'reason' => $reason ?? ($cancellation['reason'] ?? null),
            'result' => self::REQUIRES_REFUND,
            'provider' => (string) ($metadata['provider'] ?? 'xendit'),
            'provider_cancel_api' => false,
            'provider_status' => $verification['provider_status'] ?? ($cancellation['provider_status'] ?? 'paid'),
            'provider_status_desc' => $verification['provider_status_desc'] ?? ($cancellation['provider_status_desc'] ?? 'Sudah dibayar'),
            'refund_required' => true,
            'refund_requested_at' => $cancellation['refund_requested_at'] ?? null,
        ]);

        $updates = ['metadata' => $metadata];
        if (($verification['local_status'] ?? null) === 'paid' && $payment->status !== 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = $payment->paid_at ?? now();
        }
        $payment->update($updates);

        return $this->response(
            self::REQUIRES_REFUND,
            'Pesanan sudah dibayar. Pembatalan memerlukan pengembalian dana melalui mekanisme refund.',
            $payment->fresh()
        );
    }

    private function verifyAndCancelAtProvider(Payment $payment): array
    {
        $metadata = (array) ($payment->metadata ?? []);
        $provider = strtolower((string) ($metadata['provider'] ?? 'xendit'));

        $base = [
            'result' => self::UNVERIFIED,
            'local_status' => self::UNVERIFIED,
            'provider_cancel_api' => false,
            'provider_status' => null,
            'provider_status_desc' => null,
            'provider_expired_at' => $payment->expired_at?->toIso8601String(),
            'error' => null,
        ];

        // Transaksi sandbox mock atau histori non-Xendit ditutup lokal secara aman
        if ($provider !== 'xendit' || empty($payment->payment_id) || ! $this->xenditPaymentService->isConfigured()) {
            return array_merge($base, [
                'result' => self::PROVIDER_PENDING,
                'local_status' => 'canceled',
                'provider_status' => $provider === 'mock' ? 'mock_canceled' : 'local_only',
                'provider_status_desc' => 'Ditutup secara lokal',
                'message' => 'Pesanan dan transaksi pembayaran berhasil dibatalkan.',
            ]);
        }

        try {
            $sync = $this->xenditPaymentService->getPaymentStatus($payment->payment_id);
        } catch (\Throwable $e) {
            return array_merge($base, [
                'result' => self::UNVERIFIED,
                'local_status' => self::UNVERIFIED,
                'error' => $e->getMessage(),
                'message' => 'Status pembayaran tidak dapat diverifikasi ke Xendit.',
            ]);
        }

        $providerStatus = (string) ($sync['status'] ?? 'pending');
        $base['provider_status'] = $providerStatus;
        $base['provider_status_desc'] = (string) ($sync['provider_status'] ?? $providerStatus);

        if ($providerStatus === 'paid') {
            return array_merge($base, [
                'result' => self::REQUIRES_REFUND,
                'local_status' => 'paid',
                'message' => 'Pembayaran sudah diterima Xendit.',
            ]);
        }

        if ($providerStatus === 'expired') {
            return array_merge($base, [
                'result' => self::EXPIRED,
                'local_status' => 'expired',
                'message' => 'Pesanan berhasil dibatalkan. Kode pembayaran Xendit sudah kedaluwarsa.',
            ]);
        }

        if (in_array($providerStatus, ['canceled', 'failed'], true)) {
            return array_merge($base, [
                'result' => self::PROVIDER_CANCELED,
                'local_status' => $providerStatus,
                'message' => 'Pesanan dan pembayaran Xendit sudah dibatalkan.',
            ]);
        }

        // Masih pending: ajukan cancel ke Xendit Payment Requests v3
        try {
            $cancelRes = $this->xenditPaymentService->cancelPaymentRequest($payment->payment_id);
            $cancelledAtProvider = (bool) ($cancelRes['cancelled'] ?? false);
        } catch (\Throwable) {
            $cancelledAtProvider = false;
        }

        return array_merge($base, [
            'result' => $cancelledAtProvider ? self::PROVIDER_CANCELED : self::PROVIDER_PENDING,
            'local_status' => 'canceled',
            'provider_cancel_api' => $cancelledAtProvider,
            'provider_status' => $cancelledAtProvider ? 'CANCELED' : 'pending_local_closed',
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
    }

    private function closeSiblings(int $exceptId, $siblings, array $cancellation): void
    {
        if (! $siblings) {
            return;
        }

        foreach ($siblings as $sibling) {
            if ($sibling->id === $exceptId || $sibling->status !== 'pending') {
                continue;
            }

            $meta = (array) ($sibling->metadata ?? []);
            $meta['cancellation'] = $cancellation + ['refund_required' => false];
            $sibling->update(['status' => 'canceled', 'metadata' => $meta]);
        }
    }

    private function response(string $result, string $message, ?Payment $payment = null): array
    {
        return [
            'result' => $result,
            'message' => $message,
            'payment' => $payment ? $this->summary($payment) : null,
        ];
    }

    public function summary(Payment $payment): array
    {
        $metadata = (array) ($payment->metadata ?? []);
        $cancellation = (array) ($metadata['cancellation'] ?? []);

        return [
            'payment_id' => $payment->payment_id,
            'provider' => (string) ($metadata['provider'] ?? 'xendit'),
            'status' => $payment->status,
            'amount' => $payment->amount,
            'channel' => $payment->channel_code,
            'va_number' => $payment->va_number,
            'reference_id' => $metadata['xendit_reference_id'] ?? ($metadata['ipaymu_reference_id'] ?? null),
            'payment_mode' => $metadata['payment_mode'] ?? 'direct',
            'provider_cancel_api' => (bool) ($cancellation['provider_cancel_api'] ?? false),
            'provider_status' => $cancellation['provider_status'] ?? null,
            'provider_status_desc' => $cancellation['provider_status_desc'] ?? null,
            'provider_expired_at' => $cancellation['provider_expired_at'] ?? $payment->expired_at?->toIso8601String(),
            'cancellation_result' => $cancellation['result'] ?? null,
            'cancellation_at' => $cancellation['requested_at'] ?? null,
            'cancellation_actor' => $cancellation['actor'] ?? null,
            'refund_required' => (bool) ($cancellation['refund_required'] ?? false),
            'refund_requested_at' => $cancellation['refund_requested_at'] ?? null,
        ];
    }
}
