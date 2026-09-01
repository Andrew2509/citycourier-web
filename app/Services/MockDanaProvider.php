<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Mock DANA provider for development and testing.
 * Replace with OfficialDanaProvider when DANA credentials are available.
 *
 * PRD §73: MockDanaProvider → DanaDirectProvider when credentials arrive.
 *          UI and business logic must NOT need to be reworked.
 */
class MockDanaProvider implements DanaProvider
{
    public function checkDisbursementAccount(): array
    {
        Log::info('[MockDANA] checkDisbursementAccount called');

        return [
            'success'        => true,
            'balance'        => 50000000.00, // Rp50.000.000 mock merchant balance
            'currency'       => 'IDR',
            'account_status' => 'active',
        ];
    }

    public function accountInquiry(string $accountIdentifier): array
    {
        Log::info('[MockDANA] accountInquiry called', ['account' => $accountIdentifier]);

        // Mock: any phone starting with 08 is valid
        if (preg_match('/^08\d{8,12}$/', $accountIdentifier)) {
            return [
                'success'      => true,
                'account_info' => [
                    'account_identifier' => $accountIdentifier,
                    'masked_account'      => substr($accountIdentifier, 0, 6) . '******' . substr($accountIdentifier, -4),
                    'account_status'      => 'active',
                    'account_type'        => 'DANA',
                    'name'                => 'MOCK USER',
                ],
                'error' => null,
            ];
        }

        return [
            'success'      => false,
            'account_info' => null,
            'error'        => 'Akun DANA tidak ditemukan.',
        ];
    }

    public function customerTopUp(string $accountIdentifier, float $amount, string $referenceNo): array
    {
        Log::info('[MockDANA] customerTopUp called', [
            'account' => $accountIdentifier,
            'amount'  => $amount,
            'ref'     => $referenceNo,
        ]);

        // Mock: simulate success
        $transactionId = 'DANA-TX-' . strtoupper(substr(md5($referenceNo), 0, 12));

        return [
            'success'        => true,
            'transaction_id' => $transactionId,
            'status'         => 'pending', // DANA returns pending initially
            'error'          => null,
        ];
    }

    public function customerTopUpInquiry(string $transactionId): array
    {
        Log::info('[MockDANA] customerTopUpInquiry called', ['transaction_id' => $transactionId]);

        // Mock: simulate success after inquiry
        return [
            'success'  => true,
            'status'   => 'success',
            'details'  => [
                'transaction_id' => $transactionId,
                'completed_at'   => now()->toIso8601String(),
            ],
            'error' => null,
        ];
    }
}
