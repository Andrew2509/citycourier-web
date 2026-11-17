<?php

namespace App\Modules\Wallet\Models;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutDestination extends Model
{
    protected $table = 'payout_destinations';

    protected $fillable = [
        'owner_type',
        'user_id',
        'courier_id',
        'destination_type',
        'channel_code',
        'channel_name',
        'account_number',
        'account_number_masked',
        'account_holder_name',
        'is_verified',
        'verification_status',
        'verified_at',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'account_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->owner_type,
            'destination_type' => $this->destination_type,
            'channel_code' => $this->channel_code,
            'channel_name' => $this->channel_name,
            'account_number_masked' => $this->account_number_masked,
            'account_holder_name' => $this->account_holder_name,
            'is_verified' => (bool) $this->is_verified,
            'verification_status' => $this->verification_status,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
