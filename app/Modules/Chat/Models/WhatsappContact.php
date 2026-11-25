<?php

namespace App\Modules\Chat\Models;

use App\Modules\Payment\Services\GatewayCredentialsResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappContact extends Model
{
    public const OPT_PENDING = 'pending';
    public const OPT_IN = 'opted_in';
    public const OPT_OUT = 'opted_out';

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'user_id',
        'wa_phone_e164',
        'opt_in_status',
        'opt_in_source',
        'opted_in_at',
        'opted_out_at',
        'last_inbound_at',
        'window_expires_at',
        'active_conversation_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'active_conversation_id' => 'integer',
        'opted_in_at' => 'datetime',
        'opted_out_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'window_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('chat.host_models.user'), 'user_id');
    }

    public function activeConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'active_conversation_id');
    }

    public function isOptedIn(): bool
    {
        return $this->opt_in_status === self::OPT_IN;
    }

    public function isWindowOpen(?CarbonInterface $at = null): bool
    {
        if ($this->window_expires_at === null) {
            return false;
        }

        $now = $at ?? now();

        return $now->lt($this->window_expires_at);
    }

    public function openWindow(?CarbonInterface $inboundAt = null): void
    {
        $timestamp = $inboundAt ?? now();
        $creds = app(GatewayCredentialsResolver::class)->whatsappCloud();
        $hours = (int) ($creds['window_hours'] ?? config('whatsapp.customer_service_window_hours', 24));

        $this->last_inbound_at = $timestamp;
        $this->window_expires_at = $timestamp->copy()->addHours($hours);
    }

    public function markOptedIn(string $source = 'app_toggle'): void
    {
        $this->opt_in_status = self::OPT_IN;
        $this->opt_in_source = $source;
        $this->opted_in_at = now();
        $this->opted_out_at = null;
    }

    public function markOptedOut(string $source = 'stop_keyword'): void
    {
        $this->opt_in_status = self::OPT_OUT;
        $this->opt_in_source = $source;
        $this->opted_out_at = now();
    }
}
