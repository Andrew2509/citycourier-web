<?php

namespace App\Modules\Chat\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'conversations';

    protected $fillable = [
        'order_id',
        'shipment_id',
        'order_number',
        'customer_user_id',
        'courier_user_id',
        'courier_id',
        'status',
        'wa_bridged_enabled',
        'last_message_id',
        'last_message_preview',
        'last_message_at',
        'customer_unread_count',
        'courier_unread_count',
        'closed_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'shipment_id' => 'integer',
        'customer_user_id' => 'integer',
        'courier_user_id' => 'integer',
        'courier_id' => 'integer',
        'wa_bridged_enabled' => 'boolean',
        'last_message_id' => 'integer',
        'customer_unread_count' => 'integer',
        'courier_unread_count' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'customer_user_id');
    }

    public function courierUser(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'courier_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.order'), 'order_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.shipment'), 'shipment_id');
    }

    public function scopeForParticipant(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('customer_user_id', $userId)
                ->orWhere('courier_user_id', $userId);
        });
    }

    public function isParticipant(int $userId): bool
    {
        return $this->roleForUser($userId) !== null;
    }

    public function roleForUser(int $userId): ?string
    {
        if ((int) $this->customer_user_id === $userId) {
            return Message::ROLE_CUSTOMER;
        }
        if ($this->courier_user_id !== null && (int) $this->courier_user_id === $userId) {
            return Message::ROLE_COURIER;
        }

        return null;
    }

    public function unreadCountForRole(string $role): int
    {
        return $role === Message::ROLE_CUSTOMER
            ? (int) $this->customer_unread_count
            : (int) $this->courier_unread_count;
    }

    public function unreadColumnForRole(string $role): string
    {
        return $role === Message::ROLE_CUSTOMER
            ? 'customer_unread_count'
            : 'courier_unread_count';
    }
}
