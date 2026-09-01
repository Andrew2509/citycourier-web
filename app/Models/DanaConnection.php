<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanaConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'status',
        'masked_phone',
        'provider_reference',
        'session_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'linked_at',
        'revoked_at',
        'session_expires_at',
    ];

    protected $casts = [
        'linked_at'         => 'datetime',
        'revoked_at'        => 'datetime',
        'session_expires_at' => 'datetime',
        'token_expires_at'  => 'datetime',
    ];

    /**
     * Hide sensitive tokens from JSON serialization.
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }
}
