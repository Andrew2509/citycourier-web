<?php

namespace App\Modules\TopUp\Models;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupTransaction extends Model
{
    protected $table = 'topup_transactions';

    protected $fillable = [
        'reference',
        'idempotency_key',
        'user_id',
        'wallet_id',
        'provider',
        'mode',
        'is_simulated',
        'payment_type',
        'channel_code',
        'amount',
        'fee',
        'net_amount',
        'status',
        'provider_reference',
        'va_number',
        'qr_string',
        'payment_url',
        'instructions',
        'provider_payload',
        'expired_at',
        'paid_at',
        'settled_at',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'net_amount' => 'integer',
        'is_simulated' => 'boolean',
        'instructions' => 'array',
        'provider_payload' => 'array',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'mode' => $this->mode,
            'is_simulated' => (bool) $this->is_simulated,
            'payment_type' => $this->payment_type,
            'channel_code' => $this->channel_code,
            'amount' => (int) $this->amount,
            'fee' => (int) $this->fee,
            'net_amount' => (int) $this->net_amount,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'va_number' => $this->va_number,
            'qr_string' => $this->qr_string,
            'payment_url' => $this->payment_url,
            'instructions' => $this->instructions ?? [],
            'expired_at' => $this->expired_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
