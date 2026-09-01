<?php

namespace App\Services;

use App\Models\DanaConnection;
use App\Models\Setting;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

/**
 * DANA Service Layer.
 * Wraps DanaProvider and handles business logic for DANA Widget Binding.
 *
 * Official Flow (PRD §7, §55):
 * 1. applyOTT() → OTT token
 * 2. Redirect to DANA App → user authorizes
 * 3. DANA returns authCode via callback
 * 4. applyToken(authCode) → accessToken
 * 5. Query user profile → masked phone
 * 6. Store connection
 *
 * PRD §37: DanaService → business logic layer.
 * PRD §38: DanaProvider interface → API layer.
 */
class DanaService
{
    private DanaProvider $provider;

    public function __construct(?DanaProvider $provider = null)
    {
        $this->provider = $provider ?? $this->resolveProvider();
    }

    private function resolveProvider(): DanaProvider
    {
        // Baca kredensial dari Database Settings (Admin Panel) atau fallback env/config.
        $mode = Setting::get('dana_mode', env('DANA_MODE', config('services.dana.mode', 'mock')));
        $clientId = Setting::get('dana_client_id', env('DANA_CLIENT_ID', config('services.dana.client_id', '')));
        $privateKey = Setting::get('dana_private_key', env('DANA_PRIVATE_KEY', config('services.dana.private_key', '')));

        if (in_array($mode, ['sandbox', 'production'], true) && $clientId && $privateKey) {
            $config = [
                'mode'          => $mode,
                'env'           => $mode,
                'client_id'     => $clientId,
                'client_secret' => Setting::get('dana_client_secret', env('DANA_CLIENT_SECRET', config('services.dana.client_secret', ''))),
                'merchant_id'   => Setting::get('dana_merchant_id', env('DANA_MERCHANT_ID', config('services.dana.merchant_id', ''))),
                'private_key'   => $privateKey,
                'api_base_url'  => Setting::get('dana_api_base_url', env('DANA_API_BASE_URL', config('services.dana.api_base_url', 'https://api.sandbox.dana.id'))),
                'callback_url'  => Setting::get('dana_callback_url', env('DANA_CALLBACK_URL', config('services.dana.callback_url', ''))),
            ];
            return new OfficialDanaProvider($config);
        }
        return new MockDanaProvider();
    }

