<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessageLog extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';
    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_STATUS_WEBHOOK = 'status_webhook';

    public const PROCESS_RECEIVED = 'received';
    public const PROCESS_PROCESSED = 'processed';
    public const PROCESS_DUPLICATE = 'duplicate';
    public const PROCESS_IGNORED = 'ignored';
    public const PROCESS_FAILED = 'failed';

    protected $table = 'whatsapp_message_logs';

    protected $fillable = [
        'message_id',
        'conversation_id',
        'wa_message_id',
        'direction',
        'event_hash',
        'signature_valid',
        'http_status',
        'processing_status',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'message_id' => 'integer',
        'conversation_id' => 'integer',
        'signature_valid' => 'boolean',
        'http_status' => 'integer',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
