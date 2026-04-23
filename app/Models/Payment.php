<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'payment_id',        // ID dari Komerce (KOMPAY-xxx)
        'payment_type',      // bank_transfer | qris
        'channel_code',      // BCA, BNI, dll (kosong untuk QRIS)
        'amount',
        'status',            // pending | paid | expired | canceled
        'va_number',         // Nomor Virtual Account
        'qr_string',         // String untuk generate QR code
        'payment_url',       // URL halaman pembayaran Komerce
        'expired_at',
        'paid_at',
        'callback_data',     // JSON raw dari callback Komerce
        'metadata',          // JSON data tambahan (order info, dll)
    ];

    protected $casts = [
        'amount'        => 'integer',
        'expired_at'    => 'datetime',
        'paid_at'       => 'datetime',
        'callback_data' => 'array',
        'metadata'      => 'array',
    ];

    // ─── Relationships ──────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }
}
