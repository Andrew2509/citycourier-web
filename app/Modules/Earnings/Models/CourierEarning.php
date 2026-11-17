<?php

namespace App\Modules\Earnings\Models;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Wallet;
use App\Modules\Payout\Models\PayoutTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierEarning extends Model
{
    protected $table = 'courier_earnings';

    protected $fillable = [
        'reference',
        'courier_id',
        'wallet_id',
        'order_id',
        'shipment_id',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
        'payout_transaction_id',
        'mode',
        'earned_at',
        'available_at',
        'released_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'commission_rate' => 'float',
        'commission_amount' => 'integer',
        'net_amount' => 'integer',
        'earned_at' => 'datetime',
        'available_at' => 'datetime',
        'released_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function payoutTransaction(): BelongsTo
    {
        return $this->belongsTo(PayoutTransaction::class, 'payout_transaction_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'order_id' => $this->order_id,
            'shipment_id' => $this->shipment_id,
            'gross_amount' => (int) $this->gross_amount,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (int) $this->commission_amount,
            'net_amount' => (int) $this->net_amount,
            'status' => $this->status,
            'mode' => $this->mode,
            'earned_at' => $this->earned_at?->toIso8601String(),
            'available_at' => $this->available_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payout_transaction_id' => $this->payout_transaction_id,
        ];
    }
}
