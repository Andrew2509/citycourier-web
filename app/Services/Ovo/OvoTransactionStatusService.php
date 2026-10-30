<?php

namespace App\Services\Ovo;

use App\Services\OvoProvider;

/**
 * OVO Transaction Status Service.
 * Check Transaction Status resmi:
 * POST /OVOSNAP/v2.0/emoney/topup-status.
 *
 * Dipakai untuk:
 * - rekonsiliasi transaksi 'processing' (termasuk hasil timeout transport),
 * - memastikan hasil FINAL sebelum transaksi pengganti dikirim.
 */
class OvoTransactionStatusService
{
    private OvoProvider $provider;

    public function __construct(?OvoProvider $provider = null)
    {
        $this->provider = $provider ?? (new \App\Services\OvoService())->activeProvider();
    }

    /**
     * Cek status akhir transaksi top up.
     *
     * @param string $referenceNo originalPartnerReferenceNo (wajib sama dengan saat topup)
     * @param string|null $transactionId referenceNo dari OVO bila tersedia
     * @return array ['success' => bool, 'status' => 'success|processing|failed|unknown', 'details' => array, 'error' => ?string]
     */
    public function inquire(string $referenceNo, ?string $transactionId = null): array
    {
        return $this->provider->topupStatusInquiry($referenceNo, $transactionId);
    }
}
