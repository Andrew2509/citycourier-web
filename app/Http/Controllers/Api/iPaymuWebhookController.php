<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\iPaymuService;
use App\Services\IpaymuPaymentService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * iPaymu Webhook Controller (publik).
 *
 * Sesuai docs.ipaymu.com → Callback:
 * - Header autentikasi: X-Signature (+ X-Timestamp, X-External-ID).
 * - Secret = nomor VA merchant (kolom "Webhook Secret" di admin bersifat
 *   override opsional).
 * - Validasi: normalisasi tipe data → sort keys A→Z → PHP json_encode
 *   (default meng-escape '/') → HMAC-SHA256 dengan secret → bandingkan.
 *
 * Tanggung jawab:
 * 1. Validasi signature callback (wajib bila VA/secret tersedia).
 * 2. Terima payload status transaksi dari iPaymu.
 * 3. Untuk status FINAL (success/failed), validasi ULANG ke API iPaymu
 *    (inquiry) sebelum saldo dikonfirmasi/dilepas — payload webhook saja
 *    tidak cukup mengubah saldo.
 * 4. Idempotent: callback yang sama pada transaksi terminal diabaikan.
 */
class iPaymuWebhookController extends Controller
{
    public function __construct(
        private ?iPaymuService $ipaymuService = null,
        private ?WithdrawalService $withdrawalService = null,
        private ?IpaymuPaymentService $paymentService = null,
    ) {
        $this->ipaymuService = $this->ipaymuService ?? new iPaymuService();
        $this->withdrawalService = $this->withdrawalService ?? new WithdrawalService();
        $this->paymentService = $this->paymentService ?? new IpaymuPaymentService($this->ipaymuService);
    }

