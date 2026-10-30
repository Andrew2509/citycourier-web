<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pembatalan pesanan yang menyertakan transaksi pembayaran iPaymu.
 *
 * FAKTA PENTING (docs.ipaymu.com, sitemap + Postman resmi + SDK resmi):
 * iPaymu v2 TIDAK menyediakan endpoint cancel/void/refund. Kode transaksi
 * pending hanya berakhir sendiri lewat `expired` (QRIS ±5 menit, BCA 12 jam,
 * BSI 3 jam, dst). Karena itu layanan ini TIDAK PERNAH mengaku sudah
 * membatalkan transaksi di iPaymu. Yang dilakukan:
 *
 *  1. Menanyakan status sebenarnya lewat resmi Check Transaction
 *     (POST /api/v2/transaction) melalui IpaymuPaymentService::syncStatus().
 *  2. Menyamakan status lokal dengan hasil yang benar-benar dikonfirmasi.
 *  3. Bila iPaymu masih pending: pembayaran ditutup LOKAL + dicatat bahwa
 *     iPaymu akan mengedaluwarsakannya sendiri (bukan dibatalkan).
 *  4. Bila iPaymu sudah dibayar: pesanan TIDAK dibatalkan sebagai pembatalan
 *     biasa — hasilnya 'requires_refund' dan refund ditangani manual
 *     (support@ipaymu.com), sesuai kebijakan iPaymu.
 *  5. Bila status tidak bisa dipastikan (timeout/error/redirect session):
 *     tidak ada status yang diubah secara sepihak pada kasus unverified.
 */
class PaymentCancellationService
{
    /** Tidak ada transaksi pembayaran sama sekali (mis. COD/tunai). */
    public const NO_PAYMENT = 'no_payment';

    /** iPaymu sudah mengedaluwarsakan transaksi; status lokal disamakan. */
    public const EXPIRED = 'expired';

    /** iPaymu menandai transaksi batal/gagal; status lokal disamakan. */
    public const PROVIDER_CANCELED = 'provider_canceled';

    /**
     * Transaksi masih pending di iPaymu dan tidak ada API pembatalan.
     * Pembayaran ditutup lokal; kode bayar akan kedaluwarsa sendiri di iPaymu.
     */
    public const PROVIDER_PENDING = 'provider_pending';

    /** Sudah dibayar → wajib jalur refund manual, bukan pembatalan biasa. */
    public const REQUIRES_REFUND = 'requires_refund';

    /** Status tidak bisa dipastikan → jangan ubah apa pun, cek ulang nanti. */
    public const UNVERIFIED = 'unverified';

    /** Pembayaran sudah final sebelumnya → idempotent, tidak ada aksi. */
    public const ALREADY_FINAL = 'already_final';

    public function __construct(private IpaymuPaymentService $paymentService)
    {
    }

