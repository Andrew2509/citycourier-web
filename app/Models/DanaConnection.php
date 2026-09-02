<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DanaConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'status',                // not_connected, pending, connected, expired, revoked, failed
        'external_id',
        'state_hash',
        'masked_phone',
        'provider_reference',
        'dana_user_reference',
        'session_id',            // state (sesi binding) dari DANA
        'session_expires_at',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'token_expires_at',
        'bound_at',
        'linked_at',
        'revoked_at',
        // Alias (diremap ke *_encrypted oleh setAttribute()).
        'access_token',
        'refresh_token',
    ];

    protected $appends = ['status_label'];

    protected $casts = [
        'linked_at'          => 'datetime',
        'revoked_at'         => 'datetime',
        'session_expires_at' => 'datetime',
        'token_expires_at'   => 'datetime',
        'bound_at'           => 'datetime',
    ];

    /**
     * Sensitive data selalu di-serialize sebagai null / disembunyikan.
     */
    protected $hidden = [
        'state_hash',
        'access_token',
        'refresh_token',
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    // ─── Access token (encrypted at rest) ───────────────────────────

    public function getAccessTokenAttribute(): ?string
    {
        return $this->decrypt('access_token_encrypted');
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token_encrypted'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(): ?string
    {
        return $this->decrypt('refresh_token_encrypted');
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token_encrypted'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    private function decrypt(string $column): ?string
    {
        $cipher = $this->attributes[$column] ?? null;
        if (!$cipher) {
            return null;
        }
        try {
            return Crypt::decryptString($cipher);
        } catch (DecryptException $e) {
            return null;
        }
    }

    // ─── Status helpers ──────────────────────────────────────────────

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function isExpired(): bool
    {
        if ($this->status !== 'connected') {
            return false;
        }
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        $map = [
            'not_connected' => 'NOT_CONNECTED',
            'pending'       => 'PENDING',
            'connected'     => 'CONNECTED',
            'expired'       => 'EXPIRED',
            'revoked'       => 'REVOKED',
            'failed'        => 'FAILED',
        ];
        return $map[$this->status] ?? strtoupper((string) $this->status);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}