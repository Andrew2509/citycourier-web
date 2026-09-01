<?php

namespace App\Services;

/**
 * DANA Provider Interface — Widget Binding API.
 *
 * Based on official DANA documentation:
 * https://dashboard.dana.id/api-docs-v2/guide/dana-widget/widget-binding
 * https://github.com/dana-id/dana-node
 *
 * Flow:
 * 1. applyOTT() → get one-time token
 * 2. Redirect to DANA App with OTT → user authorizes
 * 3. DANA returns authCode
 * 4. applyToken(authCode) → get accessToken
 * 5. Account bound
 *
 * PRD §38: DanaProvider interface — decouple DANA API from business logic.
 * PRD §73: MockDanaProvider for dev, OfficialDanaProvider when credentials available.
 */
interface DanaProvider
{
    /**
     * Apply One Time Token (OTT) for DANA Widget Binding.
     * Used to generate the authorization URL / deep link to DANA App.
     *
     * POST /rest/v1.1/qr/apply-ott
     *
     * @param string $phoneNumber User's phone number (optional, for seamless binding)
     * @return array ['success' => bool, 'ott' => string|null, 'redirect_url' => string|null, 'error' => string|null]
     */
    public function applyOTT(?string $phoneNumber = null): array;

    /**
     * Apply Token — exchange authCode for accessToken.
     * Finalizes the account binding process.
     *
     * POST /v1.0/access-token/b2b2c.htm
     *
     * @param string $authCode The authorization code returned from DANA callback
     * @return array ['success' => bool, 'access_token' => string|null, 'refresh_token' => string|null, 'error' => string|null]
     */
    public function applyToken(string $authCode): array;

    /**
     * Account Unbinding — revoke the accessToken.
     * POST /v1.0/registration-account-unbinding.htm
     *
     * @param string $accessToken The stored access token
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function accountUnbinding(string $accessToken): array;

    /**
     * Query User Profile — get DANA user info (masked phone, balance, etc).
     * POST /dana/member/query/queryUserProfile.htm
     *
     * @param string $accessToken The stored access token
     * @return array ['success' => bool, 'profile' => array|null, 'error' => string|null]
     */
    public function queryUserProfile(string $accessToken): array;

    /**
     * Balance Inquiry — query user's DANA balance.
     * POST /v1.0/balance-inquiry.htm
     *
     * @param string $accessToken The stored access token
     * @return array ['success' => bool, 'balance' => float|null, 'currency' => string|null, 'error' => string|null]
     */
    public function balanceInquiry(string $accessToken): array;

    /**
     * Customer Top Up — disburse funds to DANA user.
     * POST via Disbursement API
     *
     * @param string $accountIdentifier Verified DANA account
     * @param float  $amount            Amount in IDR
     * @param string $referenceNo       Unique reference
     * @return array ['success' => bool, 'transaction_id' => string|null, 'status' => string, 'error' => string|null]
     */
    public function customerTopUp(string $accountIdentifier, float $amount, string $referenceNo): array;

    /**
     * Customer Top Up Inquiry — check disbursement status.
     *
     * @param string $transactionId The transaction ID from customerTopUp
     * @return array ['success' => bool, 'status' => string, 'details' => array, 'error' => string|null]
     */
    public function customerTopUpInquiry(string $transactionId): array;
}
