<?php

namespace App\Services;

use App\Models\DanaConnection;
use App\Models\Setting;
use App\Models\Wallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * OVO Service Layer.
 * Mengelola integrasi OVO SNAP B2B untuk dompet digital kurir City Courier.
 */
class OvoService
{
    private OvoProvider $provider;

    public function __construct(?OvoProvider $provider = null)
    {
        $this->provider = $provider ?? $this->resolveProvider();
    }

    private function resolveProvider(): OvoProvider
    {
        $mode = Setting::get('ovo_mode', config('services.ovo.mode', 'mock'));
        $clientId = Setting::get('ovo_client_id', config('services.ovo.client_id', ''));
        $privateKey = Setting::get('ovo_private_key', config('services.ovo.private_key', ''));

        if (in_array($mode, ['sandbox', 'production'], true) && $clientId && $privateKey) {
            return new OfficialOvoProvider($this->providerConfig());
        }

        return new MockOvoProvider();
    }

    public function providerConfig(): array
    {
        $mode = Setting::get('ovo_mode', config('services.ovo.mode', 'mock'));
        return [
            'mode'          => $mode,
            'client_id'     => Setting::get('ovo_client_id', config('services.ovo.client_id', '')),
            'client_secret' => Setting::get('ovo_client_secret', config('services.ovo.client_secret', '')),
            'partner_id'    => Setting::get('ovo_partner_id', config('services.ovo.partner_id', '')),
            'merchant_id'   => Setting::get('ovo_merchant_id', config('services.ovo.merchant_id', '')),
            'api_base_url'  => Setting::get('ovo_api_base_url', config('services.ovo.api_base_url', 'https://api.ovo.id')),
            'public_key'    => Setting::get('ovo_public_key', config('services.ovo.public_key', '')),
            'private_key'   => Setting::get('ovo_private_key', config('services.ovo.private_key', '')),
        ];
    }

    public function activeProvider(): OvoProvider
    {
        return $this->provider;
    }

    /**
     * Validasi format nomor HP Indonesia (OVO).
     */
    public function validatePhoneNumber(string $phoneNumber): array
    {
        $clean = preg_replace('/[^0-9+]/', '', trim($phoneNumber));

        if (str_starts_with($clean, '+62')) {
            $clean = '0' . substr($clean, 3);
        } elseif (str_starts_with($clean, '62')) {
            $clean = '0' . substr($clean, 2);
        }

        // Nomor seluler Indonesia harus mulai dengan 08 dan memiliki panjang 10 - 14 digit
        if (!preg_match('/^08[0-9]{8,12}$/', $clean)) {
            return [
                'valid'            => false,
                'normalized_phone' => null,
                'error'            => 'Format nomor HP OVO tidak valid. Gunakan format seluler Indonesia (contoh: 081234567890).',
                'error_code'       => 'INVALID_PHONE_FORMAT',
            ];
        }

        return [
            'valid'            => true,
            'normalized_phone' => $clean,
            'error'            => null,
            'error_code'       => null,
        ];
    }

    /**
     * Mask nomor telepon (mis. 081234567890 -> 0812****7890).
     */
    public function maskPhoneNumber(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 7) {
            return $phone;
        }

        $prefix = substr($phone, 0, 4);
        $suffix = substr($phone, -4);
        $maskedLength = max(4, $len - 8);

