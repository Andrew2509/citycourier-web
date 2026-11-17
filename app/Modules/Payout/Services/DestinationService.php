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
