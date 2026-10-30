<?php

namespace App\Services\Ovo;

use App\Services\OvoProvider;

/**
 * OVO Disbursement Service.
 * Pengiriman dana ke nomor OVO kurir via Customer Top Up resmi:
 * POST /OVOSNAP/v2.0/emoney/topup.
 *
 * Aturan penting:
 * - partnerReferenceNo konsisten (dipakai ulang pada status inquiry).
 * - Respons timeout/tertunda return status 'processing', BUKAN 'failed' dan
 *   BUKAN 'success' — verifikasi wajib lewat OvoTransactionStatusService
 *   sebelum membuat transaksi pengganti (cegah transfer ganda).
 */
class OvoDisbursementService
{
    private OvoProvider $provider;

    public function __construct(?OvoProvider $provider = null)
    {
        $this->provider = $provider ?? (new \App\Services\OvoService())->activeProvider();
    }

    /**
     * Kirim Top Up / Disbursement ke nomor OVO tujuan.
     *
     * @param string $customerNumber Nomor OVO tujuan (hasil inquiry)
     * @param float  $amount         Nominal IDR
     * @param string $referenceNo    Referensi unik/idempotency (partnerReferenceNo)
     * @return array ['success' => bool, 'transaction_id' => ?string, 'status' => 'success|processing|failed', 'error' => ?string]
     */
    public function disburse(string $customerNumber, float $amount, string $referenceNo): array
    {
        return $this->provider->customerTopUp($customerNumber, $amount, $referenceNo);
    }
}
