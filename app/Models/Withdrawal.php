<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'admin_notes',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the courier that requested this withdrawal.
     */
    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
