<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log audit komunikasi API OVO SNAP.
 * Payload tersimpan sudah melalui redaksi OvoAuditLogger — tanpa secret/Private key/token.
 */
class OvoApiLog extends Model
{
    protected $fillable = [
        'action',
        'endpoint',
        'http_status',
        'response_code',
        'success',
        'request_payload',
        'response_payload',
        'duration_ms',
    ];

    protected $casts = [
        'success'     => 'boolean',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
    ];
}
