<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;    protected $fillable = [
        'shipment_number', 'user_id', 'customer_name', 'customer_phone',
        'sender_name', 'sender_phone', 'sender_address',
        'sender_latitude', 'sender_longitude',
        'origin_name', 'origin_id',
        'receiver_name', 'receiver_phone', 'receiver_address',
        'receiver_latitude', 'receiver_longitude',
        'destination_name', 'destination_id',
        'package_description', 'package_weight',
        'courier_code', 'courier_name', 'courier_service', 'etd',
        'shipping_cost', 'insurance', 'wood_packing', 'total_cost',
        'status', 'tracking_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sender_latitude'   => 'decimal:8',
            'sender_longitude'  => 'decimal:8',
            'receiver_latitude' => 'decimal:8',
            'receiver_longitude'=> 'decimal:8',
            'package_weight'    => 'decimal:2',
            'shipping_cost'     => 'integer',
            'total_cost'        => 'integer',
            'insurance'         => 'boolean',
            'wood_packing'      => 'boolean',
        ];
    }

    protected $appends = ['status_label', 'status_color', 'payment_info'];

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaymentInfoAttribute()
    {
        $payment = $this->payments()->where('status', 'pending')->latest()->first();
        if (!$payment) return null;

        return [
            'va_number'    => $payment->va_number,
            'bank_name'    => $payment->channel_code,
            'amount'       => $payment->amount,
            'expired_at'   => $payment->expired_at?->toIso8601String(),
            'payment_url'  => $payment->payment_url,
            'qr_string'    => $payment->qr_string,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShipmentLog::class)->latest();
    }

        protected static function boot()
    {
        parent::boot();

        static::creating(function ($shipment) {
            if (empty($shipment->shipment_number)) {
                $shipment->shipment_number = self::generateShipmentNumber();
            }
            if (empty($shipment->tracking_number)) {
                $shipment->tracking_number = self::generateTrackingNumber();
            }
        });

        static::updated(function ($shipment) {
            if ($shipment->isDirty('status') && $shipment->status === 'confirmed') {
                \App\Models\Order::firstOrCreate(
                    ['order_number' => $shipment->shipment_number],
                    [
                        'customer_name'      => $shipment->customer_name,
                        'customer_phone'     => $shipment->customer_phone,
                        'pickup_address'     => $shipment->sender_address,
                        'pickup_latitude'    => $shipment->sender_latitude,
                        'pickup_longitude'   => $shipment->sender_longitude,
                        'delivery_address'   => $shipment->receiver_address,
                        'delivery_latitude'  => $shipment->receiver_latitude,
                        'delivery_longitude' => $shipment->receiver_longitude,
                        'package_description'=> $shipment->package_description,
                        'package_weight'     => $shipment->package_weight,
                        'price'              => $shipment->total_cost,
                        'status'             => 'pending',
                        'notes'              => $shipment->notes ?? '-',
                    ]
                );
            }
            
            // Sync status from Order back to Shipment when Order updates?
            // For now, let's just make sure the Order is created.
        });
    }

    

    public static function generateShipmentNumber(): string
    {
        return 'SHP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public static function generateTrackingNumber(): string
    {
        return 'CC' . date('ymd') . strtoupper(substr(uniqid(), -4));
    }

    public function getStatusLabelAttribute(): string
    {
        switch ($this->status) {
            case 'pending':    return 'Menunggu Pembayaran';
            case 'confirmed':  return 'Dikonfirmasi';
            case 'assigned':   return 'Kurir Ditugaskan';
            case 'picking_up': return 'Proses Pickup';
            case 'picked_up':  return 'Paket Diambil';
            case 'delivering': return 'Dalam Perjalanan';
            case 'delivered':  return 'Terkirim';
            case 'cancelled':  return 'Dibatalkan';
            default:           return ucfirst($this->status);
        }
    }

    public function getStatusColorAttribute(): string
    {
        switch ($this->status) {
            case 'pending':    return 'warning';
            case 'confirmed':  return 'info';
            case 'assigned':   return 'info';
            case 'picking_up': return 'primary';
            case 'picked_up':  return 'primary';
            case 'delivering': return 'primary';
            case 'delivered':  return 'success';
            case 'cancelled':  return 'danger';
            default:           return 'secondary';
        }
    }
}




