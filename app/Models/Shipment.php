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
        'package_description', 'package_weight', 'length', 'width', 'height', 'volume',
        'courier_code', 'courier_name', 'courier_service', 'vehicle', 'etd',
        'shipping_cost', 'insurance', 'wood_packing', 'total_cost', 'payment_method',
        'scheduled_pickup', 'pickup_time', 'pickup_scheduled_at', 'estimated_delivery',
        'status', 'tracking_number', 'notes',
        'whatsapp_notification', 'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_latitude'   => 'decimal:8',
            'sender_longitude'  => 'decimal:8',
            'receiver_latitude' => 'decimal:8',
            'receiver_longitude'=> 'decimal:8',
            'package_weight'    => 'decimal:2',
            'length'            => 'decimal:2',
            'width'             => 'decimal:2',
            'height'            => 'decimal:2',
            'volume'            => 'decimal:2',
            'shipping_cost'     => 'integer',
            'total_cost'        => 'integer',
            'insurance'         => 'boolean',
            'wood_packing'      => 'boolean',
            'scheduled_pickup'  => 'boolean',
            'pickup_scheduled_at' => 'datetime',
            'estimated_delivery'  => 'datetime',
            'whatsapp_sent_at'  => 'datetime',
        ];
    }

    protected $appends = ['status_label', 'status_color', 'payment_info', 'tracking_url', 'qrcode_url', 'vehicle_display_name'];

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

    public function getTrackingUrlAttribute(): string
    {
        $baseUrl = rtrim(\App\Models\Setting::get('app_url', config('app.url')), '/');
        if (empty($baseUrl) || $baseUrl === 'http://localhost') {
            $baseUrl = 'https://citycourier.pabm.space';
        }
        return $baseUrl . '/track/' . ($this->tracking_number ?? $this->shipment_number);
    }

    public function getQrCodeUrlAttribute(): string
    {
        $baseUrl = rtrim(\App\Models\Setting::get('app_url', config('app.url')), '/');
        if (empty($baseUrl) || $baseUrl === 'http://localhost') {
            $baseUrl = 'https://citycourier.pabm.space';
        }
        return $baseUrl . '/track/' . ($this->tracking_number ?? $this->shipment_number) . '/qrcode.png';
    }

    public function getVehicleDisplayNameAttribute(): string
    {
        $v = strtolower($this->vehicle ?? $this->courier_code ?? $this->courier_name ?? '');
        if (str_contains($v, 'mobil') || str_contains($v, 'car')) {
            return 'Mobil';
        }
        return 'Motor';
    }

    public function getSchedulePickupTextAttribute(): string
    {
        if ($this->scheduled_pickup && $this->pickup_scheduled_at) {
            $dt = $this->pickup_scheduled_at;
            $day = $dt->isToday() ? 'Hari ini' : ($dt->isTomorrow() ? 'Besok' : $dt->format('d/m/Y'));
            $time = $this->pickup_time ?: $dt->format('H:i');
            return "{$day}, {$time}";
        }
        if (!empty($this->pickup_time)) {
            return $this->pickup_time;
        }
        return 'Sekarang';
    }

    public function getFormattedTotalCostAttribute(): string
    {
        return 'Rp' . number_format($this->total_cost ?: 0, 0, ',', '.');
    }
}




