<?php

namespace App\Modules\Ledger\Models;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletLedgerEntry extends Model
{
    public const ACCOUNT_WALLET_AVAILABLE = 'WALLET_AVAILABLE';
    public const ACCOUNT_WALLET_PENDING = 'WALLET_PENDING';
    public const ACCOUNT_WALLET_RESERVED = 'WALLET_RESERVED';
    public const ACCOUNT_SYSTEM_GATEWAY_CLEARING = 'SYSTEM_GATEWAY_CLEARING';
    public const ACCOUNT_SYSTEM_ORDER_ESCROW = 'SYSTEM_ORDER_ESCROW';
    public const ACCOUNT_SYSTEM_PLATFORM_REVENUE = 'SYSTEM_PLATFORM_REVENUE';
    public const ACCOUNT_SYSTEM_PAYOUT_CLEARING = 'SYSTEM_PAYOUT_CLEARING';
    public const ACCOUNT_SYSTEM_REFUND_CLEARING = 'SYSTEM_REFUND_CLEARING';
    public const ACCOUNT_SYSTEM_SUSPENSE_ADJUSTMENT = 'SYSTEM_SUSPENSE_ADJUSTMENT';

    public const BUCKET_ACCOUNTS = [
        self::ACCOUNT_WALLET_AVAILABLE,
        self::ACCOUNT_WALLET_PENDING,
        self::ACCOUNT_WALLET_RESERVED,
    ];

    public $timestamps = false;

    protected $table = 'wallet_ledger_entries';

    protected $fillable = [
        'journal_id',
        'journal_reference',
        'dedupe_key',
        'wallet_id',
        'user_id',
        'account_code',
        'entry_type',
        'amount',
        'currency',
        'transaction_type',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'mode',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Wallet ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new LogicException('Wallet ledger entries are immutable and cannot be deleted.');
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
