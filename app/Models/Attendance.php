<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'courier_id',
        'check_in_at',
        'check_out_at',
        'status',
        'drop_point_name',
        'latitude',
        'longitude',
        'device',
        'note',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->check_in_at) {
            return null;
        }

        $end = $this->check_out_at ?? now();
        $minutes = (int) $this->check_in_at->diffInMinutes($end);

        if ($minutes <= 0) {
            return null;
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? "{$h}j {$m}m" : "{$m} menit";
    }
}