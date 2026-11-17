<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhook extends Model
{
    protected $table = 'payment_webhooks';

    protected $fillable = [
        'provider',
        'event_type',
        'external_id',
        'provider_reference',
        'webhook_hash',
        'signature_valid',
        'processing_status',
        'headers',
        'payload',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'headers' => 'array',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
