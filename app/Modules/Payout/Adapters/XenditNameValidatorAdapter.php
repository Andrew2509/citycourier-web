<?php

namespace App\Modules\Payout\Adapters;

use App\Modules\Payment\Services\GatewayCredentialsResolver;
use App\Modules\Payout\Contracts\AccountInquiryAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adapter resmi Xendit Data Services (Bank Name Validator & E-Wallet Validator).
 *
 * Catatan dokumentasi resmi docs.xendit.co:
 * Xendit Payouts v2 (POST /v2/payouts) TIDAK menyediakan Account Name Inquiry universal.
 * Fitur verifikasi nama pemilik rekening/e-wallet berada di produk terpisah
 * "Xendit Data Services" (Name Validator) yang memerlukan aktivasi khusus oleh Xendit.
 *
 * Jika fitur Name Validator belum diaktifkan pada akun Xendit merchant,
 * adapter ini jujur melaporkan status "unverified" (tidak mendukung inquiry otomatis)
 * sehingga frontend menampilkan input nama manual berlabel "Belum Terverifikasi"
 * tanpa pernah membuat nama dummy atau status terverifikasi palsu.
 */
class XenditNameValidatorAdapter implements AccountInquiryAdapterInterface
{
    public function __construct(
        private readonly GatewayCredentialsResolver $credentialsResolver
    ) {}

    public function providerCode(): string
    {
        return 'xendit_name_validator';
    }

    public function isEnabled(): bool
    {
        $creds = $this->credentialsResolver->xendit();
        $cfg = (array) config('payment_wallet.xendit.name_validator', []);

        return $creds['enabled']
            && $creds['configured']
            && (bool) ($cfg['enabled'] ?? false);
    }

    public function supportsChannel(string $destinationType, string $channelCode): bool
    {
        $type = strtolower($destinationType);
        $code = strtoupper($channelCode);

        return in_array($type, ['bank', 'ewallet'], true) && $code !== '';
    }

    public function inquire(string $destinationType, string $channelCode, string $accountNumber): array
    {
        $destType = strtolower(trim($destinationType));
        $normalizedChannel = strtoupper(trim($channelCode));

        if (! $this->isEnabled()) {
            return [
                'supported' => false,
                'found' => false,
                'verification_status' => 'unverified',
                'account_holder_name' => null,
                'can_input_manually' => true,
                'provider' => 'xendit',
                'message' => 'Verifikasi nama otomatis tidak tersedia pada Xendit Payouts standar. Silakan isi nama pemilik secara manual (Belum Terverifikasi).',
            ];
        }

        $creds = $this->credentialsResolver->xendit();
        $cfg = (array) config('payment_wallet.xendit.name_validator', []);
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? $creds['base_url'] ?? 'https://api.xendit.co'), '/');
        $secretKey = trim((string) ($cfg['secret_key'] ?: $creds['secret_key']));
        $timeout = (int) ($creds['timeout_seconds'] ?? 15);

        $path = $destType === 'ewallet'
            ? (string) ($cfg['ewallet_path'] ?? '')
            : (string) ($cfg['bank_path'] ?? '');

        if ($secretKey === '' || $path === '') {
            return [
                'supported' => false,
                'found' => false,
                'verification_status' => 'unverified',
                'account_holder_name' => null,
                'can_input_manually' => true,
                'provider' => 'xendit',
                'message' => 'Endpoint Xendit Name Validator belum dikonfigurasi. Isi nama pemilik secara manual (Belum Terverifikasi).',
            ];
        }

        $cleanChannel = str_starts_with($normalizedChannel, 'ID_')
            ? substr($normalizedChannel, 3)
            : $normalizedChannel;

        $payload = $destType === 'ewallet'
            ? [
                'channel_code' => $normalizedChannel,
                'ewallet_type' => $cleanChannel,
                'account_number' => $accountNumber,
                'phone_number' => $accountNumber,
            ]
            : [
                'bank_code' => $cleanChannel,
                'channel_code' => $normalizedChannel,
                'bank_account_number' => $accountNumber,
                'account_number' => $accountNumber,
            ];

        try {
            $response = Http::timeout($timeout)
                ->withBasicAuth($secretKey, '')
                ->post($baseUrl.'/'.ltrim($path, '/'), $payload);

            if (! $response->successful()) {
                Log::warning('[XenditNameValidator] Verification rejected', [
                    'channel' => $normalizedChannel,
                    'status' => $response->status(),
                ]);

                return [
                    'supported' => true,
                    'found' => false,
                    'verification_status' => 'verification_failed',
                    'account_holder_name' => null,
                    'can_input_manually' => true,
                    'provider' => $this->providerCode(),
                    'message' => 'Verifikasi nama akun gagal diproses oleh Xendit Name Validator. Periksa nomor atau isi nama secara manual.',
                ];
            }

            $json = $response->json() ?: [];
            $status = strtoupper((string) ($json['status'] ?? $json['result'] ?? ''));
            $holderName = trim((string) (
                $json['account_holder_name']
                ?? $json['bank_account_holder_name']
                ?? $json['name']
                ?? $json['account_name']
                ?? ''
            ));

            if ($holderName !== '' && ! in_array($status, ['FAILED', 'INVALID', 'NOT_FOUND'], true)) {
                return [
                    'supported' => true,
                    'found' => true,
                    'verification_status' => 'auto_verified',
                    'account_holder_name' => strtoupper($holderName),
                    'can_input_manually' => false,
                    'provider' => $this->providerCode(),
                    'message' => 'Nama pemilik akun terverifikasi otomatis oleh Xendit.',
                ];
            }

            return [
                'supported' => true,
                'found' => false,
                'verification_status' => 'verification_failed',
                'account_holder_name' => null,
                'can_input_manually' => true,
                'provider' => $this->providerCode(),
                'message' => 'Nomor rekening/e-wallet tidak ditemukan atau gagal diverifikasi.',
            ];
        } catch (\Throwable $e) {
            Log::warning('[XenditNameValidator] Exception during verification', [
                'channel' => $normalizedChannel,
                'error' => $e->getMessage(),
            ]);

            return [
                'supported' => true,
                'found' => false,
                'verification_status' => 'verification_failed',
                'account_holder_name' => null,
                'can_input_manually' => true,
                'provider' => $this->providerCode(),
                'message' => 'Gagal menghubungi layanan verifikasi nama Xendit. Silakan coba lagi atau isi nama secara manual.',
            ];
        }
    }
}
