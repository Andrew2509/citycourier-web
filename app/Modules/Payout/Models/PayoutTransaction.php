<?php

namespace App\Modules\Payout\Models;

use App\Models\Courier;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Wallet\Models\PayoutDestination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutTransaction extends Model
{
    protected $table = 'payout_transactions';

    protected $fillable = [
        'reference',
        'idempotency_key',
        'owner_type',
        'user_id',
        'courier_id',
        'wallet_id',
        'payout_destination_id',
        'legacy_withdrawal_id',
        'payout_type',
        'provider',
        'mode',
        'is_simulated',
        'channel_code',
        'account_number_masked',
        'account_holder_name',
        'amount',
        'fee',
        'net_amount',
        'status',
        'provider_reference',
        'failure_code',
        'failure_reason',
        'retry_count',
        'status_history',
        'scheduled_at',
        'submitted_at',
        'completed_at',
        'failed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'net_amount' => 'integer',
        'is_simulated' => 'boolean',
        'retry_count' => 'integer',
        'status_history' => 'array',
        'scheduled_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(PayoutDestination::class, 'payout_destination_id');
    }

    public function appendStatusHistory(string $status, string $note = ''): void
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status' => $status,
            'note' => $note,
            'at' => now()->toIso8601String(),
        ];
        $this->status_history = $history;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'owner_type' => $this->owner_type,
            'payout_type' => $this->payout_type,
            'provider' => $this->provider,
            'mode' => $this->mode,
            'is_simulated' => (bool) $this->is_simulated,
            'channel_code' => $this->channel_code,
            'account_number_masked' => $this->account_number_masked,
            'account_holder_name' => $this->account_holder_name,
            'amount' => (int) $this->amount,
            'fee' => (int) $this->fee,
            'net_amount' => (int) $this->net_amount,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'failure_code' => $this->failure_code,
            'failure_reason' => $this->failure_reason,
            'retry_count' => (int) $this->retry_count,
            'status_history' => $this->status_history ?? [],
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
