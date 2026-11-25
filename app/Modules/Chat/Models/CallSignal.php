<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSignal extends Model
{
    public const TYPE_OFFER = 'offer';
    public const TYPE_ANSWER = 'answer';
    public const TYPE_ICE_CANDIDATE = 'ice_candidate';

    public $timestamps = false;

    protected $table = 'call_signals';

    protected $fillable = [
        'call_session_id',
        'sender_id',
        'receiver_id',
        'signal_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'call_session_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class, 'call_session_id');
    }
}
