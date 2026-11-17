<?php

namespace App\Modules\Payout\Services;

use App\Models\Courier;
use App\Models\User;
use App\Modules\Payment\Providers\MockPaymentProvider;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payment\Services\GatewayCredentialsResolver;
use App\Modules\Wallet\Models\PayoutDestination;
use App\Modules\Wallet\Services\WalletLedgerManager;
use App\Services\OvoProvider;
use App\Services\iPaymuService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class DestinationService
{
    public const SUPPORTED_CHANNELS = [
        'bank' => [
            'ID_BCA' => 'Bank BCA',
            'ID_BRI' => 'Bank BRI',
            'ID_BNI' => 'Bank BNI',
            'ID_MANDIRI' => 'Bank Mandiri',
            'ID_PERMATA' => 'Bank Permata',
            'ID_CIMB' => 'Bank CIMB Niaga',
            'ID_BSI' => 'Bank Syariah Indonesia (BSI)',
        ],
        'ewallet' => [
            'ID_DANA' => 'DANA',
            'ID_OVO' => 'OVO',
            'ID_GOPAY' => 'GoPay',
            'ID_SHOPEEPAY' => 'ShopeePay',
            'ID_LINKAJA' => 'LinkAja',
        ],
    ];

    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly WalletLedgerManager $walletManager
    ) {}

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

        $minLen = $destType === 'ewallet' ? 10 : 8;
        if (strlen($accountNumber) < $minLen) {
            return $this->inquiryEnvelope(
                supported: true,
                found: false,
                holderName: null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: $isSandbox,
                provider: $this->providerResolver->safePayoutProviderCode(),
                message: $destType === 'ewallet'
                    ? 'Masukkan minimal 10 digit nomor HP e-wallet.'
                    : 'Masukkan minimal 8 digit nomor rekening bank.'
            );
        }

        // 1. Cek daftar rekening/e-wallet tersimpan milik pengguna sendiri.
        $existing = PayoutDestination::where('user_id', $user->id)
            ->where('channel_code', $channelCode)
            ->where('account_number', $accountNumber)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->first();
        if ($existing && trim((string) $existing->account_holder_name) !== '') {
            return $this->inquiryEnvelope(
                supported: true,
                found: true,
                holderName: strtoupper(trim((string) $existing->account_holder_name)),
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: false,
                provider: 'saved_destination',
                message: 'Nama pemilik ditemukan dari rekening tersimpan.'
            );
        }

        // 2. Cek integrasi e-wallet khusus bila aktif (OVO SNAP / iPaymu DANA).
        if ($destType === 'ewallet') {
            $specialized = $this->trySpecializedEwalletInquiry($channelCode, $accountNumber, $mode);
            if ($specialized !== null) {
                return $specialized;
            }
        }

        // 3. Di mode Sandbox/Demo, cocokkan nomor HP akun pengguna yang login agar terasa nyata.
        if ($isSandbox && $destType === 'ewallet') {
            $userPhone = $this->normalizeInquiryNumber('ewallet', (string) ($user->phone ?? ''));
            if ($userPhone !== '' && $userPhone === $accountNumber && trim((string) $user->name) !== '') {
                return $this->inquiryEnvelope(
                    supported: true,
                    found: true,
                    holderName: strtoupper(trim((string) $user->name)),
                    channelCode: $channelCode,
                    accountNumber: $accountNumber,
                    mode: $mode,
                    isSimulated: true,
                    provider: 'mock',
                    message: 'Nama pemilik ditemukan sesuai profil akun (Simulasi Sandbox).'
                );
            }
        }

        // 4. Delegasikan ke provider payout aktif.
        try {
            $provider = $this->providerResolver->resolvePayoutProvider();
            $result = $provider->inquireDestination($destType, $channelCode, $accountNumber);

            if (! ($result['supported'] ?? false) && $isSandbox) {
                $result = app(MockPaymentProvider::class)->inquireDestination($destType, $channelCode, $accountNumber);
            }

            return $this->inquiryEnvelope(
                supported: (bool) ($result['supported'] ?? false),
                found: (bool) ($result['found'] ?? false),
                holderName: $result['account_holder_name'] ?? null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: ($result['provider'] ?? '') === 'mock' || $isSandbox,
                provider: (string) ($result['provider'] ?? $provider->providerCode()),
                message: $result['message'] ?? null
            );
        } catch (RuntimeException $e) {
            $cleanMessage = str_starts_with($e->getMessage(), 'PROVIDER_DISABLED:')
                ? substr($e->getMessage(), strlen('PROVIDER_DISABLED:'))
                : $e->getMessage();

            return $this->inquiryEnvelope(
                supported: false,
                found: false,
                holderName: null,
                channelCode: $channelCode,
                accountNumber: $accountNumber,
                mode: $mode,
                isSimulated: $isSandbox,
                provider: $this->providerResolver->safePayoutProviderCode(),
                message: $cleanMessage
            );
        }
    }

    private function trySpecializedEwalletInquiry(string $channelCode, string $accountNumber, string $mode): ?array
    {
        $creds = app(GatewayCredentialsResolver::class);

        if ($channelCode === 'ID_OVO' && $creds->isProviderEnabled('ovo') && $mode === 'production') {
            try {
                $res = app(OvoProvider::class)->accountInquiry($accountNumber);
                if ($res['success'] ?? false) {
                    return $this->inquiryEnvelope(
                        supported: true,
                        found: true,
                        holderName: strtoupper(trim((string) ($res['customer_name'] ?? ''))),
                        channelCode: $channelCode,
                        accountNumber: $accountNumber,
                        mode: $mode,
                        isSimulated: false,
                        provider: 'ovo',
                        message: 'Akun OVO terverifikasi.'
                    );
                }

                if (($res['error_code'] ?? '') === 'OVO_ACCOUNT_NOT_FOUND') {
                    return $this->inquiryEnvelope(
                        supported: true,
                        found: false,
                        holderName: null,
                        channelCode: $channelCode,
                        accountNumber: $accountNumber,
                        mode: $mode,
                        isSimulated: false,
                        provider: 'ovo',
                        message: $res['error'] ?? 'Nomor OVO tidak terdaftar.'
                    );
                }
            } catch (\Throwable) {
                // Fall through cleanly ke resolver standar bila driver OVO tidak tersedia.
            }
        }

        if ($channelCode === 'ID_DANA' && $creds->isProviderEnabled('ipaymu') && $mode === 'production') {
            try {
                $res = (new iPaymuService())->inquireAccountName($accountNumber);
                if ($res['available'] ?? false) {
                    return $this->inquiryEnvelope(
                        supported: true,
                        found: true,
                        holderName: strtoupper(trim((string) ($res['account_name'] ?? ''))),
                        channelCode: $channelCode,
                        accountNumber: $accountNumber,
                        mode: $mode,
                        isSimulated: false,
                        provider: 'ipaymu',
                        message: 'Akun DANA terverifikasi via iPaymu.'
                    );
                }
            } catch (\Throwable) {
                // Fall through cleanly ke resolver standar.
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function inquiryEnvelope(
        bool $supported,
        bool $found,
        ?string $holderName,
        string $channelCode,
        string $accountNumber,
        string $mode,
        bool $isSimulated,
        string $provider,
        ?string $message
    ): array {
        return [
            'supported' => $supported,
            'found' => $found,
            'account_holder_name' => $found && $holderName !== null ? trim($holderName) : null,
            'channel_code' => $channelCode,
            'account_number' => $accountNumber,
            'mode' => $mode,
            'is_simulated' => $isSimulated,
            'provider' => $provider,
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
        $channelName = (string) ($input['channel_name'] ?? (self::SUPPORTED_CHANNELS[$destType][$channelCode] ?? $channelCode));
        $accountNumber = preg_replace('/\s+|-/', '', (string) ($input['account_number'] ?? '')) ?? '';
        $holderName = trim((string) ($input['account_holder_name'] ?? ''));

        if (strlen($accountNumber) < 6 || $holderName === '') {
            throw new InvalidArgumentException('Nomor rekening/e-wallet dan nama pemilik wajib valid.');
        }

        $payoutProvider = $this->providerResolver->resolvePayoutProvider();
        $validation = $payoutProvider->validateDestination($channelCode, $accountNumber, $holderName);
        if (! ($validation['valid'] ?? false)) {
            throw new InvalidArgumentException($validation['message'] ?? 'Rekening tujuan gagal divalidasi.');
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
                'is_verified' => true,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'is_default' => $setAsDefault,
                'is_active' => true,
                'metadata' => [
                    'mode' => $this->providerResolver->currentMode(),
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
                ['channel_code' => $channelCode, 'masked' => $dest->account_number_masked, 'is_default' => $setAsDefault]
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
                $destination->account_holder_name = trim((string) $input['account_holder_name']);
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
        $upper = strtoupper(trim($code));

        return str_starts_with($upper, 'ID_') ? $upper : 'ID_'.$upper;
    }
}
