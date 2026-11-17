<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'wallet_type',
        'courier_id',
        'order_id',
        'type',
        'direction',
        'amount',
        'amount_idr',
        'balance_after',
        'fee',
        'net_amount',
        'status',
        'mode',
        'reference',
        'idempotency_key',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_idr' => 'integer',
        'balance_after' => 'integer',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