    public function handle(\Illuminate\Http\Request $request): JsonResponse
    {
        $cred = iPaymuService::credentials();

        // Secret callback resmi iPaymu adalah nomor VA (menu Integration).
        // "Webhook Secret" di pengaturan admin dipakai sebagai override bila
        // diisi — misalnya bila merchant memakai callback key terpisah.
        $secret = ($cred['webhook_secret'] ?? '') !== ''
            ? (string) $cred['webhook_secret']
            : (string) ($cred['va'] ?? '');

        // ── 1. Validasi signature (wajib bila VA/secret tersedia) ──
        if ($secret !== '') {
            if (!$this->signatureMatches($request, $secret)) {
                Log::warning('[iPaymuWebhook] Signature tidak valid', [
                    'ip' => $request->ip(),
                    'external_id' => $request->headers->get('x-external-id'),
                ]);
                return response()->json(['success' => false, 'message' => 'Signature tidak valid.'], 401);
            }
        } else {
            Log::warning('[iPaymuWebhook] Callback diterima tanpa VA/secret — validasi dilewati, status akan diverifikasi via inquiry.');
        }

        $payload = $request->all();
        Log::info('[iPaymuWebhook] Callback diterima', ['payload' => $payload]);

        // ── 2. Identifikasi transaksi ──
        // Nama field callback resmi iPaymu: trx_id (id iPaymu) dan
        // reference_id/referenceId (referensi merchant kita).
        $transactionId = $payload['trx_id']
            ?? $payload['transaction_id']
            ?? $payload['TransactionId']
            ?? $payload['reference_id']
            ?? $payload['referenceId']
            ?? $payload['reference']
            ?? $payload['sid']
            ?? ($payload['data']['transaction_id'] ?? null)
            ?? ($payload['Data']['TransactionId'] ?? null);

        // status_code numerik resmi diprioritaskan; 'status' teks
        // ('berhasil'|'pending'|'expired') sebagai fallback.
        $rawStatus = $payload['status_code']
            ?? $payload['transaction_status_code']
            ?? $payload['status']
            ?? $payload['Status']
            ?? $payload['transaction_status']
            ?? ($payload['data']['status'] ?? null)
            ?? null;

        if (empty($transactionId) || $rawStatus === null) {
            return response()->json([
                'success' => false,
                'message' => 'Payload webhook tidak dikenal (transaction_id/status tidak ditemukan).',
            ], 422);
        }

        // Normalisasi status: kode resmi iPaymu (1/6 sukses, 0/7 pending,
        // -2 dan lainnya gagal) maupun teks ("berhasil", "pending", ...).
        $normalized = $this->normalizeStatus($rawStatus);

        if (!in_array($normalized, ['success', 'failed', 'pending', 'processing'], true)) {
            Log::warning('[iPaymuWebhook] Status tidak dikenali', ['raw' => $rawStatus]);
            return response()->json(['success' => false, 'message' => 'Status tidak dikenali.'], 422);
        }

        // ── 3. Status non-final: hanya catat, saldo tetap ditahan ──
        $withdrawal = \App\Models\Withdrawal::where('provider_reference', (string) $transactionId)
            ->orWhere('idempotency_key', (string) $transactionId)
            ->first();

        if (!$withdrawal) {
            // ── Bukan penarikan — cek pembayaran checkout (VA/QRIS/Redirect) ──
            $payment = \App\Models\Payment::where('payment_id', (string) $transactionId)->first();

            // Redirect Payment menyimpan SessionID sebagai payment_id, sedangkan
            // callback membawa `trx_id` + `reference_id` milik kita. Cocokkan
            // lewat referenceId, lalu simpan trx_id resmi supaya inquiry status
            // berikutnya memakai TransactionId (bukan SessionID).
            if (!$payment) {
                $reference = $payload['reference_id'] ?? $payload['referenceId'] ?? null;
                if (!empty($reference)) {
                    $payment = \App\Models\Payment::where('metadata->ipaymu_reference_id', (string) $reference)->first();
                }

                if ($payment) {
                    $meta = $payment->metadata ?? [];
                    $meta['ipaymu_trx_id'] = (string) $transactionId;
                    $payment->update([
                        'payment_id' => (string) $transactionId,
                        'metadata'   => $meta,
                    ]);
                }
            }

            if ($payment) {
                return $this->handlePaymentCallback($payment, $normalized, $payload, (string) $transactionId);
            }

            Log::warning('[iPaymuWebhook] Transaksi tidak ditemukan', ['tx' => $transactionId]);
            // 200 supaya iPaymu tidak me-retry webhook yang memang bukan milik kita.
            return response()->json(['success' => true, 'message' => 'Referensi tidak dikenal, diabaikan.']);
        }

        if ($withdrawal->isTerminal()) {
            // Idempotent: terminal tidak berubah.
            return response()->json(['success' => true, 'message' => 'Transaksi sudah final.']);
        }

        if ($normalized === 'pending' || $normalized === 'processing') {
            if (!in_array($withdrawal->status, ['pending', 'reserved', 'processing'], true)) {
                $this->withdrawalService->pushHistory($withdrawal, 'processing', 'Webhook: payout masih diproses.', 'ipaymu');
            }
            return response()->json(['success' => true, 'message' => 'Status processing dicatat.']);
        }

        // ── 4. Status final: verifikasi ulang ke iPaymu sebelum mengubah saldo ──
        $inquiry = $this->ipaymuService->inquire((string) ($withdrawal->provider_reference ?: $transactionId));
        $inquiryStatus = $inquiry['status'] ?? 'unknown';

        // Inquiry harus setuju dengan webhook. Jika tidak, PERCAYA INQUIRY
        // API resmi — payload webhook tidak boleh sendiri mengubah saldo.
        if ($inquiryStatus === 'unknown') {
            if ($secret === '') {
                Log::warning('[iPaymuWebhook] Inquiry gagal & secret tidak di-set; status tidak diubah', [
                    'withdrawal_id' => $withdrawal->id,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook diterima. Konfirmasi final menunggu inquiry API iPaymu.',
                ]);
            }
            // Secret valid tapi inquiry gagal: masih aman menerima jawaban final
            // karena endpoint sudah terautentikasi.
        }

        // Terapkan berdasarkan penyatuan hasil webhook + inquiry.
        $finalStatus = $this->reconcileFinalStatus($normalized, $inquiryStatus);
        $failureReason = $inquiry['error'] ?? ($payload['message'] ?? null);

        $updated = $this->withdrawalService->handleStatusUpdate(
            (string) ($withdrawal->provider_reference ?: $transactionId),
            $finalStatus,
            $failureReason,
        );

        if ($updated && $finalStatus !== $normalized) {
            $this->withdrawalService->pushHistory($updated, $finalStatus, 'Final via webhook, disesuaikan hasil inquiry iPaymu.', 'ipaymu');
        }

        return response()->json(['success' => true, 'message' => 'Status penarikan diperbarui.']);
    }

