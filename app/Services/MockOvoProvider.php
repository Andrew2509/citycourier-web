<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mock OVO Provider for development, staging, and automated tests.
 * Menyimulasikan seluruh alur SNAP B2B OVO tanpa koneksi riil ke server OVO.
 */
class MockOvoProvider implements OvoProvider
{
    /** @var array<string, array> */
    public static array $inquiryOverrides = [];

    /** @var array<string, array> */
    public static array $disbursementOverrides = [];

    public function getB2BAccessToken(): array
    {
        Log::info('[MockOVO] getB2BAccessToken called');

        return [
            'success'      => true,
            'access_token' => 'OVO-B2B-' . strtoupper(Str::random(32)),
            'expires_in'   => 900,
            'error'        => null,
        ];
    }

    public function accountInquiry(string $phoneNumber): array
    {
        Log::info('[MockOVO] accountInquiry called', ['phone' => $phoneNumber]);

        // Support test overrides
        if (isset(self::$inquiryOverrides[$phoneNumber])) {
            return self::$inquiryOverrides[$phoneNumber];
        }

        // Simulasi nomor tidak terdaftar: berakhiran '0000' atau tepat '080000000000'
        if (str_ends_with($phoneNumber, '0000') || $phoneNumber === '080000000000' || str_contains($phoneNumber, '999999')) {
            return [
                'success'         => false,
                'customer_name'   => null,
                'customer_number' => $phoneNumber,
                'status'          => null,
                'error'           => 'Nomor HP tidak terdaftar sebagai akun OVO.',
                'error_code'      => 'OVO_ACCOUNT_NOT_FOUND',
            ];
        }

        return [
            'success'         => true,
            'customer_name'   => 'KURIR OVO USER',
            'customer_number' => $phoneNumber,
            'status'          => 'ACTIVE',
            'error'           => null,
            'error_code'      => null,
        ];
    }

    public function customerTopUp(string $customerNumber, float $amount, string $referenceNo): array
    {
        Log::info('[MockOVO] customerTopUp called', [
            'customer_number' => $customerNumber,
            'amount'          => $amount,
            'reference'       => $referenceNo,
        ]);

        if (isset(self::$disbursementOverrides[$referenceNo])) {
            return self::$disbursementOverrides[$referenceNo];
        }

        // Simulasi timeout / processing tertunda
        if (str_contains($referenceNo, 'TIMEOUT')) {
            $transactionId = 'OVO-TX-TIMEOUT-' . strtoupper(substr(md5($referenceNo), 0, 10));
            return [
                'success'        => true,
                'transaction_id' => $transactionId,
                'status'         => 'processing',
                'error'          => null,
            ];
        }

        // Simulasi kegagalan dari provider
        if (str_contains($referenceNo, 'FAIL') || str_contains($referenceNo, 'ERROR')) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => 'OVO disbursement rejected by provider.',
            ];
        }

        $transactionId = 'OVO-TX-' . strtoupper(substr(md5($referenceNo), 0, 12));

        return [
            'success'        => true,
            'transaction_id' => $transactionId,
            'status'         => 'success',
            'error'          => null,
        ];
    }

    public function topupStatusInquiry(string $referenceNo, ?string $transactionId = null): array
    {
        Log::info('[MockOVO] topupStatusInquiry called', [
            'reference'      => $referenceNo,
            'transaction_id' => $transactionId,
        ]);

        return [
            'success'  => true,
            'status'   => 'success',
            'details'  => [
                'reference_no'   => $referenceNo,
                'transaction_id' => $transactionId ?? ('OVO-TX-' . strtoupper(substr(md5($referenceNo), 0, 12))),
                'completed_at'   => now()->toIso8601String(),
            ],
            'error'    => null,
        ];
    }

    public function balanceInquiry(): array
    {
        Log::info('[MockOVO] balanceInquiry called');

        return [
            'success'  => true,
            'balance'  => 50000000.00,
            'currency' => 'IDR',
            'error'    => null,
        ];
    }

    public static function resetOverrides(): void
    {
        self::$inquiryOverrides = [];
        self::$disbursementOverrides = [];
    }
}
