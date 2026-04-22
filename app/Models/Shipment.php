<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_number',
        'user_id',
        'customer_name',
        'customer_phone',
        'sender_name',
        'sender_phone',
        'sender_address',
        'origin_name',
        'origin_id',
        'receiver_name',
        'receiver_phone',
        'receiver_address',
        'destination_name',
        'destination_id',
        'package_description',
        'package_weight',
        'courier_code',
        'courier_name',
        'courier_service',
        'etd',
        'shipping_cost',
        'insurance',
        'wood_packing',
        'total_cost',
        'status',
        'tracking_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_weight' => 'decimal:2',
            'shipping_cost'  => 'integer',
            'total_cost'     => 'integer',
            'insurance'      => 'boolean',
            'wood_packing'   => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function generateShipmentNumber(): string
    {
        return 'SHP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'    => 'Menunggu',
            'confirmed'  => 'Dikonfirmasi',
            'picked_up'  => 'Diambil',
            'in_transit' => 'Dalam Perjalanan',
            'delivered'  => 'Terkirim',
            'cancelled'  => 'Dibatalkan',
            default      => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending'    => 'warning',
            'confirmed'  => 'info',
            'picked_up'  => 'primary',
            'in_transit' => 'primary',
            'delivered'  => 'success',
            'cancelled'  => 'danger',
            default      => 'secondary',
        ];
    }
}
