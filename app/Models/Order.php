<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'courier_id',
        'customer_name',
        'customer_phone',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'package_description',
        'package_weight',
        'price',
        'status',
        'notes',
        'pickup_photo',
        'delivery_photo',
        'picked_up_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_latitude' => 'decimal:8',
            'pickup_longitude' => 'decimal:8',
            'delivery_latitude' => 'decimal:8',
            'delivery_longitude' => 'decimal:8',
            'package_weight' => 'decimal:2',
            'price' => 'decimal:0',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Get the courier assigned to this order.
     */
    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    /**
     * Generate a unique order number.
     */
    public static function generateOrderNumber(): string
    {
        return 'CC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
