<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Message extends Model
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_COURIER = 'courier';
    public const ROLE_SYSTEM = 'system';

    public const CHANNEL_INTERNAL = 'internal';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SYSTEM = 'system';

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_SYSTEM = 'system';

    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'sender_role',
        'client_message_id',
        'channel',
        'message_type',
        'body',
        'media_path',
        'media_mime',
        'media_size',
        'delivery_status',
        'wa_message_id',
        'wa_status',
        'wa_error_code',
        'wa_error_message',
        'metadata',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'sender_user_id' => 'integer',
        'media_size' => 'integer',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'sender_user_id');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsappMessageLog::class, 'message_id');
    }

    public function previewText(int $maxLength = 120): string
    {
        $text = trim((string) ($this->body ?? ''));
        if ($text !== '') {
            return Str::limit($text, $maxLength);
        }

        return match ($this->message_type) {
            self::TYPE_IMAGE => '[Foto]',
            self::TYPE_DOCUMENT => '[Dokumen]',
            self::TYPE_TEMPLATE => '[Notifikasi WhatsApp]',
            default => '[Pesan]',
        };
    }
}
