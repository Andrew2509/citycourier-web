<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_type',
        'courier_id',
        'status',
        'available_balance',
        'pending_balance',
        'reserved_balance',
        'currency',
        'frozen_at',
        'frozen_by',
        'freeze_reason',
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function balanceRecord(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Modules\Wallet\Models\WalletBalance::class, 'wallet_id');
    }

    public function ledgerEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Ledger\Models\WalletLedgerEntry::class, 'wallet_id');
    }

    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Modules\Wallet\Models\WalletAuditLog::class, 'wallet_id');
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen' || $this->status === 'suspended' || $this->frozen_at !== null;
    }

    public function isCustomer(): bool
    {
        return ($this->wallet_type ?? 'courier') === 'customer';
    }

    public function isCourier(): bool
    {
        return ($this->wallet_type ?? 'courier') === 'courier';
    }

    protected $casts = [
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
    ];

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }
}
