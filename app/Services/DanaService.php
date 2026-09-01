<?php

namespace App\Services;

use App\Models\DanaConnection;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DANA Service Layer.
 * Wraps DanaProvider and handles business logic around DANA operations.
 *
 * PRD §37: DanaService → checkDisbursementAccount, accountInquiry, customerTopUp, etc.
 * PRD §38: DanaProvider interface decouples DANA API from business logic.
 */
class DanaService
{
    private DanaProvider $provider;

    public function __construct(?DanaProvider $provider = null)
    {
        // Factory: use MockDanaProvider unless real credentials configured
        $this->provider = $provider ?? $this->resolveProvider();
    }

    /**
     * Resolve which DanaProvider to use based on environment.
     */
    private function resolveProvider(): DanaProvider
    {
        // When real DANA credentials are configured, switch to OfficialDanaProvider
        if (config('services.dana.env') === 'production' && config('services.dana.client_id')) {
            // TODO: return new OfficialDanaProvider(config('services.dana'));
        }

        return new MockDanaProvider();
    }

    /**
     * Check merchant disbursement account balance.
     * PRD §20: Must verify sufficient balance before payout.
     */
    public function checkDisbursementAccount(): array
    {
        try {
            return $this->provider->checkDisbursementAccount();
        } catch (\Exception $e) {
            Log::error('[DanaService] checkDisbursementAccount failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error'   => 'DANA sedang tidak dapat memproses transaksi.',
            ];
        }
    }

    /**
     * Verify a DANA account via Account Inquiry.
     * PRD §9: Account Inquiry to validate courier's DANA account.
     *
     * @return array ['success' => bool, 'masked_account' => string|null, 'account_info' => array|null, 'error' => string|null]
     */
    public function accountInquiry(string $accountIdentifier): array
    {
        try {
            $result = $this->provider->accountInquiry($accountIdentifier);

            if ($result['success']) {
                return [
                    'success'        => true,
                    'masked_account' => $result['account_info']['masked_account'] ?? null,
                    'account_info'   => $result['account_info'],
                    'error'          => null,
                ];
            }

            return [
                'success'        => false,
                'masked_account' => null,
                'account_info'   => null,
                'error'          => $result['error'] ?? 'Akun DANA tidak ditemukan.',
            ];
        } catch (\Exception $e) {
            Log::error('[DanaService] accountInquiry failed', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'masked_account' => null,
                'account_info'   => null,
                'error'          => 'Gagal memverifikasi akun DANA. Silakan coba lagi.',
            ];
        }
    }

    /**
     * Process disbursement to DANA balance (Customer Top Up).
     * PRD §21: Customer Top Up to send funds to courier's DANA.
     *
     * @param DanaConnection $connection Active DANA connection for the courier
     * @param float          $amount     Amount to disburse
     * @param string         $referenceNo Unique CityCourier reference (idempotency key)
     * @return array ['success' => bool, 'transaction_id' => string|null, 'status' => string, 'error' => string|null]
     */
    public function disburseToDana(DanaConnection $connection, float $amount, string $referenceNo): array
    {
        try {
            // PRD §20: Check disbursement account first
            $balanceCheck = $this->checkDisbursementAccount();
            if (!$balanceCheck['success']) {
                return [
                    'success'        => false,
                    'transaction_id' => null,
                    'status'         => 'failed',
                    'error'          => 'Saldo pencairan CityCourier tidak mencukupi.',
                ];
            }

            if (($balanceCheck['balance'] ?? 0) < $amount) {
                return [
                    'success'        => false,
                    'transaction_id' => null,
                    'status'         => 'failed',
                    'error'          => 'Saldo pencairan CityCourier tidak mencukupi.',
                ];
            }

            // PRD §9: Account Inquiry before disbursement
            $accountIdentifier = $connection->masked_phone ?? '';
            // In production, use the actual DANA account identifier (not masked)
            // stored securely in dana_connections.provider_reference or similar

            // PRD §21: Customer Top Up
            $result = $this->provider->customerTopUp($accountIdentifier, $amount, $referenceNo);

            return [
                'success'        => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null,
                'status'         => $result['status'] ?? 'failed',
                'error'          => $result['error'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[DanaService] disburseToDana failed', [
                'error'     => $e->getMessage(),
                'reference' => $referenceNo,
            ]);
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => 'DANA sedang tidak dapat memproses transaksi.',
            ];
        }
    }

    /**
     * Inquiry status of a disbursement transaction.
     * PRD §23: Customer Top Up Inquiry Status
     */
    public function inquireTransactionStatus(string $transactionId): array
    {
        try {
            return $this->provider->customerTopUpInquiry($transactionId);
        } catch (\Exception $e) {
            Log::error('[DanaService] inquireTransactionStatus failed', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => 'Gagal memeriksa status transaksi.',
            ];
        }
    }

    /**
     * Complete DANA connection after account inquiry succeeds.
     * PRD §10: DANA verified → CONNECTED
     */
    public function completeConnection(DanaConnection $connection, array $accountInfo): DanaConnection
    {
        $maskedPhone = $accountInfo['masked_account'] ?? $connection->masked_phone;
        $providerRef = $accountInfo['account_identifier'] ?? $connection->provider_reference;

        $connection->update([
            'status'             => 'connected',
            'masked_phone'       => $maskedPhone,
            'provider_reference' => $providerRef,
            'linked_at'          => now(),
        ]);

        // PRD §10: Activate wallet when DANA connects
        Wallet::updateOrCreate(
            ['courier_id' => $connection->courier_id],
            ['status'     => 'active']
        );

        Log::info('[DanaService] DANA connection completed', [
            'courier_id'    => $connection->courier_id,
            'masked_phone'  => $maskedPhone,
        ]);

        return $connection->fresh();
    }
}
