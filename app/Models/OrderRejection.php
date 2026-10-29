<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderRejection extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'shipment_id',
        'courier_id',
        'reason',
        'notes',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'rejected_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
