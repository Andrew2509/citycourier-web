<?php

namespace App\Modules\Payment\Models;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'reference',
        'idempotency_key',
        'user_id',
        'wallet_id',
        'shipment_id',
        'order_id',
        'legacy_payment_id',
        'payment_method',
        'provider',
        'mode',
        'is_simulated',
        'channel_code',
        'amount',
        'fee',
        'total_amount',
        'status',
        'provider_reference',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'total_amount' => 'integer',
        'is_simulated' => 'boolean',
        'paid_at' => 'datetime',
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

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function legacyPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'legacy_payment_id');
    }
}
