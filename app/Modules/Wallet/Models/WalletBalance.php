<?php

namespace App\Modules\Wallet\Models;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalance extends Model
{
    protected $table = 'wallet_balances';

    protected $fillable = [
        'wallet_id',
        'currency',
        'available_balance',
        'pending_balance',
        'reserved_balance',
        'total_credited',
        'total_debited',
        'last_ledger_entry_id',
        'version',
    ];

    protected $casts = [
        'available_balance' => 'integer',
        'pending_balance' => 'integer',
        'reserved_balance' => 'integer',
        'total_credited' => 'integer',
        'total_debited' => 'integer',
        'last_ledger_entry_id' => 'integer',
        'version' => 'integer',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
