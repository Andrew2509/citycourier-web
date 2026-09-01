<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Mock DANA provider for development and testing.
 * Simulates all DANA Widget Binding API responses.
 *
 * PRD §73: MockDanaProvider used when credentials unavailable.
 *          Replace with OfficialDanaProvider when DANA credentials arrive.
 */
class MockDanaProvider implements DanaProvider
{
    public function applyOTT(?string $phoneNumber = null): array
    {
        Log::info('[MockDANA] applyOTT called', ['phone' => $phoneNumber]);

        // Generate mock OTT token
        $ott = 'OTT-' . strtoupper(Str::random(16));

        // Build mock redirect URL (sandbox)
        $redirectUrl = config('services.dana.redirect_url', 'https://sandbox.dana.id');
        $redirectUrl .= '/v1.0/widget/binding?' . http_build_query([
            'ott'       => $ott,
            'callback'  => config('services.dana.callback_url', url('/api/courier/dana/callback')),
        ]);

        return [
            'success'      => true,
            'ott'          => $ott,
            'redirect_url' => $redirectUrl,
            'error'        => null,
        ];
    }

    public function applyToken(string $authCode): array
    {
        Log::info('[MockDANA] applyToken called', ['auth_code' => $authCode]);

        // Mock: generate access token
        $accessToken = 'ACC-' . strtoupper(Str::random(32));
        $refreshToken = 'REF-' . strtoupper(Str::random(32));

        return [
            'success'       => true,
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'error'         => null,
        ];
    }

    public function accountUnbinding(string $accessToken): array
    {
        Log::info('[MockDANA] accountUnbinding called');

        return [
            'success' => true,
            'error'   => null,
        ];
    }

    public function queryUserProfile(string $accessToken): array
    {
        Log::info('[MockDANA] queryUserProfile called');

        // Mock: return masked phone and profile info
        return [
            'success' => true,
            'profile' => [
                'phone_number' => '081234567890',
                'masked_phone' => '081234******7890',
                'name'         => 'MOCK USER',
                'kyc_status'   => 'verified',
            ],
            'error' => null,
        ];
    }

    public function balanceInquiry(string $accessToken): array
    {
        Log::info('[MockDANA] balanceInquiry called');

        return [
            'success'  => true,
            'balance'  => 1500000.00,
            'currency' => 'IDR',
            'error'    => null,
        ];
    }

    public function customerTopUp(string $accountIdentifier, float $amount, string $referenceNo): array
    {
        Log::info('[MockDANA] customerTopUp called', [
            'account' => $accountIdentifier,
            'amount'  => $amount,
            'ref'     => $referenceNo,
        ]);

        $transactionId = 'DANA-TX-' . strtoupper(substr(md5($referenceNo), 0, 12));

        return [
            'success'        => true,
            'transaction_id' => $transactionId,
            'status'         => 'pending',
            'error'          => null,
        ];
    }

    public function customerTopUpInquiry(string $transactionId): array
    {
        Log::info('[MockDANA] customerTopUpInquiry called', ['transaction_id' => $transactionId]);

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
