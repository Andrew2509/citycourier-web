<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallSession extends Model
{
    public const STATUS_RINGING = 'ringing';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_MISSED = 'missed';
    public const STATUS_ENDED = 'ended';
    public const STATUS_FAILED = 'failed';

    public const ACTIVE_STATUSES = [
        self::STATUS_RINGING,
        self::STATUS_ACCEPTED,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_REJECTED,
        self::STATUS_MISSED,
        self::STATUS_ENDED,
        self::STATUS_FAILED,
    ];

    protected $table = 'call_sessions';

    protected $fillable = [
        'call_uuid',
        'order_id',
        'conversation_id',
        'caller_id',
        'receiver_id',
        'status',
        'end_reason',
        'started_at',
        'answered_at',
        'ended_at',
        'duration',
        'expires_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'conversation_id' => 'integer',
        'caller_id' => 'integer',
        'receiver_id' => 'integer',
        'duration' => 'integer',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'receiver_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.order'), 'order_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(CallSignal::class, 'call_session_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isParticipant(int $userId): bool
    {
        return (int) $this->caller_id === $userId || (int) $this->receiver_id === $userId;
    }

    public function counterpartyIdFor(int $userId): ?int
    {
        if ((int) $this->caller_id === $userId) {
            return (int) $this->receiver_id;
        }
        if ((int) $this->receiver_id === $userId) {
            return (int) $this->caller_id;
        }

        return null;
    }
}
