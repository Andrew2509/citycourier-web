<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentLog extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'location',
        'description',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
