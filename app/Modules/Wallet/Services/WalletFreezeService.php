<?php

namespace App\Modules\Wallet\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletFreezeService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager
    ) {}

    public function freeze(
        Wallet $wallet,
        User $admin,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Wallet {
        return DB::transaction(function () use ($wallet, $admin, $reason, $ipAddress, $userAgent) {
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $oldValues = [
                'status' => $locked->status,
                'frozen_at' => $locked->frozen_at ? \Illuminate\Support\Carbon::parse($locked->frozen_at)->toIso8601String() : null,
                'freeze_reason' => $locked->freeze_reason,
            ];

            $locked->status = 'frozen';
            $frozenAt = now();
            $locked->frozen_at = $frozenAt;
            $locked->frozen_by = $admin->id;
            $locked->freeze_reason = $reason;
            $locked->save();

            $this->walletManager->writeAuditLog(
                $admin->id,
                'admin',
                $locked->id,
                'wallet_frozen',
                Wallet::class,
                $locked->id,
                $oldValues,
                [
                    'status' => 'frozen',
                    'frozen_at' => $frozenAt->toIso8601String(),
                    'freeze_reason' => $reason,
                ],
                $reason,
                $ipAddress,
                $userAgent
            );

            return $locked->fresh(['balanceRecord', 'user', 'courier']);
        });
    }

    public function unfreeze(
        Wallet $wallet,
        User $admin,
        ?string $notes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Wallet {
        return DB::transaction(function () use ($wallet, $admin, $notes, $ipAddress, $userAgent) {
            $locked = Wallet::where('id', $wallet->id)->lockForUpdate()->firstOrFail();

            $oldValues = [
                'status' => $locked->status,
                'frozen_at' => $locked->frozen_at ? \Illuminate\Support\Carbon::parse($locked->frozen_at)->toIso8601String() : null,
                'freeze_reason' => $locked->freeze_reason,
            ];

            $locked->status = 'active';
            $locked->frozen_at = null;
            $locked->frozen_by = null;
            $locked->freeze_reason = null;
            $locked->save();

            $this->walletManager->writeAuditLog(
                $admin->id,
                'admin',
                $locked->id,
                'wallet_unfrozen',
                Wallet::class,
                $locked->id,
                $oldValues,
                ['status' => 'active'],
                $notes ?: 'Wallet unfrozen by admin',
                $ipAddress,
                $userAgent
            );

            return $locked->fresh(['balanceRecord', 'user', 'courier']);
        });
    }
}
