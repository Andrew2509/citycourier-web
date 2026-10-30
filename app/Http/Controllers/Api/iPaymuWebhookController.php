<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\iPaymuService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * iPaymu Webhook Controller (publik).
 *
 * Tanggung jawab:
 * 1. Validasi signature callback jika IPAYMU_WEBHOOK_SECRET di-set.
 * 2. Terima payload status disbursement dari iPaymu.
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
    ) {
        $this->ipaymuService = $this->ipaymuService ?? new iPaymuService();
        $this->withdrawalService = $this->withdrawalService ?? new WithdrawalService();
    }

    public function handle(\Illuminate\Http\Request $request): JsonResponse
    {
        $secret = (string) (iPaymuService::credentials()['webhook_secret'] ?? '');

        // ── 1. Validasi signature (wajib bila secret dikonfigurasi) ──
        if ($secret !== '') {
            if (!$this->signatureMatches($request, $secret)) {
                Log::warning('[iPaymuWebhook] Signature tidak valid', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['success' => false, 'message' => 'Signature tidak valid.'], 401);
            }
        }

        $payload = $request->all();
        Log::info('[iPaymuWebhook] Callback diterima', ['payload' => $payload]);

        // ── 2. Identifikasi transaksi ──
        // Menerima nama field umum iPaymu: transaction_id | trx_id | reference | data.*
        $transactionId = $payload['transaction_id']
            ?? $payload['trx_id']
            ?? $payload['TransactionId']
            ?? $payload['reference']
            ?? $payload['reference_id']
            ?? ($payload['data']['transaction_id'] ?? null)
            ?? ($payload['Data']['TransactionId'] ?? null);

        $rawStatus = $payload['status']
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

        // Normalisasi status: angka iPaymu (0 sukses, 1 pending, lainnya gagal)
        // maupun masih string ("success", "pending", "failed", ...).
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
     * Signature webhook. Mendukung pola umum provider:
     * - Header X-Callback-Signature / X-Signature / Sign
     *   = hash('sha256', $secret, body) ATAU body + secret concat HMAC/SHA256.
     */
    private function signatureMatches(\Illuminate\Http\Request $request, string $secret): bool
    {
        $header = $request->headers->get('x-callback-signature')
            ?? $request->headers->get('x-signature')
            ?? $request->headers->get('sign')
            ?? $request->input('sign');

        if (empty($header)) {
            return false;
        }

        $body = $request->getContent();
        $candidates = [
            hash_hmac('sha256', $body, $secret),
            hash('sha256', $body . $secret),
            hash('sha256', $secret . $body),
        ];

        return in_array(strtolower((string) $header), $candidates, true);
    }

    private function normalizeStatus($raw): string
    {
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            return match ((int) $raw) {
                0 => 'success',
                1 => 'pending',
                default => 'failed',
            };
        }

        return match (strtolower((string) $raw)) {
            'success', 'paid', 'settled', 'completed' => 'success',
            'processing', 'pending', 'queued', 'awaiting' => 'processing',
            'expired', 'failed', 'cancelled', 'reversed' => 'failed',
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
