<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'wallet_id',
        'dana_connection_id',
        'amount',
        'fee',
        'net_amount',
        'status',
        'idempotency_key',
        'reference',
        'provider_reference',
        'failure_code',
        'failure_reason',
        'processed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'fee'          => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function danaConnection()
    {
        return $this->belongsTo(DanaConnection::class);
    }

    /**
     * Check if withdrawal is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['success', 'failed', 'cancelled']);
    }

    /**
     * Check if withdrawal is still processing.
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'reserved', 'processing']);
    }
}
