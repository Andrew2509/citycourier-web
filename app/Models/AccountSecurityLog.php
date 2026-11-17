<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token_id',
        'event_type',
        'title',
        'description',
        'device_name',
        'ip_address',
        'status',
        'metadata',
    ];

    protected $casts = [
        'token_id' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'device_name' => $this->device_name,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'time_label' => $this->created_at
                ? $this->created_at->copy()->timezone('Asia/Jakarta')->format('d M Y, H.i') . ' WIB'
                : null,
        ];
    }
}