    /**
     * Validasi X-Signature callback iPaymu (docs.ipaymu.com → Callback):
     * 1. Ambil data body (JSON atau form-urlencoded).
     * 2. Normalisasi tipe (trx_id dll → int, is_escrow → bool, additional_info → array).
     * 3. Sort semua keys A→Z (case-sensitive, seperti PHP ksort).
     * 4. JSON-encode (PHP default meng-escape '/', sesuai standar iPaymu).
     * 5. HMAC-SHA256 dengan secret (VA) → bandingkan dengan header.
     */
    private function signatureMatches(\Illuminate\Http\Request $request, string $secret): bool
    {
        $received = $request->headers->get('x-signature')
            ?? $request->headers->get('x-callback-signature')
            ?? '';

        if ($received === '') {
            return false;
        }

        $normalized = $this->normalizeCallbackData($request);
        ksort($normalized, SORT_STRING);

        $json = json_encode($normalized);
        if ($json === false) {
            return false;
        }

        $calculated = hash_hmac('sha256', $json, $secret);

        return hash_equals($calculated, strtolower(trim($received)));
    }

    /**
     * Normalisasi tipe data callback sesuai tabel di dokumentasi iPaymu.
     * Kesalahan tipe pada salah satu field akan membuat hash tidak cocok.
     */
    private function normalizeCallbackData(\Illuminate\Http\Request $request): array
    {
        $data = $request->json()->all();
        if (!is_array($data) || empty($data)) {
            $data = $request->post();
        }
        if (!is_array($data)) {
            return [];
        }

        $intFields = ['trx_id', 'status_code', 'transaction_status_code', 'paid_off'];

        $result = [];
        foreach ($data as $key => $value) {
            if ($key === 'signature') {
                continue;
            }
            if (in_array($key, $intFields, true)) {
                $result[$key] = is_numeric($value) ? (int) $value : $value;
            } elseif ($key === 'is_escrow') {
                $result[$key] = in_array($value, [true, 1, '1', 'true'], true);
            } elseif ($key === 'additional_info') {
                $result[$key] = ($value === '[]') ? [] : $value;
            } else {
                $result[$key] = is_array($value) ? $value : (string) $value;
            }
        }

        if (!array_key_exists('additional_info', $result)) {
            $result['additional_info'] = [];
        }

        return $result;
    }

