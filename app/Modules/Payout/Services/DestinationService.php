<?php

namespace App\Modules\Payout\Services;

use App\Models\Courier;
use App\Models\User;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Wallet\Models\PayoutDestination;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DestinationService
{
    /**
     * Fallback static catalog matching Xendit Payouts v2 IDR channels.
     * Runtime filtering uses XenditPayoutService::availablePayoutChannels().
     */
    public const SUPPORTED_CHANNELS = [
        'bank' => XenditPayoutService::BANK_CHANNELS,
        'ewallet' => XenditPayoutService::EWALLET_CHANNELS,
    ];

    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly WalletLedgerManager $walletManager,
        private readonly XenditPayoutService $xenditPayoutService,
        private readonly AccountInquiryService $accountInquiryService
    ) {}

    /**
     * Daftar kanal payout yang benar-benar didukung & aktif pada Xendit Payouts.
     *
     * @return array{bank: array<string, string>, ewallet: array<string, string>}
     */
    public function supportedChannels(bool $forceRefresh = false): array
    {
        return $this->xenditPayoutService->availablePayoutChannels($forceRefresh);
    }

    public function inquireDestination(User $user, array $input): array
    {
        $destType = strtolower((string) ($input['destination_type'] ?? 'bank'));
        if (! in_array($destType, ['bank', 'ewallet'], true)) {
            throw new InvalidArgumentException('destination_type harus bank atau ewallet.');
        }

        $channelCode = $this->normalizeChannelCode((string) ($input['channel_code'] ?? ''));
        $accountNumber = $this->normalizeInquiryNumber($destType, (string) ($input['account_number'] ?? ''));
        $mode = $this->providerResolver->currentMode();
        $isSandbox = $mode !== 'production';

        if (! $this->xenditPayoutService->isChannelSupported($destType, $channelCode)) {
            return $this->inquiryEnvelope(
                supported: false,
                found: false,
                verificationStatus: 'unavailable',
                holderName: null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: false,
                provider: 'xendit',
                canInputManually: false,
                message: "Kanal {$channelCode} tidak tersedia untuk payout Xendit."
            );
        }

        if ($this->providerResolver->isPayoutProviderDisabled('xendit')) {
            return $this->inquiryEnvelope(
                supported: false,
                found: false,
                verificationStatus: 'unavailable',
                holderName: null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: false,
                provider: 'xendit',
                canInputManually: false,
                message: 'Gateway pencairan Xendit sedang dinonaktifkan oleh Admin.'
            );
        }

        $minLen = $destType === 'ewallet' ? 10 : 8;
        if (strlen($accountNumber) < $minLen) {
            return $this->inquiryEnvelope(
                supported: $this->accountInquiryService->hasOfficialInquiry(),
                found: false,
                verificationStatus: 'unverified',
                holderName: null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: $isSandbox,
                provider: 'xendit',
                canInputManually: true,
                message: $destType === 'ewallet'
                    ? 'Masukkan minimal 10 digit nomor HP e-wallet.'
                    : 'Masukkan minimal 8 digit nomor rekening bank.'
            );
        }

        // Cek apakah pengguna sudah pernah menyimpan tujuan ini dan benar-benar terverifikasi resmi
        $existingAutoVerified = PayoutDestination::where('user_id', $user->id)
            ->where('channel_code', $channelCode)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->where('verification_status', 'auto_verified')
            ->first();

        if ($existingAutoVerified && trim((string) $existingAutoVerified->account_holder_name) !== '') {
            return $this->inquiryEnvelope(
                supported: true,
                found: true,
                verificationStatus: 'auto_verified',
                holderName: strtoupper(trim((string) $existingAutoVerified->account_holder_name)),
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: false,
                provider: 'xendit_name_validator',
                canInputManually: false,
                message: 'Nama pemilik rekening sudah terverifikasi otomatis sebelumnya.'
            );
        }

        // Delegasikan ke AccountInquiryService (Xendit Data Services Name Validator bila diaktifkan)
        $inquiry = $this->accountInquiryService->inquire($destType, $channelCode, $accountNumber);

        return $this->inquiryEnvelope(
            supported: (bool) ($inquiry['supported'] ?? false),
            found: (bool) ($inquiry['found'] ?? false),
            verificationStatus: (string) ($inquiry['verification_status'] ?? 'unverified'),
            holderName: $inquiry['account_holder_name'] ?? null,
            channelCode: $channelCode,
            accountNumber: $accountNumber,
            mode: $mode,
            isSimulated: false,
            provider: (string) ($inquiry['provider'] ?? 'xendit'),
            canInputManually: (bool) ($inquiry['can_input_manually'] ?? true),
            message: $inquiry['message'] ?? null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inquiryEnvelope(
        bool $supported,
        bool $found,
        string $verificationStatus,
        ?string $holderName,
        string $channelCode,
        string $accountNumber,
        string $mode,
        bool $isSimulated,
        string $provider,
        bool $canInputManually,
        ?string $message
    ): array {
        return [
            'supported' => $supported,
            'found' => $found,
            'verification_status' => $verificationStatus,
            'account_holder_name' => $found && $holderName !== null ? trim($holderName) : null,
            'channel_code' => $channelCode,
            'account_number' => $accountNumber,
            'mode' => $mode,
            'is_simulated' => $isSimulated,
            'provider' => $provider,
            'can_input_manually' => $canInputManually,
            'message' => $message,
        ];
    }

    private function normalizeInquiryNumber(string $destType, string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($destType === 'ewallet' && str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    public function listDestinations(User $user, string $ownerType)
    {
        return PayoutDestination::where('user_id', $user->id)
            ->where('owner_type', $ownerType)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();
    }

    public function addDestination(
        User $user,
        string $ownerType,
        array $input,
        ?Courier $courier = null
    ): PayoutDestination {
        $destType = strtolower((string) ($input['destination_type'] ?? 'bank'));
        if (! in_array($destType, ['bank', 'ewallet'], true)) {
            throw new InvalidArgumentException('destination_type harus bank atau ewallet.');
        }

        $channelCode = $this->normalizeChannelCode((string) ($input['channel_code'] ?? ''));
        $channels = $this->supportedChannels();
        if (! array_key_exists($channelCode, $channels[$destType] ?? [])) {
            throw new InvalidArgumentException("Kanal {$channelCode} tidak didukung oleh payout Xendit.");
        }

        $channelName = (string) ($input['channel_name'] ?? ($channels[$destType][$channelCode] ?? $channelCode));
        $accountNumber = $this->normalizeInquiryNumber($destType, (string) ($input['account_number'] ?? ''));
        $holderName = trim((string) ($input['account_holder_name'] ?? ''));

        if (strlen($accountNumber) < 6 || $holderName === '') {
            throw new InvalidArgumentException('Nomor rekening/e-wallet dan nama pemilik wajib valid.');
        }

        // Pastikan provider payout aktif (melempar PROVIDER_DISABLED bila OFF oleh Admin)
        $payoutProvider = $this->providerResolver->resolvePayoutProvider();
        $validation = $payoutProvider->validateDestination($channelCode, $accountNumber, $holderName);
        if (! ($validation['valid'] ?? false)) {
            throw new InvalidArgumentException($validation['message'] ?? 'Rekening tujuan gagal divalidasi.');
        }

        // Tentukan status verifikasi secara jujur (hanya auto_verified bila Name Validator resmi menyetujui)
        $isAutoVerified = false;
        $verificationStatus = 'unverified';
        if ($this->accountInquiryService->hasOfficialInquiry()) {
            $inquiry = $this->accountInquiryService->inquire($destType, $channelCode, $accountNumber);
            if (($inquiry['verification_status'] ?? '') === 'auto_verified'
                && strtoupper(trim((string) ($inquiry['account_holder_name'] ?? ''))) === strtoupper($holderName)) {
                $isAutoVerified = true;
                $verificationStatus = 'auto_verified';
            }
        }

        return DB::transaction(function () use (
            $user,
            $ownerType,
            $courier,
            $destType,
            $channelCode,
            $channelName,
            $accountNumber,
            $holderName,
            $isAutoVerified,
            $verificationStatus,
            $input
        ) {
            $hasAnyDefault = PayoutDestination::where('user_id', $user->id)
                ->where('owner_type', $ownerType)
                ->where('is_active', true)
                ->where('is_default', true)
                ->exists();

            $setAsDefault = array_key_exists('is_default', $input)
                ? (bool) $input['is_default']
                : ! $hasAnyDefault;

            if ($setAsDefault) {
                PayoutDestination::where('user_id', $user->id)
                    ->where('owner_type', $ownerType)
                    ->update(['is_default' => false]);
            }

            $dest = PayoutDestination::create([
                'owner_type' => $ownerType,
                'user_id' => $user->id,
                'courier_id' => $courier?->id,
                'destination_type' => $destType,
                'channel_code' => $channelCode,
                'channel_name' => $channelName,
                'account_number' => $accountNumber,
                'account_number_masked' => $this->maskAccountNumber($accountNumber),
                'account_holder_name' => $holderName,
                'is_verified' => $isAutoVerified,
                'verification_status' => $verificationStatus,
                'verified_at' => $isAutoVerified ? now() : null,
                'is_default' => $setAsDefault,
                'is_active' => true,
                'metadata' => [
                    'mode' => $this->providerResolver->currentMode(),
                    'provider' => 'xendit',
                ],
            ]);

            $this->walletManager->writeAuditLog(
                $user->id,
                $ownerType,
                null,
                'payout_destination_added',
                PayoutDestination::class,
                $dest->id,
                null,
                [
                    'channel_code' => $channelCode,
                    'masked' => $dest->account_number_masked,
                    'verification_status' => $verificationStatus,
                    'is_default' => $setAsDefault,
                ]
            );

            return $dest;
        });
    }

    public function setDefault(PayoutDestination $destination, User $user): PayoutDestination
    {
        return DB::transaction(function () use ($destination, $user) {
            PayoutDestination::where('user_id', $user->id)
                ->where('owner_type', $destination->owner_type)
                ->where('id', '!=', $destination->id)
                ->update(['is_default' => false]);

            $destination->refresh();
            $destination->is_default = true;
            $destination->is_active = true;
            $destination->save();

            return $destination->fresh();
        });
    }

    public function updateDestination(PayoutDestination $destination, User $user, array $input): PayoutDestination
    {
        return DB::transaction(function () use ($destination, $user, $input) {
            if (! empty($input['is_default'])) {
                PayoutDestination::where('user_id', $user->id)
                    ->where('owner_type', $destination->owner_type)
                    ->where('id', '!=', $destination->id)
                    ->update(['is_default' => false]);
                $destination->refresh();
                $destination->is_default = true;
            }

            if (array_key_exists('is_active', $input)) {
                $destination->is_active = (bool) $input['is_active'];
                if (! $destination->is_active) {
                    $destination->is_default = false;
                }
            }

            if (! empty($input['account_holder_name'])) {
                $newHolder = trim((string) $input['account_holder_name']);
                if ($newHolder !== $destination->account_holder_name) {
                    // Perubahan nama manual membuat status kembali Belum Terverifikasi
                    $destination->account_holder_name = $newHolder;
                    $destination->is_verified = false;
                    $destination->verification_status = 'unverified';
                    $destination->verified_at = null;
                }
            }

            $destination->save();

            return $destination->fresh();
        });
    }

    public function maskAccountNumber(string $number): string
    {
        $len = strlen($number);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $prefix = substr($number, 0, min(4, max(2, $len - 4)));
        $suffix = substr($number, -4);

        return $prefix.str_repeat('*', max(3, $len - strlen($prefix) - 4)).$suffix;
    }

    private function normalizeChannelCode(string $code): string
    {
        return $this->xenditPayoutService->normalizeChannelCode($code);
    }
}
