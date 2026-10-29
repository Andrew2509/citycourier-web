<?php

namespace App\Models;

use Carbon\Carbon;
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
        'vehicle',
        'price',
        'status',
        'scheduled_pickup',
        'pickup_time',
        'pickup_scheduled_at',
        'notes',
        'issue_reason',
        'issue_notes',
        'pickup_photo',
        'delivery_photo',
        'pickup_started_at',
        'picked_up_at',
        'delivering_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancelled_from_status',
    ];

    protected $appends = [
        'tracking_number', 'sender_name', 'receiver_name', 'sender_phone', 'receiver_phone', 
        'dropoff_address', 'dropoff_latitude', 'dropoff_longitude',
        'pickup_photo_url', 'delivery_photo_url',
        'vehicle_display_name', 'is_pickup_ready', 'is_pickup_overdue',
        'pickup_countdown_minutes', 'pickup_countdown_text',
        'cancelled_by_label',
    ];

    public function getTrackingNumberAttribute()
    {
        return ($this->shipment && !empty($this->shipment->tracking_number))
            ? $this->shipment->tracking_number
            : $this->order_number;
    }

    public function getDropoffAddressAttribute() { return $this->delivery_address; }
    public function getDropoffLatitudeAttribute() { return $this->delivery_latitude; }
    public function getDropoffLongitudeAttribute() { return $this->delivery_longitude; }

    public function getPickupPhotoUrlAttribute()
    {
        if (!$this->pickup_photo) return null;
        return url('storage/' . $this->pickup_photo);
    }

    public function getDeliveryPhotoUrlAttribute()
    {
        if (!$this->delivery_photo) return null;
        return url('storage/' . $this->delivery_photo);
    }

    public function getVehicleDisplayNameAttribute(): string
    {
        $v = strtolower($this->vehicle ?? $this->shipment?->vehicle ?? 'motor');
        return $v === 'mobil' ? 'Mobil' : 'Motor';
    }

    public function getCancelledByLabelAttribute(): ?string
    {
        if (!$this->cancelled_by) {
            return $this->status === 'cancelled' ? 'Pelanggan / Sistem' : null;
        }

        return match (strtolower($this->cancelled_by)) {
            'customer', 'pelanggan', 'user' => 'Pelanggan',
            'admin', 'administrator'        => 'Admin City Courier',
            'courier', 'kurir'              => 'Kurir',
            default                         => ucfirst($this->cancelled_by),
        };
    }

    /**
     * Mengambil waktu jadwal pickup dalam zona waktu Asia/Jakarta secara konsisten.
     */
    public function getPickupScheduledAtJakarta(): ?Carbon
    {
        $raw = $this->getRawOriginal('pickup_scheduled_at');
        if (!empty($raw)) {
            return Carbon::parse($raw, 'Asia/Jakarta')->timezone('Asia/Jakarta');
        }

        if ($this->pickup_scheduled_at instanceof Carbon) {
            return $this->pickup_scheduled_at->copy()->timezone('Asia/Jakarta');
        }

        return null;
    }

    /**
     * Memeriksa apakah kurir sudah diizinkan untuk memulai penjemputan.
     * Aturan: Order instan (non-scheduled) selalu siap.
     * Order terjadwal: siap jika waktu sekarang >= (waktu jadwal - 30 menit).
     */
    public function getIsPickupReadyAttribute(): bool
    {
        $scheduled = $this->getPickupScheduledAtJakarta();
        if (!$this->scheduled_pickup || !$scheduled) {
            return true;
        }

        $now = Carbon::now('Asia/Jakarta');
        $toleranceStart = $scheduled->copy()->subMinutes(30);

        return $now->gte($toleranceStart);
    }

    /**
     * Memeriksa apakah waktu pickup terjadwal telah terlewati (overdue).
     */
    public function getIsPickupOverdueAttribute(): bool
    {
        $scheduled = $this->getPickupScheduledAtJakarta();
        if (!$this->scheduled_pickup || !$scheduled) {
            return false;
        }

        $now = Carbon::now('Asia/Jakarta');

        return $now->gt($scheduled) && in_array($this->status, ['pending', 'assigned']);
    }

    /**
     * Menghitung sisa menit hingga penjemputan (negatif jika sudah lewat / overdue).
     */
    public function getPickupCountdownMinutesAttribute(): ?int
    {
        $scheduled = $this->getPickupScheduledAtJakarta();
        if (!$this->scheduled_pickup || !$scheduled) {
            return null;
        }

        $now = Carbon::now('Asia/Jakarta');

        return (int) $now->diffInMinutes($scheduled, false);
    }

    /**
     * Label waktu ramah untuk kurir.
     */
    public function getPickupCountdownTextAttribute(): ?string
    {
        if (!$this->scheduled_pickup || !$this->pickup_scheduled_at) {
            return null;
        }

        $mins = $this->pickup_countdown_minutes;
        if ($mins === null) return null;

        if ($mins < 0) {
            $absMins = abs($mins);
            if ($absMins < 60) {
                return "Terlambat {$absMins} menit";
            }
            $hours = floor($absMins / 60);
            return "Terlambat {$hours} jam";
        }

        if ($mins <= 30) {
            return "Siap dijemput (dalam {$mins} menit)";
        }

        if ($mins < 60) {
            return "Dapat dimulai {$mins} menit lagi";
        }

        $hours = floor($mins / 60);
        $rem = $mins % 60;
        return "Jadwal {$hours} jam {$rem} mnt lagi";
    }

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
            'scheduled_pickup' => 'boolean',
            'pickup_scheduled_at' => 'datetime',
            'pickup_started_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivering_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public static function generateOrderNumber(): string
    {
        return 'CC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
