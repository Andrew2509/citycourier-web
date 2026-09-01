<?php

namespace App\Services;

/**
 * Abstract interface for DANA API operations.
 * Implement OfficialDanaProvider when DANA credentials are available.
 * Use MockDanaProvider for development/sandbox.
 *
 * PRD §38: Don't embed DANA code into controllers. Use adapter pattern.
 */
interface DanaProvider
{
    /**
     * Check merchant/disbursement account balance on DANA.
     * PRD §20: Check Disbursement Account
     */
    public function checkDisbursementAccount(): array;

    /**
     * Verify a DANA account (phone number) exists and is active.
     * PRD §9: Account Inquiry
     *
     * @param string $accountIdentifier Phone number or DANA account ID
     * @return array ['success' => bool, 'account_info' => [...], 'error' => string|null]
     */
    public function accountInquiry(string $accountIdentifier): array;

    /**
     * Top up a DANA user's balance (disbursement).
     * PRD §21: Customer Top Up
     *
     * @param string $accountIdentifier Verified DANA account
     * @param float  $amount            Amount in IDR
     * @param string $referenceNo       Unique reference for this transaction
     * @return array ['success' => bool, 'transaction_id' => string|null, 'status' => string, 'error' => string|null]
     */
    public function customerTopUp(string $accountIdentifier, float $amount, string $referenceNo): array;

    /**
     * Inquiry the status of a previous Customer Top Up.
     * PRD §23: Inquiry Status
     *
     * @param string $transactionId The transaction/top-up ID from customerTopUp
     * @return array ['success' => bool, 'status' => string, 'details' => [...], 'error' => string|null]
     */
    public function customerTopUpInquiry(string $transactionId): array;
}
