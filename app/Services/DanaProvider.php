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
 *
 * Batas fungsi (jangan dicampur):
 *  - DANA Widget Binding  → menghubungkan akun DANA kurir (applyOTT + applyToken).
 *  - Direct Debit Payment → membayar memakai akun terhubung; TIDAK dipakai
 *    untuk mencairkan saldo pendapatan kurir.
 *  - Disbursement to Balance → customerTopUp(), jalur pencairan saldo kurir.
 */
interface DanaProvider
{
    /**
     * Generate the DANA Widget Binding URL (Deeplink Binding).
     *
     * DANA's official guide: merchant calls Deeplink Binding to generate the
     * binding URL by providing redirectUrl, with seamlessData as an optional
     * parameter (seamless binding). The URL opens the DANA App where the user
     * logs in (only when needed) and approves the binding.
     *
     * @param string|null $phoneNumber User's verified phone, only sent for seamless binding
     * @param string|null $externalId  Merchant unique reference for this binding request
     * @param bool        $seamless    Attach seamlessData.mobileNumber when true
     * @return array ['success' => bool, 'ott' => string|null, 'redirect_url' => string|null,
     *                'external_id' => string|null, 'seamless' => bool, 'error' => string|null]
     */
    public function applyOTT(?string $phoneNumber = null, ?string $externalId = null, bool $seamless = false): array;

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