    /**
     * Tangani callback untuk transaksi pembayaran checkout (VA/QRIS) iPaymu.
     *
     * Status 'paid' HANYA setelah konfirmasi resmi iPaymu (callback dengan
     * signature tervalidasi + inquiry). Bila inquiry gagal, status webhook
     * dipakai karena endpoint sudah terautentikasi signature.
     */
    private function handlePaymentCallback(\App\Models\Payment $payment, string $webhookStatus, array $payload, string $transactionId): JsonResponse
    {
        $terminal = in_array($payment->status, ['paid', 'expired', 'canceled', 'cancelled', 'failed'], true);

        if ($webhookStatus === 'pending' || $webhookStatus === 'processing') {
            return response()->json(['success' => true, 'message' => 'Status processing dicatat.']);
        }

        $finalStatus = $this->paymentService->webhookFinalStatus($transactionId, $webhookStatus);

        if ($terminal) {
            // Satu-satunya transisi yang tetap dihormati: uang benar-benar masuk
            // SETELAH pesanan/pembayaran lokal ditutup (pembatalan). Uang nyata
            // tidak boleh hilang diam-diam — status jadi paid + wajib refund,
            // sementara pesanan tetap dibatalkan (tidak dihidupkan kembali).
            if ($payment->status !== 'paid' && in_array($finalStatus, ['paid'], true)) {
                $metadata = (array) ($payment->metadata ?? []);
                $metadata['cancellation'] = array_merge((array) ($metadata['cancellation'] ?? []), [
                    'refund_required'      => true,
                    'payment_after_cancel' => now()->toIso8601String(),
                    'note'                 => 'Pembayaran diterima iPaymu setelah pembatalan lokal.',
                ]);

                $payment->update([
                    'status'        => 'paid',
                    'paid_at'       => now(),
                    'callback_data' => $payload,
                    'metadata'      => $metadata,
                ]);

                if ($payment->order_id) {
                    \App\Models\Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
                }

                Log::warning('Pembayaran masuk setelah pembatalan — perlu refund manual', [
                    'payment_id' => $payment->payment_id,
                    'reference'  => $metadata['ipaymu_reference_id'] ?? null,
                    'amount'     => $payment->amount,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran terlambat dicatat; refund diperlukan.',
                ]);
            }

            // Idempotent: transaksi terminal tidak berubah.
            return response()->json(['success' => true, 'message' => 'Transaksi pembayaran sudah final.']);
        }

        $updateData = [
            'status'        => $finalStatus,
            'callback_data' => $payload,
        ];

        if ($finalStatus === 'paid') {
            $updateData['paid_at'] = now();
        }

        $payment->update($updateData);

        if ($finalStatus === 'paid') {
            if ($payment->order_id) {
                \App\Models\Order::where('id', $payment->order_id)->update(['payment_status' => 'paid']);
            }
            if ($payment->shipment_id) {
                \App\Models\Shipment::where('id', $payment->shipment_id)->update(['status' => 'confirmed']);
            }
        }

        return response()->json(['success' => true, 'message' => 'Status pembayaran diperbarui.']);
    }

    private function normalizeStatus($raw): string
    {
        if (is_int($raw) || (is_string($raw) && (ctype_digit($raw) || $raw === '-2'))) {
            // Kode resmi: 0 pending · 1 success · 2 cancelled · 3 refund
            // 4 error · 5 failed · 6 success(unsettled) · 7 escrow · -2 expired.
            return match ((int) $raw) {
                1, 6    => 'success',
                0, 7    => 'pending',
                default => 'failed',
            };
        }

        return match (strtolower((string) $raw)) {
            'success', 'paid', 'settled', 'completed', 'berhasil', 'terbayar' => 'success',
            'processing', 'pending', 'queued', 'awaiting', 'diproses', 'menunggu' => 'processing',
            'expired', 'failed', 'cancelled', 'reversed', 'gagal', 'dibatalkan', 'error' => 'failed',
            default => 'unknown',
        };
    }

    /**
     * Menyatukan status webhook & inquiry.
     * Inquiry dianggap sumber sebenarnya ketika hasilnya berbeda dari webhook.
     */
    private function reconcileFinalStatus(string $webhookStatus, string $inquiryStatus): string
    {
        if ($inquiryStatus === 'success' || $inquiryStatus === 'failed') {
            return $inquiryStatus;
        }
        return $webhookStatus; // inquiry masih pending/unknown → pakai status webhook
    }
}
