<?php

namespace App\Services;

/**
 * OVO SNAP Provider Interface.
 *
 * Mengikuti spesifikasi resmi OVO Partner Integration:
 * https://www.ovo.id/partner-integration/consumer-topup/integration-high-level
 *
 * Endpoints:
 * 1. B2B Access Token:       POST /OVOSNAP/v1.0/access-token/b2b
 * 2. Account Inquiry:        POST /OVOSNAP/v2.0/emoney/account-inquiry
 * 3. Customer Top Up (Disb): POST /OVOSNAP/v2.0/emoney/topup
 * 4. Topup Inquiry Status:   POST /OVOSNAP/v2.0/emoney/topup-status
 * 5. Balance Inquiry:        POST /OVOSNAP/v1.0/balance-inquiry
 */
interface OvoProvider
{
    /**
     * Mendapatkan B2B access token OVO SNAP.
     *
     * @return array ['success' => bool, 'access_token' => string|null, 'expires_in' => int|null, 'error' => string|null]
     */
    public function getB2BAccessToken(): array;

    /**
     * Verifikasi kepemilikan dan status akun OVO melalui nomor HP.
     *
     * @param string $phoneNumber Nomor HP format 08... atau 628...
     * @return array ['success' => bool, 'customer_name' => string|null, 'customer_number' => string|null, 'status' => string|null, 'error' => string|null, 'error_code' => string|null]
     */
    public function accountInquiry(string $phoneNumber): array;

    /**
     * Pencairan dana ke akun OVO kurir (Customer Top Up / Disbursement).
     *
     * @param string $customerNumber Nomor OVO tujuan
     * @param float  $amount         Nominal pencairan (IDR)
     * @param string $referenceNo    Idempotency reference unik
     * @return array ['success' => bool, 'transaction_id' => string|null, 'status' => string, 'error' => string|null]
     */
    public function customerTopUp(string $customerNumber, float $amount, string $referenceNo): array;

    /**
     * Cek status transaksi pencairan ke OVO.
     *
     * @param string $referenceNo      Idempotency reference awal
     * @param string|null $transactionId OVO reference number bila ada
     * @return array ['success' => bool, 'status' => string, 'details' => array, 'error' => string|null]
     */
    public function topupStatusInquiry(string $referenceNo, ?string $transactionId = null): array;

    /**
     * Cek saldo deposit mitra di OVO.
     *
     * @return array ['success' => bool, 'balance' => float|null, 'currency' => string, 'error' => string|null]
     */
    public function balanceInquiry(): array;
}
