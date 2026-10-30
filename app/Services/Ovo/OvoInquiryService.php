<?php

namespace App\Services\Ovo;

use App\Services\OvoProvider;

/**
 * OVO Inquiry Service.
 * Inquiry resmi OVO SNAP:
 * - Account inquiry (cek nomor HP terdaftar & nama pemilik akun).
 * - Balance inquiry deposit merchant (skema deposit).
 */
class OvoInquiryService
{
    private OvoProvider $provider;

    public function __construct(?OvoProvider $provider = null)
    {
        $this->provider = $provider ?? (new \App\Services\OvoService())->activeProvider();
    }

    /**
     * Verifikasi nomor OVO: nomor terdaftar? siapa pemiliknya? statusnya?
     *
     * @return array ['success' => bool, 'customer_name' => ?string, 'customer_number' => ?string, 'status' => ?string, 'error' => ?string, 'error_code' => ?string]
     */
    public function accountInquiry(string $phoneNumber): array
    {
        return $this->provider->accountInquiry($phoneNumber);
    }

    /**
     * Cek saldo deposit merchant di OVO (bila memakai skema deposit).
     *
     * @return array ['success' => bool, 'balance' => ?float, 'currency' => string, 'error' => ?string]
     */
    public function merchantBalance(): array
    {
        return $this->provider->balanceInquiry();
    }
}
