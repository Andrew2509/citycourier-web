<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierLocation extends Model
{
    protected $fillable = [
        'courier_id',
        'shipment_id',
        'latitude',
        'longitude',
        'accuracy',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
