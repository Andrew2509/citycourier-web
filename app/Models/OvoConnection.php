<?php

namespace App\Models;

/**
 * Model representasi koneksi OVO kurir.
 * Berbagi tabel penyimpanan yang sama dengan DanaConnection untuk kompatibilitas foreign key.
 */
class OvoConnection extends DanaConnection
{
    protected $table = 'dana_connections';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->provider)) {
                $model->provider = 'ovo';
            }
        });
    }

    public function isOvo(): bool
    {
        return $this->provider === 'ovo';
    }
}
