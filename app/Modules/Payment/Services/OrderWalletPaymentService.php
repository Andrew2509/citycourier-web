<?php

namespace App\Modules\Payment\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class OrderWalletPaymentService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver
    ) {}

    /**
     * Pay a delivery shipment/order atomically using Customer Wallet balance.
     *
     * @return array<string, mixed>
     */
    public function payWithWallet(
        User $user,
        ?int $shipmentId,
        ?int $orderId,
        string $idempotencyKey
    ): array {
        if (! $shipmentId && ! $orderId) {
            throw new InvalidArgumentException('shipment_id atau order_id wajib diisi.');
        }

        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            if (($shipmentId && $existing->shipment_id && (int) $existing->shipment_id !== $shipmentId)
                || ($orderId && $existing->order_id && (int) $existing->order_id !== $orderId)) {
                throw new InvalidArgumentException('Idempotency-Key sudah digunakan untuk order/shipment lain.');
            }

            return [
                'payment_transaction' => $existing,
                'idempotent_replay' => true,
            ];
        }

        return DB::transaction(function () use ($user, $shipmentId, $orderId, $idempotencyKey) {
            $shipment = null;
            $order = null;

            if ($shipmentId) {
                $shipment = Shipment::where('id', $shipmentId)->lockForUpdate()->firstOrFail();
                if ((int) $shipment->user_id !== (int) $user->id) {
                    throw new RuntimeException('Anda tidak memiliki akses ke shipment ini.');
                }
                $order = $this->findOrderForShipment($shipment);
            } elseif ($orderId) {
                $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
                $shipment = $this->findShipmentForOrder($order);
                if ($shipment && (int) $shipment->user_id !== (int) $user->id) {
                    throw new RuntimeException('Anda tidak memiliki akses ke order ini.');
                }
            }

            $alreadyPaid = false;
            if ($shipment) {
                $alreadyPaid = PaymentTransaction::where('shipment_id', $shipment->id)->where('status', 'paid')->exists()
                    || Payment::where('shipment_id', $shipment->id)->where('status', 'paid')->exists();
            } elseif ($order) {
                $alreadyPaid = PaymentTransaction::where('order_id', $order->id)->where('status', 'paid')->exists()
                    || Payment::where('order_id', $order->id)->where('status', 'paid')->exists();
            }

            if ($alreadyPaid) {
                throw new RuntimeException('Pesanan/shipment ini sudah lunas dibayar.');
            }
            if ($shipment && $shipment->status === 'cancelled') {
                throw new RuntimeException('Pesanan yang dibatalkan tidak dapat dibayar.');
            }

            $amount = $shipment
                ? (int) round((float) ($shipment->total_cost ?: $shipment->shipping_cost))
                : (int) round((float) ($order?->price ?? 0));

            if ($amount <= 0) {
                throw new InvalidArgumentException('Nominal pembayaran pesanan tidak valid.');
            }

            $wallet = $this->walletManager->getOrCreateCustomerWallet($user);
            $this->walletManager->assertNotFrozen($wallet);

            $available = (int) round((float) ($wallet->balanceRecord?->available_balance ?? $wallet->available_balance));
            if ($available < $amount) {
                throw new RuntimeException(
                    "INSUFFICIENT_BALANCE:Saldo wallet tidak mencukupi (tersedia Rp {$available}, dibutuhkan Rp {$amount})."
                );
            }

            $mode = $this->providerResolver->currentMode();
            $reference = 'WALPAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
            $now = now();

            $legacyPayment = Payment::create([
                'user_id' => $user->id,
                'order_id' => $order?->id,
                'shipment_id' => $shipment?->id,
                'payment_id' => $reference,
                'payment_type' => 'wallet',
                'channel_code' => 'CITY_WALLET',
                'amount' => $amount,
                'status' => 'paid',
                'paid_at' => $now,
                'metadata' => [
                    'mode' => $mode,
                    'wallet_id' => $wallet->id,
                    'order_id' => $order?->id,
                ],
            ]);

            $paymentTx = PaymentTransaction::create([
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'shipment_id' => $shipment?->id,
                'order_id' => $order?->id,
                'legacy_payment_id' => $legacyPayment->id,
                'payment_method' => 'wallet',
                'provider' => 'internal',
                'mode' => $mode,
                'is_simulated' => $mode === 'demo',
                'channel_code' => 'CITY_WALLET',
                'amount' => $amount,
                'fee' => 0,
                'total_amount' => $amount,
                'status' => 'paid',
                'provider_reference' => $reference,
                'paid_at' => $now,
                'metadata' => [
                    'shipment_number' => $shipment?->shipment_number,
                    'order_number' => $order?->order_number,
                ],
            ]);

            $this->walletManager->debitAvailable(
                $wallet,
                $amount,
                WalletLedgerEntry::ACCOUNT_SYSTEM_ORDER_ESCROW,
                'order_payment',
                $reference,
                'order-wallet-pay-'.$paymentTx->id,
                'Pembayaran order '.($shipment?->shipment_number ?? $order?->order_number ?? '#'.$paymentTx->id),
                PaymentTransaction::class,
                $paymentTx->id,
                $mode,
                [
                    'payment_transaction_id' => $paymentTx->id,
                    'shipment_id' => $shipment?->id,
                    'order_id' => $order?->id,
                ]
            );

            if ($shipment) {
                $shipmentUpdate = [
                    'payment_method' => 'wallet',
                ];
                if ($shipment->status === 'pending') {
                    $shipmentUpdate['status'] = 'confirmed';
                }
                $shipment->update($shipmentUpdate);
            }

            $this->walletManager->writeAuditLog(
                $user->id,
                'customer',
                $wallet->id,
                'order_paid_with_wallet',
                PaymentTransaction::class,
                $paymentTx->id,
                null,
                ['reference' => $reference, 'amount' => $amount, 'shipment_id' => $shipment?->id]
            );

            return [
                'payment_transaction' => $paymentTx->fresh(),
                'wallet' => $wallet->fresh(['balanceRecord']),
                'shipment' => $shipment?->fresh(),
                'idempotent_replay' => false,
            ];
        });
    }

    private function findOrderForShipment(Shipment $shipment): ?Order
    {
        $hasShipmentIdCol = Schema::hasColumn('orders', 'shipment_id');
        if (! $hasShipmentIdCol && empty($shipment->shipment_number)) {
            return null;
        }

        return Order::query()
            ->where(function ($q) use ($shipment, $hasShipmentIdCol) {
                if ($hasShipmentIdCol) {
                    $q->where('shipment_id', $shipment->id);
                }
                if (! empty($shipment->shipment_number)) {
                    $hasShipmentIdCol
                        ? $q->orWhere('order_number', $shipment->shipment_number)
                        : $q->where('order_number', $shipment->shipment_number);
                }
            })
            ->lockForUpdate()
            ->first();
    }

    private function findShipmentForOrder(Order $order): ?Shipment
    {
        if (Schema::hasColumn('orders', 'shipment_id') && ! empty($order->shipment_id)) {
            return Shipment::where('id', $order->shipment_id)->lockForUpdate()->first();
        }

        if (! empty($order->order_number)) {
            return Shipment::where('shipment_number', $order->order_number)->lockForUpdate()->first();
        }

        return null;
    }
}
