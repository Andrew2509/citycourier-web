<?php

namespace App\Modules\Earnings\Services;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CourierEarningService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function commissionRatePercent(): float
    {
        $configured = (float) config('payment_wallet.courier_earnings.default_commission_rate_percent', 10.0);
        if (Schema::hasTable('settings')) {
            $val = Setting::get('courier_commission_rate_percent');
            if ($val !== null && $val !== '') {
                return max(0.0, min(90.0, (float) $val));
            }
        }

        return $configured;
    }

    public function settlementWaitingMinutes(): int
    {
        $configured = (int) config('payment_wallet.courier_earnings.settlement_waiting_minutes', 1440);
        if (Schema::hasTable('settings')) {
            $val = Setting::get('courier_settlement_waiting_minutes');
            if ($val !== null && $val !== '') {
                return max(0, (int) $val);
            }
        }

        return $configured;
    }

    /**
     * Automatically calculate and record courier earning upon verified delivery.
     */
    public function recordDeliveredOrderEarning(int $orderId): ?CourierEarning
    {
        return DB::transaction(function () use ($orderId) {
            $existing = CourierEarning::where('order_id', $orderId)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $order = Order::with(['courier', 'shipment'])->where('id', $orderId)->lockForUpdate()->first();
            if (! $order || $order->status !== 'delivered' || ! $order->courier_id || ! $order->courier) {
                return null;
            }

            $grossAmount = (int) round((float) $order->price);
            if ($grossAmount <= 0 && $order->shipment) {
                $grossAmount = (int) round((float) ($order->shipment->shipping_cost ?: $order->shipment->total_cost));
            }
            if ($grossAmount <= 0) {
                return null;
            }

            $commissionRate = $this->commissionRatePercent();
            $commissionAmount = (int) round($grossAmount * ($commissionRate / 100.0));
            $netAmount = max(0, $grossAmount - $commissionAmount);
            if ($netAmount <= 0) {
                return null;
            }

            $wallet = $this->walletManager->getOrCreateCourierWallet($order->courier);
            $mode = $this->providerResolver->currentMode();
            $earnedAt = $order->delivered_at
                ? Carbon::parse($order->delivered_at)
                : ($order->updated_at ? Carbon::parse($order->updated_at) : now());
            $waitingMinutes = $this->settlementWaitingMinutes();
            $availableAt = $earnedAt->copy()->addMinutes($waitingMinutes);
            $reference = 'ERN-ORD-'.$order->id.'-'.strtoupper(substr(md5((string) $order->id), 0, 4));

            $earning = CourierEarning::create([
                'reference' => $reference,
                'courier_id' => $order->courier_id,
                'wallet_id' => $wallet->id,
                'order_id' => $order->id,
                'shipment_id' => $order->shipment?->id ?? (Schema::hasColumn('orders', 'shipment_id') ? $order->shipment_id : null),
                'gross_amount' => $grossAmount,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_amount' => $netAmount,
                'status' => 'pending',
                'mode' => $mode,
                'earned_at' => $earnedAt,
                'available_at' => $availableAt,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'waiting_minutes' => $waitingMinutes,
                ],
            ]);

            $this->walletManager->creditPendingEarning(
                $wallet,
                $grossAmount,
                $commissionAmount,
                $netAmount,
                $reference,
                'earning-pending-order-'.$order->id,
                "Pendapatan order #".($order->order_number ?: $order->id)." (masa tunggu settlement)",
                CourierEarning::class,
                $earning->id,
                $mode,
                [
                    'order_id' => $order->id,
                    'gross_amount' => $grossAmount,
                    'commission_amount' => $commissionAmount,
                    'commission_rate' => $commissionRate,
                ]
            );

            $this->walletManager->writeAuditLog(
                $order->courier->user_id,
                'system',
                $wallet->id,
                'courier_earning_recorded',
                CourierEarning::class,
                $earning->id,
                null,
                [
                    'order_id' => $order->id,
                    'gross' => $grossAmount,
                    'commission' => $commissionAmount,
                    'net' => $netAmount,
                    'available_at' => $availableAt->toIso8601String(),
                ]
            );

            if ($waitingMinutes === 0) {
                return $this->releaseSingleEarning($earning);
            }

            return $earning->fresh();
        });
    }

    public function releaseSingleEarning(CourierEarning $earning): CourierEarning
    {
        return DB::transaction(function () use ($earning) {
            $locked = CourierEarning::where('id', $earning->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                return $locked;
            }

            $wallet = $locked->wallet()->firstOrFail();

            $this->walletManager->releasePendingToAvailable(
                $wallet,
                (int) $locked->net_amount,
                $locked->reference.'-AVAIL',
                'earning-release-'.$locked->id,
                "Pendapatan order #{$locked->order_id} siap dicairkan",
                CourierEarning::class,
                $locked->id,
                $locked->mode,
                ['courier_earning_id' => $locked->id, 'order_id' => $locked->order_id]
            );

            $locked->status = 'available';
            $locked->released_at = now();
            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Release all pending earnings whose settlement waiting period has elapsed.
     */
    public function releaseMatureEarnings(): int
    {
        return $this->releaseMatureEarningsQuery(null);
    }

    public function releaseMatureEarningsForCourier(int $courierId): int
    {
        return $this->releaseMatureEarningsQuery($courierId);
    }

    /**
     * Synchronize delivered courier orders missing from courier_earnings / ledger
     * (e.g. orders completed before queue worker or v1 ledger ran) and release mature ones.
     */
    public function syncDeliveredOrdersForCourier(Courier $courier): int
    {
        if (
            ! Schema::hasTable('orders')
            || ! Schema::hasTable('courier_earnings')
            || ! Schema::hasTable('wallet_balances')
            || ! Schema::hasTable('wallet_ledger_entries')
        ) {
            return 0;
        }

        $runSync = function () use ($courier): int {
            $wallet = $this->walletManager->getOrCreateCourierWallet($courier);
            $openingEntry = Schema::hasColumn('wallet_ledger_entries', 'journal_reference')
                ? WalletLedgerEntry::where('journal_reference', 'OPENING-WALLET-'.$wallet->id)->first()
                : null;
            $openingCutoff = $openingEntry?->created_at;

            $existingOrderIds = CourierEarning::where('courier_id', $courier->id)
                ->pluck('order_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $legacyTxOrderIds = [];
            if (Schema::hasTable('wallet_transactions') && Schema::hasColumn('wallet_transactions', 'order_id')) {
                $legacyTxOrderIds = WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('type', 'earning')
                    ->whereNotNull('order_id')
                    ->pluck('order_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $skipOrderIds = array_flip(array_merge($existingOrderIds, $legacyTxOrderIds));
            $deliveredOrders = Order::where('courier_id', $courier->id)
                ->where('status', 'delivered')
                ->orderBy('id')
                ->get();

            $syncedCount = 0;
            foreach ($deliveredOrders as $order) {
                if (isset($skipOrderIds[(int) $order->id])) {
                    continue;
                }

                $orderTime = $order->delivered_at ?? $order->updated_at ?? $order->created_at;
                if ($openingCutoff !== null && $orderTime !== null && Carbon::parse($orderTime)->lte($openingCutoff)) {
                    continue;
                }

                try {
                    if ($this->recordDeliveredOrderEarning((int) $order->id) !== null) {
                        $syncedCount++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[CourierEarningService] Skip syncing delivered order', [
                        'courier_id' => $courier->id,
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->releaseMatureEarningsForCourier($courier->id);

            return $syncedCount;
        };

        try {
            return (int) Cache::lock('courier-earning-sync-'.$courier->id, 10)->block(3, $runSync);
        } catch (\Throwable) {
            return $runSync();
        }
    }

    private function releaseMatureEarningsQuery(?int $courierId): int
    {
        $query = CourierEarning::where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            });

        if ($courierId !== null) {
            $query->where('courier_id', $courierId);
        }

        $matureIds = $query->pluck('id');

        $releasedCount = 0;
        foreach ($matureIds as $id) {
            try {
                $earning = CourierEarning::find($id);
                if ($earning && $earning->status === 'pending') {
                    $this->releaseSingleEarning($earning);
                    $releasedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('[CourierEarningService] Failed releasing pending earning', [
                    'earning_id' => $id,
                    'courier_id' => $courierId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $releasedCount;
    }

    public function getCourierEarningsSummary(Courier $courier): array
    {
        $pending = (int) CourierEarning::where('courier_id', $courier->id)
            ->where('status', 'pending')
            ->sum('net_amount');

        $available = (int) CourierEarning::where('courier_id', $courier->id)
            ->where('status', 'available')
            ->sum('net_amount');

        $reserved = (int) CourierEarning::where('courier_id', $courier->id)
            ->where('status', 'reserved')
            ->sum('net_amount');

        $paid = (int) CourierEarning::where('courier_id', $courier->id)
            ->where('status', 'paid')
            ->sum('net_amount');

        $totalGross = (int) CourierEarning::where('courier_id', $courier->id)->sum('gross_amount');
        $totalCommission = (int) CourierEarning::where('courier_id', $courier->id)->sum('commission_amount');

        return [
            'pending_earnings' => $pending,
            'available_earnings' => $available,
            'reserved_earnings' => $reserved,
            'paid_earnings' => $paid,
            'total_gross_earnings' => $totalGross,
            'total_commission_deducted' => $totalCommission,
            'commission_rate_percent' => $this->commissionRatePercent(),
            'settlement_waiting_minutes' => $this->settlementWaitingMinutes(),
        ];
    }
}
