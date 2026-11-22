<?php

namespace App\Modules\Payout\Services;

use App\Modules\Payout\Adapters\XenditNameValidatorAdapter;

/**
 * Orkestrasi verifikasi nama pemilik rekening/e-wallet resmi.
 *
 * Hanya mengembalikan status `auto_verified` bila adapter resmi (Xendit Data Services
 * Name Validator) benar-benar aktif & mengembalikan nama pemilik dari API penyedia.
 * Tidak pernah membuat nama dummy maupun status terverifikasi palsu.
 */
class AccountInquiryService
{
    public function __construct(
        private readonly XenditPayoutService $xenditPayoutService,
        private readonly XenditNameValidatorAdapter $xenditNameValidator
    ) {}

    public function hasOfficialInquiry(): bool
    {
        return $this->xenditNameValidator->isEnabled();
    }

    /**
     * @return array{
     *   supported: bool,
     *   found: bool,
     *   verification_status: string,
     *   account_holder_name: string|null,
     *   can_input_manually: bool,
     *   provider: string,
     *   message: string|null
     * }
     */
    public function inquire(string $destinationType, string $channelCode, string $accountNumber): array
    {
        $destType = strtolower(trim($destinationType));
        $normalizedChannel = $this->xenditPayoutService->normalizeChannelCode($channelCode);

        if (! $this->xenditPayoutService->isChannelSupported($destType, $normalizedChannel)) {
            return [
                'supported' => false,
                'found' => false,
                'verification_status' => 'unavailable',
                'account_holder_name' => null,
                'can_input_manually' => false,
                'provider' => 'xendit',
                'message' => "Kanal {$normalizedChannel} tidak tersedia atau tidak didukung untuk payout Xendit.",
            ];
        }

        if (! $this->xenditNameValidator->isEnabled()) {
            return [
                'supported' => false,
                'found' => false,
                'verification_status' => 'unverified',
                'account_holder_name' => null,
                'can_input_manually' => true,
                'provider' => 'xendit',
                'message' => 'Pengecekan nama otomatis tidak tersedia di Xendit Payouts standar. Masukkan nama pemilik sesuai buku tabungan/akun (Belum Terverifikasi).',
            ];
        }

        return $this->xenditNameValidator->inquire($destType, $normalizedChannel, $accountNumber);
    }
}
