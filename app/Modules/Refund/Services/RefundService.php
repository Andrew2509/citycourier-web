<?php

namespace App\Modules\Refund\Services;

use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RefundService
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function requestRefund(User $user, array $input, string $idempotencyKey): array
    {
        $existing = RefundTransaction::where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return ['refund' => $existing, 'idempotent_replay' => true];
        }

        $paymentTx = null;
        $legacyPayment = null;
        $shipment = null;

        if (! empty($input['payment_transaction_id'])) {
            $paymentTx = PaymentTransaction::where('id', (int) $input['payment_transaction_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
            if ($paymentTx->shipment_id) {
                $shipment = Shipment::find($paymentTx->shipment_id);
            }
        } elseif (! empty($input['shipment_id'])) {
            $shipment = Shipment::where('id', (int) $input['shipment_id'])
                ->where('user_id', $user->id)
                ->firstOrFail();
            $paymentTx = PaymentTransaction::where('shipment_id', $shipment->id)
                ->where('status', 'paid')
                ->latest('id')
                ->first();
            $legacyPayment = Payment::where('shipment_id', $shipment->id)
                ->where('status', 'paid')
                ->latest('id')
                ->first();
        } else {
            throw new InvalidArgumentException('payment_transaction_id atau shipment_id wajib diisi.');
        }

        if ($shipment && $shipment->status === 'delivered') {
            throw new RuntimeException('Order yang sudah selesai dikirim (delivered) tidak memenuhi kebijakan refund otomatis.');
        }

        $paidAmount = $paymentTx
            ? (int) $paymentTx->total_amount
            : (int) round((float) ($legacyPayment?->amount ?? 0));

        if ($paidAmount <= 0 || ($paymentTx && $paymentTx->status !== 'paid') || (! $paymentTx && (! $legacyPayment || $legacyPayment->status !== 'paid'))) {
            throw new RuntimeException('Hanya transaksi pembayaran berstatus paid/lunas yang dapat diajukan refund.');
        }

        $refundAmount = ! empty($input['amount']) ? (int) $input['amount'] : $paidAmount;
        if ($refundAmount <= 0 || $refundAmount > $paidAmount) {
            throw new InvalidArgumentException('Nominal refund harus lebih dari 0 dan tidak melebihi nominal pembayaran.');
        }

        $alreadyRefunded = (int) RefundTransaction::where('user_id', $user->id)
            ->when($paymentTx, fn ($q) => $q->where('payment_transaction_id', $paymentTx->id))
            ->when(! $paymentTx && $shipment, fn ($q) => $q->where('shipment_id', $shipment->id))
            ->whereIn('status', ['requested', 'approved', 'processing', 'completed'])
            ->sum('amount');

        if (($alreadyRefunded + $refundAmount) > $paidAmount) {
            throw new RuntimeException('Pengajuan refund melebihi sisa nilai transaksi yang dapat di-refund.');
        }

        $wallet = $this->walletManager->getOrCreateCustomerWallet($user);
        $mode = $this->providerResolver->currentMode();
        $reference = 'REF-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));

        $refund = RefundTransaction::create([
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'payment_transaction_id' => $paymentTx?->id,
            'legacy_payment_id' => $legacyPayment?->id ?? $paymentTx?->legacy_payment_id,
            'shipment_id' => $shipment?->id ?? $paymentTx?->shipment_id,
            'order_id' => $paymentTx?->order_id,
            'refund_method' => 'wallet_credit',
            'provider' => 'internal',
            'mode' => $mode,
            'is_simulated' => $mode === 'demo',
            'amount' => $refundAmount,
            'reason' => (string) $input['reason'],
            'status' => 'requested',
        ]);

        $autoApproveMax = (int) config('payment_wallet.refund.auto_approve_max_idr', 0);
        if ($autoApproveMax > 0 && $refundAmount <= $autoApproveMax) {
            $refund = $this->approveAndProcess($refund, null, 'Auto-approved by policy');
        } else {
            $this->walletManager->writeAuditLog(
                $user->id,
                'customer',
                $wallet->id,
                'refund_requested',
                RefundTransaction::class,
                $refund->id,
                null,
                ['reference' => $refund->reference, 'amount' => $refundAmount]
            );
        }

        return ['refund' => $refund, 'idempotent_replay' => false];
    }

    public function approveAndProcess(
        RefundTransaction $refund,
        ?User $admin = null,
        ?string $notes = null
    ): RefundTransaction {
        return DB::transaction(function () use ($refund, $admin, $notes) {
            $locked = RefundTransaction::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'completed') {
                return $locked;
            }
            if (! in_array($locked->status, ['requested', 'approved', 'processing'], true)) {
                throw new RuntimeException("Refund status '{$locked->status}' tidak dapat diproses.");
            }

            $user = $locked->user()->firstOrFail();
            $wallet = $locked->wallet_id
                ? $locked->wallet()->firstOrFail()
                : $this->walletManager->getOrCreateCustomerWallet($user);

            $now = now();
            $locked->wallet_id = $wallet->id;
            $locked->reviewed_by = $admin?->id;
            $locked->reviewed_at = $locked->reviewed_at ?? $now;
            $locked->review_notes = $notes ?: 'Approved & credited to City Wallet';
            $locked->status = 'completed';
            $locked->processed_at = $now;
            $locked->save();

            $this->walletManager->creditAvailable(
                $wallet,
                (int) $locked->amount,
                WalletLedgerEntry::ACCOUNT_SYSTEM_REFUND_CLEARING,
                'refund',
                $locked->reference,
                'refund-credit-'.$locked->id,
                "Refund dana pesanan ({$locked->reference}): {$locked->reason}",
                RefundTransaction::class,
                $locked->id,
                $locked->mode,
                [
                    'refund_id' => $locked->id,
                    'shipment_id' => $locked->shipment_id,
                    'payment_transaction_id' => $locked->payment_transaction_id,
                ]
            );

            if ($locked->shipment_id) {
                $shipment = Shipment::find($locked->shipment_id);
                if ($shipment && in_array($shipment->status, ['pending', 'confirmed'], true)) {
                    $shipment->update(['status' => 'cancelled']);
                }
            }

            if ($locked->payment_transaction_id) {
                PaymentTransaction::where('id', $locked->payment_transaction_id)->update(['status' => 'refunded']);
            }

            $this->walletManager->writeAuditLog(
                $admin?->id,
                $admin ? 'admin' : 'system',
                $wallet->id,
                'refund_completed',
                RefundTransaction::class,
                $locked->id,
                ['status' => 'requested'],
                ['status' => 'completed', 'amount' => (int) $locked->amount],
                $notes
            );

            return $locked->fresh();
        });
    }

    public function rejectRefund(RefundTransaction $refund, User $admin, string $reason): RefundTransaction
    {
        return DB::transaction(function () use ($refund, $admin, $reason) {
            $locked = RefundTransaction::where('id', $refund->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'completed') {
                throw new RuntimeException('Refund yang sudah selesai tidak dapat ditolak.');
            }

            $locked->status = 'rejected';
            $locked->reviewed_by = $admin->id;
            $locked->reviewed_at = now();
            $locked->review_notes = $reason;
            $locked->save();

            $this->walletManager->writeAuditLog(
                $admin->id,
                'admin',
                $locked->wallet_id,
                'refund_rejected',
                RefundTransaction::class,
                $locked->id,
                null,
                ['status' => 'rejected', 'reason' => $reason],
                $reason
            );

            return $locked->fresh();
        });
    }
}
