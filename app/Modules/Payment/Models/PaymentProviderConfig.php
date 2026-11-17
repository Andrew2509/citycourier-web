<?php

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentProviderConfig extends Model
{
    protected $table = 'payment_provider_configs';

    protected $fillable = [
        'provider_code',
        'display_name',
        'mode',
        'is_active',
        'supports_money_in',
        'supports_money_out',
        'supports_customer_withdrawal',
        'capabilities',
        'settings',
        'health_status',
        'last_tested_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_money_in' => 'boolean',
        'supports_money_out' => 'boolean',
        'supports_customer_withdrawal' => 'boolean',
        'capabilities' => 'array',
        'settings' => 'array',
        'last_tested_at' => 'datetime',
    ];
}