    /**
     * Batalkan pembayaran milik sebuah shipment.
     *
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

        // Prioritaskan pembayaran yang masih hidup; kalau semua sudah final,
        // laporkan yang terakhir supaya tetap informatif.
        $target = $payments->firstWhere('status', 'pending') ?? $payments->first();

        return $this->cancelPayment($target, $actor, $reason, $payments);
    }

    /**
     * Batalkan satu pembayaran (dipakai juga oleh endpoint /payment/{id}/cancel).
     *
     * @param  \Illuminate\Support\Collection<int,Payment>|null  $siblings
     * @return array{result:string,message:string,payment:?array}
     */
    public function cancelPayment(Payment $payment, string $actor, ?string $reason = null, $siblings = null): array
    {
        return DB::transaction(function () use ($payment, $actor, $reason, $siblings) {
            /** @var Payment $locked */
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked) {
                return $this->response(self::UNVERIFIED, 'Transaksi pembayaran tidak ditemukan.');
            }

            $actor = $actor === 'admin' ? 'admin' : 'customer';

            // Idempotensi: status terminal tidak dikirim ulang ke iPaymu.
            if (in_array($locked->status, ['canceled', 'cancelled', 'expired', 'failed'], true)) {
                return $this->response(
                    self::ALREADY_FINAL,
                    'Transaksi pembayaran ini sudah final, tidak ada permintaan ulang ke iPaymu.',
                    $locked
                );
            }

            if ($locked->status === 'paid') {
                return $this->requiresRefund($locked, $actor, $reason);
            }

            // Status pembayaran yang tidak diketahui pasti: jangan ubah apa pun.
            $verification = $this->verifyAtProvider($locked);
            $verification['actor'] = $actor;
            $verification['reason'] = $reason;

            switch ($verification['local_status']) {
                case 'paid':
                    return $this->requiresRefund($locked, $actor, $reason, $verification);

                case self::UNVERIFIED:
                    Log::warning('Pembatalan ditahan: status iPaymu tidak dapat dipastikan', [
                        'payment_id' => $locked->payment_id,
                        'reference'  => $locked->metadata['ipaymu_reference_id'] ?? null,
                        'error'      => $verification['error'] ?? null,
                    ]);

                    return $this->response(
                        self::UNVERIFIED,
                        'Status pembayaran belum bisa dipastikan karena iPaymu tidak merespons. '
                        . 'Pesanan belum dibatalkan — silakan periksa kembali beberapa saat lagi.',
                        $locked
                    );

                default:
                    $finalStatus = $verification['local_status'];
                    $metadata = (array) ($locked->metadata ?? []);
                    $metadata['cancellation'] = [
                        'requested_at'           => now()->toIso8601String(),
                        'actor'                  => $actor,
                        'reason'                 => $reason,
                        'result'                 => $verification['result'],
                        'provider'               => 'ipaymu',
                        'provider_cancel_api'    => false,
                        'provider_status'        => $verification['provider_status'],
                        'provider_status_desc'   => $verification['provider_status_desc'],
                        'provider_expired_at'    => $verification['provider_expired_at'],
                        'refund_required'        => false,
                        'refund_requested_at'    => null,
                    ];

                    $locked->update([
                        'status'   => $finalStatus,
                        'metadata' => $metadata,
                    ]);

                    Log::info('Pembayaran ditutup karena pesanan dibatalkan', [
                        'payment_id'   => $locked->payment_id,
                        'reference'    => $metadata['ipaymu_reference_id'] ?? null,
                        'from'         => 'pending',
                        'to'           => $finalStatus,
                        'provider'     => $verification['provider_status'],
                        'actor'        => $actor,
                        'note'         => $verification['note'] ?? null,
                    ]);

                    // Sinkronkan sisa pembayaran pending lain pada pesanan yang sama
                    // bila ada (mis. percobaan bayar ganda) — tanpa memanggil iPaymu.
                    $this->closeSiblings($locked->id, $siblings, $metadata['cancellation']);

                    return $this->response($verification['result'], $verification['message'], $locked->fresh());
            }
        });
    }

    /**
     * Tandai pembayaran sebagai perlu refund (tidak ada API refund iPaymu).
     */
    private function requiresRefund(Payment $payment, string $actor, ?string $reason, ?array $verification = null): array
    {
        $metadata = (array) ($payment->metadata ?? []);
        $cancellation = (array) ($metadata['cancellation'] ?? []);

        $metadata['cancellation'] = array_merge($cancellation, [
            'requested_at'         => $cancellation['requested_at'] ?? now()->toIso8601String(),
            'actor'                => $actor,
            'reason'               => $reason ?? ($cancellation['reason'] ?? null),
            'result'               => self::REQUIRES_REFUND,
            'provider'             => 'ipaymu',
            'provider_cancel_api'  => false,
            'provider_status'      => $verification['provider_status'] ?? ($cancellation['provider_status'] ?? null),
            'provider_status_desc' => $verification['provider_status_desc'] ?? ($cancellation['provider_status_desc'] ?? null),
            'refund_required'      => true,
            'refund_requested_at'  => $cancellation['refund_requested_at'] ?? null,
        ]);

        $updates = ['metadata' => $metadata];

        // iPaymu menyatakan sudah dibayar → status lokal WAJIB ikut paid,
        // supaya tidak ada uang yang tercatat sebagai pending.
        if (($verification['local_status'] ?? null) === 'paid' && $payment->status !== 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = $payment->paid_at ?? now();
        }

        $payment->update($updates);

        Log::warning('Pembatalan pesanan ditolak: pembayaran sudah diterima, perlu refund manual', [
            'payment_id' => $payment->payment_id,
            'reference'  => $metadata['ipaymu_reference_id'] ?? null,
            'amount'     => $payment->amount,
            'actor'      => $actor,
        ]);

        return $this->response(
            self::REQUIRES_REFUND,
            'Pesanan sudah dibayar. Pembatalan pesanan memerlukan proses pengembalian dana '
            . 'sesuai ketentuan iPaymu (tidak ada API refund, diproses manual oleh admin).',
            $payment->fresh()
        );
    }

    /**
     * Tanyakan status resmi transaksi ke iPaymu (Check Transaction).
     *
     * Tidak ada endpoint pembatalan di iPaymu, jadi hasil pemetaan ini juga
     * dipakai sebagai keputusan akhir: pending → tutup lokal, bayar → refund.
     *
     * @return array{
     *   result:string, local_status:string, provider_status:?string,
     *   provider_status_desc:?string, provider_expired_at:?string,
     *   message:string, note:?string, error:?string
     * }
     */
    private function verifyAtProvider(Payment $payment): array
    {
        $base = [
            'result'               => self::UNVERIFIED,
            'local_status'         => self::UNVERIFIED,
            'provider_status'      => null,
            'provider_status_desc' => null,
            'provider_expired_at'  => $payment->expired_at?->toIso8601String(),
            'note'                 => null,
            'error'                => null,
        ];

        $mode = (string) ($payment->metadata['payment_mode'] ?? 'direct');
        $trxId = $payment->metadata['ipaymu_trx_id'] ?? null;

        // Sesi Redirect adalah SessionID, bukan TransactionId: Check Transaction
        // tidak bisa dipakai. iPaymu tetap mengedaluwarsakan sesinya sendiri.
        if ($mode === 'redirect' && empty($trxId)) {
            return array_merge($base, [
                'result'              => self::PROVIDER_PENDING,
                'local_status'        => 'canceled',
                'provider_status'     => 'session',
                'provider_status_desc'=> 'Sesi pembayaran iPaymu (tanpa API pembatalan)',
                'note'                => 'redirect_session_closed_locally',
                'message'             => $this->providerPendingMessage(),
            ]);
        }

        if (empty($payment->payment_id) || !iPaymuService::isConfigured()) {
            // Tanpa transaksi provider (mis. pembayaran manual) pembatalan lokal aman.
            return array_merge($base, [
                'result'       => self::PROVIDER_PENDING,
                'local_status' => 'canceled',
                'provider_status' => 'local_only',
                'note'         => 'no_provider_transaction',
                'message'      => 'Pembayaran lokal dibatalkan (tidak ada transaksi iPaymu).',
            ]);
        }

        $sync = $this->paymentService->syncStatus($payment);

        if (!($sync['known'] ?? false)) {
            return array_merge($base, [
                'result' => self::UNVERIFIED,
                'error'  => $sync['error'] ?? 'Inquiry iPaymu tidak diketahui.',
            ]);
        }

        $providerStatus = (string) ($sync['status'] ?? 'unknown');
        $base['provider_status'] = $providerStatus;
        $base['provider_status_desc'] = $this->providerStatusLabel($sync['provider_code'] ?? null);

        return match ($providerStatus) {
            'paid' => array_merge($base, [
                'result'       => self::REQUIRES_REFUND,
                'local_status' => 'paid',
                'message'      => 'Pembayaran sudah diterima iPaymu.',
            ]),
            'expired' => array_merge($base, [
                'result'       => self::EXPIRED,
                'local_status' => 'expired',
                'message'      => 'Pesanan berhasil dibatalkan. Kode pembayaran iPaymu sudah kedaluwarsa.',
            ]),
            'canceled' => array_merge($base, [
                'result'       => self::PROVIDER_CANCELED,
                'local_status' => 'canceled',
                'message'      => 'Pesanan dan transaksi pembayaran iPaymu sudah dibatalkan.',
            ]),
            'failed' => array_merge($base, [
                'result'       => self::PROVIDER_CANCELED,
                'local_status' => 'failed',
                'message'      => 'Pesanan berhasil dibatalkan. Transaksi iPaymu tercatat gagal.',
            ]),
            default => array_merge($base, [
                // iPaymu tidak punya API pembatalan: transaksi dibiarkan
                // kedaluwarsa sendiri, sedangkan pembayaran kita tutup lokal.
                'result'       => self::PROVIDER_PENDING,
                'local_status' => 'canceled',
                'provider_status' => 'pending',
                'note'         => 'provider_cancel_not_supported',
                'message'      => $this->providerPendingMessage(),
            ]),
        };
    }

    private function providerPendingMessage(): string
    {
        return 'Pesanan berhasil dibatalkan. iPaymu tidak menyediakan API pembatalan, '
            . 'jadi kode pembayaran akan kedaluwarsa sendiri dan tidak bisa dipakai lagi.';
    }

    /** Terjemahan kode status resmi iPaymu untuk tampilan admin. */
    private function providerStatusLabel(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ((int) $code) {
            1  => 'Berhasil',
            6  => 'Berhasil (belum settlement)',
            0  => 'Menunggu pembayaran',
            7  => 'Escrow',
            2  => 'Dibatalkan oleh iPaymu',
            -2 => 'Kedaluwarsa',
            3  => 'Refund',
            4  => 'Error',
            5  => 'Gagal',
            default => 'Kode ' . $code,
        };
    }

    /** Tutup pembayaran pending lain pada pesanan yang sama tanpa memanggil iPaymu. */
    private function closeSiblings(int $exceptId, $siblings, array $cancellation): void
    {
        if (!$siblings) {
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
            'result'  => $result,
            'message' => $message,
            'payment' => $payment ? $this->summary($payment) : null,
        ];
    }

    /**
     * Ringkasan pembayaran untuk respons API & tampilan admin.
     * Tidak memuat kredensial apa pun.
     */
    public function summary(Payment $payment): array
    {
        $metadata = (array) ($payment->metadata ?? []);
        $cancellation = (array) ($metadata['cancellation'] ?? []);

        return [
            'payment_id'            => $payment->payment_id,
            'status'                => $payment->status,
            'amount'                => $payment->amount,
            'channel'               => $payment->channel_code,
            'va_number'             => $payment->va_number,
            'reference_id'          => $metadata['ipaymu_reference_id'] ?? null,
            'payment_mode'          => $metadata['payment_mode'] ?? 'direct',
            'provider_cancel_api'   => false,
            'provider_status'       => $cancellation['provider_status'] ?? null,
            'provider_status_desc'  => $cancellation['provider_status_desc'] ?? null,
            'provider_expired_at'   => $cancellation['provider_expired_at'] ?? $payment->expired_at?->toIso8601String(),
            'cancellation_result'   => $cancellation['result'] ?? null,
            'cancellation_at'       => $cancellation['requested_at'] ?? null,
            'cancellation_actor'    => $cancellation['actor'] ?? null,
            'refund_required'       => (bool) ($cancellation['refund_required'] ?? false),
            'refund_requested_at'   => $cancellation['refund_requested_at'] ?? null,
        ];
    }
}