        return $prefix . str_repeat('*', $maskedLength) . $suffix;
    }

    /**
     * Hubungkan akun OVO kurir dengan verifikasi Account Inquiry.
     */
    public function connectAccount(int $courierId, string $phoneNumber, string $accountName, bool $consent): array
    {
        if (!$consent) {
            return [
                'success'    => false,
                'error'      => 'Persetujuan penggunaan akun OVO wajib disetujui.',
                'error_code' => 'CONSENT_REQUIRED',
            ];
        }

        $trimmedName = trim($accountName);
        if (empty($trimmedName)) {
            return [
                'success'    => false,
                'error'      => 'Nama pemilik akun OVO wajib diisi.',
                'error_code' => 'ACCOUNT_NAME_REQUIRED',
            ];
        }

        // 1. Validasi format nomor HP
        $val = $this->validatePhoneNumber($phoneNumber);
        if (!$val['valid']) {
            return [
                'success'    => false,
                'error'      => $val['error'],
                'error_code' => $val['error_code'],
            ];
        }
        $normalizedPhone = $val['normalized_phone'];

        // 2. Account Inquiry ke API OVO
        try {
            $inquiry = $this->provider->accountInquiry($normalizedPhone);
            if (!$inquiry['success']) {
                return [
                    'success'    => false,
                    'error'      => $inquiry['error'] ?? 'Nomor HP tidak terdaftar sebagai akun OVO.',
                    'error_code' => $inquiry['error_code'] ?? 'OVO_ACCOUNT_NOT_FOUND',
                ];
            }

            $verifiedName = !empty($inquiry['customer_name']) ? $inquiry['customer_name'] : $trimmedName;
            $maskedPhone = $this->maskPhoneNumber($normalizedPhone);

            // 3. Simpan koneksi akun OVO aman
            $connection = DanaConnection::where('courier_id', $courierId)->first();
            if (!$connection) {
                $connection = new DanaConnection(['courier_id' => $courierId]);
            }

            $connection->fill([
                'provider'           => 'ovo',
                'status'             => 'connected',
                'account_name'       => $verifiedName,
                'masked_phone'       => $maskedPhone,
                'phone_encrypted'    => Crypt::encryptString($normalizedPhone),
                'consent_at'         => now(),
                'provider_reference' => 'OVO-CONN-' . substr(md5($normalizedPhone), 0, 10),
                'bound_at'           => now(),
                'linked_at'          => now(),
                'revoked_at'         => null,
            ]);
            $connection->save();

            // 4. Aktifkan dompet kurir
            Wallet::updateOrCreate(
                ['courier_id' => $courierId],
                ['status' => 'active']
            );

            Log::info('[OvoService] Akun OVO terhubung', [
                'courier_id'   => $courierId,
                'masked_phone' => $maskedPhone,
                'account_name' => $verifiedName,
            ]);

            return [
                'success'      => true,
                'connection'   => $connection,
                'masked_phone' => $maskedPhone,
                'account_name' => $verifiedName,
                'provider'     => 'OVO',
                'status'       => 'CONNECTED',
                'error'        => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[OvoService] Gagal menghubungkan OVO', ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'error'      => 'Gagal memverifikasi akun OVO: ' . $e->getMessage(),
                'error_code' => 'OVO_CONNECTION_ERROR',
            ];
        }
    }

    /**
     * Ubah nomor atau informasi akun OVO.
     */
    public function updateAccount(int $courierId, string $phoneNumber, string $accountName, bool $consent): array
    {
        return $this->connectAccount($courierId, $phoneNumber, $accountName, $consent);
    }

    /**
     * Putuskan koneksi OVO kurir.
     */
    public function unbindAccount(int $courierId): array
    {
        try {
            $connection = DanaConnection::where('courier_id', $courierId)
                ->where('status', 'connected')
                ->first();

            if (!$connection) {
                return [
                    'success'    => false,
                    'error'      => 'Tidak ada koneksi OVO aktif.',
                    'error_code' => 'OVO_NOT_CONNECTED',
                ];
            }

            $connection->update([
                'status'     => 'revoked',
                'revoked_at' => now(),
            ]);

            // Nonaktifkan wallet kurir
            $wallet = Wallet::where('courier_id', $courierId)->first();
            if ($wallet) {
                $wallet->update(['status' => 'not_active']);
            }

            Log::info('[OvoService] Koneksi OVO diputuskan', ['courier_id' => $courierId]);

            return [
                'success' => true,
                'message' => 'Koneksi OVO berhasil diputuskan.',
                'error'   => null,
            ];
        } catch (\Throwable $e) {
            Log::error('[OvoService] unbindAccount failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error'   => 'Gagal memutuskan akun OVO.',
            ];
        }
    }

    /**
     * Dapatkan nomor HP mentah (didekripsi dari penyimpanan).
     */
    public function getDecryptedPhone(DanaConnection $connection): ?string
    {
        if (!empty($connection->phone_encrypted)) {
            try {
                return Crypt::decryptString($connection->phone_encrypted);
            } catch (\Throwable $e) {
                Log::warning('[OvoService] Gagal mendekripsi nomor HP OVO', ['id' => $connection->id]);
            }
        }

        return $connection->masked_phone;
    }

    /**
     * Eksekusi pencairan dana ke OVO kurir via Customer Top Up.
     */
    public function disburseToOvo(DanaConnection $connection, float $amount, string $referenceNo): array
    {
        $rawPhone = $this->getDecryptedPhone($connection);
        if (!$rawPhone) {
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'failed',
                'error'          => 'Nomor OVO tujuan tidak valid.',
            ];
        }

        try {
            $result = $this->provider->customerTopUp($rawPhone, $amount, $referenceNo);

            return [
                'success'        => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null,
                'status'         => $result['status'] ?? 'failed',
                'error'          => $result['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('[OvoService] disburseToOvo exception', ['error' => $e->getMessage()]);
            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'processing',
                'error'          => 'Pencairan OVO mengalami kendala koneksi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cek status transaksi pencairan.
     */
    public function inquireTransactionStatus(string $referenceNo, ?string $transactionId = null): array
    {
        return $this->provider->topupStatusInquiry($referenceNo, $transactionId);
    }

    /**
     * Ambil koneksi OVO aktif kurir.
     */
    public function freshStatus(int $courierId): ?DanaConnection
    {
        return DanaConnection::where('courier_id', $courierId)->first();
    }
}
