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
        // Baca kredensial dari Database Settings (Admin Panel) — tidak dari env.
        $mode = Setting::get('dana_mode', 'mock');
        $clientId = Setting::get('dana_client_id', '');
        $privateKey = Setting::get('dana_private_key', '');

        if (in_array($mode, ['sandbox', 'production'], true) && $clientId && $privateKey) {
            return new OfficialDanaProvider($this->providerConfig());
        }
        return new MockDanaProvider();
    }

    /**
     * Kumpulkan konfigurasi provider DANA dari Settings (DB).
     * Dipakai oleh resolveProvider() dan halaman admin utk tes koneksi.
     */
    public function providerConfig(): array
    {
        $mode = Setting::get('dana_mode', 'mock');
        return [
            'mode'          => $mode,
            'env'           => $mode,
            'client_id'     => Setting::get('dana_client_id', ''),
            'client_secret' => Setting::get('dana_client_secret', ''),
            'merchant_id'   => Setting::get('dana_merchant_id', ''),
            'public_key'    => Setting::get('dana_public_key', ''),
            'private_key'   => Setting::get('dana_private_key', ''),
            'api_base_url'  => Setting::get('dana_api_base_url', 'https://api.sandbox.dana.id'),
            'callback_url'  => Setting::get('dana_callback_url', url('/api/courier/dana/callback')),
        ];
    }

    /**
     * Provider yang aktif (Official bila mode sandbox/production & kredensial ada).
     * Dipakai halaman admin utk tes koneksi tanpa menyimpan baris ke DB.
     */
    public function activeProvider(): DanaProvider
    {
        $cfg = $this->providerConfig();
        if (in_array($cfg['mode'], ['sandbox', 'production'], true) && $cfg['client_id'] && $cfg['private_key']) {
            return new OfficialDanaProvider($cfg);
        }
        return new MockDanaProvider();
    }

    /**
     * Step 1: Get binding URL/session for DANA Widget Binding.
     * PRD §7, §11-12: generate binding URL + external_id + state_hash.
     *
     * @return array ['success' => bool, 'ott' => string|null, 'redirect_url' => string|null, 'error' => string|null]
     */
    public function beginBinding(int $courierId, ?string $phoneNumber = null): array
    {
        try {
            $result = $this->provider->applyOTT($phoneNumber);

            if ($result['success']) {
                $state = (string) $result['ott'];
                $externalId = 'DANA-BIND-CRR-' . strtoupper((string) \Illuminate\Support\Str::uuid());

                // Store session untuk verifikasi callback (PRD §21) + expiry 10 menit (PRD §17).
                $connection = DanaConnection::updateOrCreate(
                    ['courier_id' => $courierId],
                    [
                        'status'              => 'pending',
                        'external_id'         => $externalId,
                        'session_id'          => $state,
                        'state_hash'          => $this->hashState($state),
                        'session_expires_at'  => now()->addMinutes(10),
                    ]
                );

                Log::info('[DanaService] Binding initiated', [
                    'courier_id' => $courierId,
                    'external_id' => $externalId,
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
                'exception'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Hash state untuk disimpan aman (PRD §21: state_hash, bukan raw state).
     * Tanpa salt courier agar callback publik bisa menemukan sesi hanya dari state.
     */
    public function hashState(string $state): string
    {
        return hash('sha256', $state);
    }

    /**
     * Verifikasi state sesi binding (masih pending & belum kadaluarsa).
     * PRD §17 (expiry 10 menit), §21 (validasi state), §29 (akun beda).
     *
     * @return array ['valid' => bool, 'connection' => DanaConnection|null, 'error' => string|null, 'error_code' => string|null]
     */
    public function resolveSession(string $state, ?int $courierId = null): array
    {
        if (!$state) {
            return ['valid' => false, 'connection' => null, 'error' => 'State tidak ditemukan.', 'error_code' => 'INVALID_STATE'];
        }

        $query = DanaConnection::where('state_hash', $this->hashState($state))->where('status', 'pending');
        if ($courierId) {
            $query->where('courier_id', $courierId);
        }

        $connection = $query->first();

        if (!$connection) {
            return ['valid' => false, 'connection' => null, 'error' => 'Sesi penghubungan tidak ditemukan.', 'error_code' => 'INVALID_STATE'];
        }

        if ($connection->session_expires_at && $connection->session_expires_at->isPast()) {
            $connection->update(['status' => 'expired']);
            return ['valid' => false, 'connection' => $connection, 'error' => 'Sesi DANA telah kedaluwarsa. Silakan hubungkan kembali.', 'error_code' => 'DANA_AUTH_CODE_EXPIRED'];
        }

        return ['valid' => true, 'connection' => $connection, 'error' => null, 'error_code' => null];
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
                    'error_code'   => 'DANA_TOKEN_EXCHANGE_FAILED',
                ];
            }

            $accessToken = $tokenResult['access_token'];
            $refreshToken = $tokenResult['refresh_token'];
            $tokenExpiresAt = $tokenResult['expires_at'] ?? null;

            // Query user profile to get masked phone / user reference
            $profileResult = $this->provider->queryUserProfile($accessToken);
            $maskedPhone = $profileResult['profile']['masked_phone'] ?? null;
            $userReference = $profileResult['profile']['dana_user_reference'] ?? null;

            // Update connection record
            $connection = DanaConnection::where('courier_id', $courierId)->first();

            if (!$connection) {
                $connection = new DanaConnection(['courier_id' => $courierId]);
            }

            $connection->update([
                'status'               => 'connected',
                'masked_phone'         => $maskedPhone,
                'provider_reference'   => $authCode,
                'dana_user_reference'  => $userReference,
                'access_token'         => $accessToken,
                'refresh_token'        => $refreshToken,
                'token_expires_at'     => $tokenExpiresAt ? date('Y-m-d H:i:s', strtotime($tokenExpiresAt)) : null,
                'bound_at'             => now(),
                'linked_at'            => now(),
            ]);

            // Activate wallet (PRD §10)
            Wallet::updateOrCreate(
                ['courier_id' => $courierId],
                ['status'     => 'active']
            );

            Log::info('[DanaService] Binding completed', [
                'courier_id'   => $courierId,
                'dana_user_reference' => $userReference,
                'masked_phone' => $maskedPhone,
            ]);

            return [
                'success'      => true,
                'masked_phone' => $maskedPhone,
                'error'        => null,
                'error_code'   => null,
            ];
        } catch (\Exception $e) {
            Log::error('[DanaService] completeBinding failed', ['error' => $e->getMessage()]);
            return [
                'success'      => false,
                'masked_phone' => null,
                'error'        => 'Gagal menyelesaikan penghubungan DANA.',
                'error_code'   => 'DANA_BINDING_FAILED',
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
                return ['success' => false, 'error' => 'Tidak ada koneksi DANA aktif.', 'error_code' => 'DANA_NOT_CONNECTED'];
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
     * Ambil koneksi aktif & tandai otomatis EXPIRED jika token kedaluwarsa (PRD §33).
     */
    public function freshStatus(int $courierId): ?DanaConnection
    {
        $connection = DanaConnection::where('courier_id', $courierId)->first();

        if ($connection && $connection->isExpired()) {
            $connection->update(['status' => 'expired']);
        }

        return $connection;
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
