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

        protected $appends = ['sender_name', 'receiver_name', 'sender_phone', 'receiver_phone'];

    protected $appends = [
        'sender_name', 'receiver_name', 'sender_phone', 'receiver_phone', 
        'dropoff_address', 'dropoff_latitude', 'dropoff_longitude'
    ];

    public function getDropoffAddressAttribute() { return $this->delivery_address; }
    public function getDropoffLatitudeAttribute() { return $this->delivery_latitude; }
    public function getDropoffLongitudeAttribute() { return $this->delivery_longitude; }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'order_number', 'shipment_number');
    }

    public function getSenderNameAttribute()
    {
        return $this->shipment ? $this->shipment->sender_name : $this->customer_name;
    }

    public function getReceiverNameAttribute()
    {
        return $this->shipment ? $this->shipment->receiver_name : $this->customer_name;
    }

    public function getSenderPhoneAttribute()
    {
        return $this->shipment ? $this->shipment->sender_phone : $this->customer_phone;
    }

    public function getReceiverPhoneAttribute()
    {
        return $this->shipment ? $this->shipment->receiver_phone : $this->customer_phone;
    }

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