    /**
     * Step 1: Get OTT token for DANA Widget Binding.
     * PRD §7: Hubungkan DANA
     *
     * @return array ['success' => bool, 'ott' => string|null, 'redirect_url' => string|null, 'error' => string|null]
     */
    public function beginBinding(int $courierId, ?string $phoneNumber = null): array
    {
        try {
            $result = $this->provider->applyOTT($phoneNumber);

            if ($result['success']) {
                // Store OTT in connection record for later verification
                $connection = DanaConnection::updateOrCreate(
                    ['courier_id' => $courierId],
                    [
                        'status'     => 'pending',
                        'session_id' => $result['ott'],
                    ]
                );

                Log::info('[DanaService] Binding initiated', [
                    'courier_id' => $courierId,
                    'ott'        => $result['ott'],
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[DanaService] beginBinding failed', ['error' => $e->getMessage()]);
            return [
                'success'      => false,
                'ott'          => null,
                'redirect_url' => null,
                'error'        => 'Gagal memulai penghubungan DANA.',
            ];
        }
    }

    /**
     * Step 4: Exchange authCode for accessToken.
     * PRD §10: DANA valid → CONNECTED
     *
     * @param int    $courierId
     * @param string $authCode From DANA callback
     * @return array ['success' => bool, 'masked_phone' => string|null, 'error' => string|null]
     */
    public function completeBinding(int $courierId, string $authCode): array
    {
        try {
            // Exchange authCode for accessToken
            $tokenResult = $this->provider->applyToken($authCode);

            if (!$tokenResult['success']) {
                return [
                    'success'      => false,
                    'masked_phone' => null,
                    'error'        => $tokenResult['error'] ?? 'Gagal verifikasi DANA.',
                ];
            }

            $accessToken = $tokenResult['access_token'];
            $refreshToken = $tokenResult['refresh_token'];
            $tokenExpiresAt = $tokenResult['expires_at'] ?? null;

            // Query user profile to get masked phone
            $profileResult = $this->provider->queryUserProfile($accessToken);
            $maskedPhone = $profileResult['profile']['masked_phone'] ?? null;

            // Update connection record
            $connection = DanaConnection::where('courier_id', $courierId)->first();

            if (!$connection) {
                $connection = new DanaConnection(['courier_id' => $courierId]);
            }

            $connection->update([
                'status'             => 'connected',
                'masked_phone'       => $maskedPhone,
                'provider_reference' => $authCode,
                'access_token'       => $accessToken,
                'refresh_token'      => $refreshToken,
                'token_expires_at'   => $tokenExpiresAt,
                'linked_at'          => now(),
            ]);

            // Activate wallet (PRD §10)
            Wallet::updateOrCreate(
                ['courier_id' => $courierId],
                ['status'     => 'active']
            );

            Log::info('[DanaService] Binding completed', [
                'courier_id'   => $courierId,
                'masked_phone' => $maskedPhone,
            ]);

            return [
                'success'      => true,
                'masked_phone' => $maskedPhone,
                'error'        => null,
            ];
        } catch (\Exception $e) {
            Log::error('[DanaService] completeBinding failed', ['error' => $e->getMessage()]);
            return [
                'success'      => false,
                'masked_phone' => null,
                'error'        => 'Gagal menyelesaikan penghubungan DANA.',
            ];
        }
    }

    /**
     * Step: Unbind DANA account.
     * PRD §34: Putuskan DANA
     */
    public function unbindAccount(int $courierId): array
    {
        try {
            $connection = DanaConnection::where('courier_id', $courierId)
                ->where('status', 'connected')
                ->first();

            if (!$connection || !$connection->access_token) {
                return ['success' => false, 'error' => 'Tidak ada koneksi DANA aktif.'];
            }

            // Call DANA unbind API
            $result = $this->provider->accountUnbinding($connection->access_token);

            $connection->update([
                'status'       => 'revoked',
                'revoked_at'   => now(),
                'access_token'  => null,
                'refresh_token' => null,
            ]);

            // Deactivate wallet
            $wallet = Wallet::where('courier_id', $courierId)->first();
            if ($wallet) {
                $wallet->update(['status' => 'not_active']);
            }

            Log::info('[DanaService] Account unbound', ['courier_id' => $courierId]);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            Log::error('[DanaService] unbindAccount failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Gagal memutuskan DANA.'];
        }
    }

    /**
     * Query user profile via DANA API.
     */
    public function getUserProfile(int $courierId): array
    {
        try {
            $connection = DanaConnection::where('courier_id', $courierId)
                ->where('status', 'connected')
                ->first();

            if (!$connection || !$connection->access_token) {
                return ['success' => false, 'profile' => null, 'error' => 'DANA tidak terhubung.'];
            }

            return $this->provider->queryUserProfile($connection->access_token);
        } catch (\Exception $e) {
            return ['success' => false, 'profile' => null, 'error' => 'Gagal mengambil profil DANA.'];
        }
    }

    /**
     * Check DANA balance via API.
     */
    public function getBalance(int $courierId): array
    {
        try {
            $connection = DanaConnection::where('courier_id', $courierId)
                ->where('status', 'connected')
                ->first();

            if (!$connection || !$connection->access_token) {
                return ['success' => false, 'balance' => null, 'error' => 'DANA tidak terhubung.'];
            }

            return $this->provider->balanceInquiry($connection->access_token);
        } catch (\Exception $e) {
            return ['success' => false, 'balance' => null, 'error' => 'Gagal mengecek saldo DANA.'];
        }
    }

    /**
     * Disburse funds to DANA user (Customer Top Up).
     * PRD §21: Customer Top Up
     */
    public function disburseToDana(DanaConnection $connection, float $amount, string $referenceNo): array
    {
        try {
            $accountIdentifier = $connection->masked_phone ?? '';

            $result = $this->provider->customerTopUp($accountIdentifier, $amount, $referenceNo);

            return [
                'success'        => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null,
                'status'         => $result['status'] ?? 'failed',
                'error'          => $result['error'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('[DanaService] disburseToDana failed', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => 'Gagal mengirim dana ke DANA.',
            ];
        }
    }

    /**
     * Inquiry disbursement status.
     * PRD §23: Customer Top Up Inquiry Status
     */
    public function inquireTransactionStatus(string $transactionId): array
    {
        try {
            return $this->provider->customerTopUpInquiry($transactionId);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status'  => 'unknown',
                'details' => [],
                'error'   => 'Gagal memeriksa status transaksi.',
            ];
        }
    }
}
