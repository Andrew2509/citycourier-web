<?php

namespace App\Modules\Refund\Models;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Payment\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundTransaction extends Model
{
    protected $table = 'refund_transactions';

    protected $fillable = [
        'reference',
        'idempotency_key',
        'user_id',
        'wallet_id',
        'payment_transaction_id',
        'legacy_payment_id',
        'shipment_id',
        'order_id',
        'refund_method',
        'provider',
        'mode',
        'is_simulated',
        'amount',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'processed_at',
        'provider_reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'is_simulated' => 'boolean',
        'reviewed_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function legacyPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'legacy_payment_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'shipment_id' => $this->shipment_id,
            'order_id' => $this->order_id,
            'payment_transaction_id' => $this->payment_transaction_id,
            'refund_method' => $this->refund_method,
            'provider' => $this->provider,
            'mode' => $this->mode,
            'is_simulated' => (bool) $this->is_simulated,
            'amount' => (int) $this->amount,
            'reason' => $this->reason,
            'status' => $this->status,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
