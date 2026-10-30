<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    /**
     * Batas waktu minimal pembatalan mandiri sebelum jadwal pickup (dalam menit).
     * Aturan: Waktu pickup - Waktu sekarang > 120 menit (2 jam).
     */
    public const CUSTOMER_CANCEL_CUTOFF_MINUTES = 120;

    /**
     * Daftar status yang mengizinkan pembatalan mandiri oleh pelanggan
     * (sebelum proses penjemputan dimulai oleh kurir).
     */
    public const CANCELLABLE_STATUSES = ['pending', 'confirmed', 'assigned'];

    protected $fillable = [
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
        'cancelled_at', 'cancelled_by', 'cancellation_reason', 'cancelled_from_status',
        'whatsapp_notification', 'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sender_latitude'     => 'decimal:8',
            'sender_longitude'    => 'decimal:8',
            'receiver_latitude'   => 'decimal:8',
            'receiver_longitude'  => 'decimal:8',
            'package_weight'      => 'decimal:2',
            'length'              => 'decimal:2',
            'width'               => 'decimal:2',
            'height'              => 'decimal:2',
            'volume'              => 'decimal:2',
            'shipping_cost'       => 'integer',
            'total_cost'          => 'integer',
            'insurance'           => 'boolean',
            'wood_packing'        => 'boolean',
            'scheduled_pickup'    => 'boolean',
            'pickup_scheduled_at' => 'datetime',
            'estimated_delivery'  => 'datetime',
            'cancelled_at'        => 'datetime',
            'whatsapp_sent_at'    => 'datetime',
        ];
    }

    protected $appends = [
        'status_label',
        'status_color',
        'payment_info',
        'tracking_url',
        'qrcode_url',
        'vehicle_display_name',
        'can_customer_cancel',
        'cancellation_block_reason',
        'minutes_until_pickup',
        'cancelled_by_label',
    ];

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Order::class, 'order_number', 'shipment_number');
    }

    public function getPaymentInfoAttribute()
    {
        $payment = $this->payments()->where('status', 'pending')->latest()->first();
        if (!$payment) return null;

        return [
            // ID transaksi iPaymu: wajib dikirim agar aplikasi bisa memanggil
            // /payment/status dan /payment/cancel untuk pesanan yang dibuka
            // ulang dari daftar "belum dibayar".
            'payment_id'   => $payment->payment_id,
            'status'       => $payment->status,
            'payment_type' => $payment->payment_type,
            'va_number'    => $payment->va_number,
            'bank_name'    => $payment->channel_code,
            'amount'       => $payment->amount,
            'expired_at'  => $payment->expired_at?->toIso8601String(),
            'payment_url' => $payment->payment_url,
            'qr_string'   => $payment->qr_string,
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

        static::created(function ($shipment) {
            if ($shipment->status === 'confirmed') {
                self::syncToOrder($shipment);
            }
        });

        static::updated(function ($shipment) {
            if ($shipment->isDirty('status') && $shipment->status === 'confirmed') {
                self::syncToOrder($shipment);
            } elseif ($shipment->isDirty(['pickup_scheduled_at', 'pickup_time', 'scheduled_pickup'])) {
                $existingOrder = Order::where('order_number', $shipment->shipment_number)->first();
                if ($existingOrder) {
                    $existingOrder->update([
                        'scheduled_pickup'    => (bool) $shipment->scheduled_pickup,
                        'pickup_time'         => $shipment->pickup_time,
                        'pickup_scheduled_at' => $shipment->pickup_scheduled_at,
                    ]);
                }
            }
        });
    }

    public static function syncToOrder(Shipment $shipment): ?Order
    {
        if (!in_array($shipment->status, ['confirmed', 'assigned', 'picking_up', 'picked_up', 'delivering', 'delivered'])) {
            return null;
        }

        $orderData = [
            'customer_name'       => $shipment->customer_name,
            'customer_phone'      => $shipment->customer_phone,
            'pickup_address'      => $shipment->sender_address,
            'pickup_latitude'     => $shipment->sender_latitude,
            'pickup_longitude'    => $shipment->sender_longitude,
            'delivery_address'    => $shipment->receiver_address,
            'delivery_latitude'   => $shipment->receiver_latitude,
            'delivery_longitude'  => $shipment->receiver_longitude,
            'package_description' => $shipment->package_description,
            'package_weight'      => $shipment->package_weight,
            'vehicle'             => $shipment->vehicle ?? (strtolower($shipment->courier_code) === 'mobil' ? 'mobil' : 'motor'),
            'price'               => $shipment->total_cost,
            'scheduled_pickup'    => (bool) $shipment->scheduled_pickup,
            'pickup_time'         => $shipment->pickup_time,
            'pickup_scheduled_at' => $shipment->pickup_scheduled_at,
            'notes'               => $shipment->notes ?? '-',
        ];

        $order = Order::where('order_number', $shipment->shipment_number)->first();
        if ($order) {
            $order->update($orderData);
            return $order;
        }

        $orderData['order_number'] = $shipment->shipment_number;
        $orderData['status'] = 'pending';
        return Order::create($orderData);
    }

    /**
     * Mengambil waktu jadwal pickup dalam zona waktu Asia/Jakarta secara konsisten.
     */
    public function getPickupScheduledAtJakarta(?Order $relatedOrder = null): ?Carbon
    {
        $raw = $this->getRawOriginal('pickup_scheduled_at');
        if (!empty($raw)) {
            return Carbon::parse($raw, 'Asia/Jakarta')->timezone('Asia/Jakarta');
        }

        if ($this->pickup_scheduled_at instanceof Carbon) {
            return $this->pickup_scheduled_at->copy()->timezone('Asia/Jakarta');
        }

        $order = $relatedOrder ?? ($this->relationLoaded('order') ? $this->order : null);
        if ($order) {
            $orderRaw = $order->getRawOriginal('pickup_scheduled_at');
            if (!empty($orderRaw)) {
                return Carbon::parse($orderRaw, 'Asia/Jakarta')->timezone('Asia/Jakarta');
            }
            if ($order->pickup_scheduled_at instanceof Carbon) {
                return $order->pickup_scheduled_at->copy()->timezone('Asia/Jakarta');
            }
        }

        return null;
    }

    /**
     * Evaluasi lengkap apakah pelanggan dapat membatalkan pesanan secara mandiri.
     *
     * Aturan:
     * 1. Status harus dalam ['pending', 'confirmed', 'assigned'] dan proses pickup belum dimulai.
     * 2. Jika pesanan memiliki jadwal pickup, sisa waktu harus > 120 menit (2 jam):
     *    (Waktu pickup - Waktu sekarang > 120 menit / 7200 detik).
     *
     * @return array{
     *   allowed: bool,
     *   reason_code: string|null,
     *   message: string|null,
     *   minutes_until_pickup: int|null
     * }
     */
    public function evaluateCustomerCancellation(?Carbon $referenceNow = null, ?Order $lockedOrder = null): array
    {
        $now = ($referenceNow ?? Carbon::now('Asia/Jakarta'))->copy()->timezone('Asia/Jakarta');
        $status = strtolower((string) $this->status);

        // 1. Cek jika sudah dibatalkan
        if ($status === 'cancelled') {
            return [
                'allowed'              => false,
                'reason_code'          => 'already_cancelled',
                'message'              => 'Pesanan ini sudah dibatalkan sebelumnya.',
                'minutes_until_pickup' => null,
            ];
        }

        // 2. Cek jika sudah selesai dikirim
        if ($status === 'delivered') {
            return [
                'allowed'              => false,
                'reason_code'          => 'already_delivered',
                'message'              => 'Pesanan yang sudah terkirim tidak dapat dibatalkan.',
                'minutes_until_pickup' => null,
            ];
        }

        // 3. Cek status pengiriman & Order terkait (apakah proses pickup sudah dimulai)
        $order = $lockedOrder ?? ($this->relationLoaded('order')
            ? $this->order
            : Order::where('order_number', $this->shipment_number)->first());

        $orderStatus = $order ? strtolower((string) $order->status) : null;
        $pickupAlreadyStarted = !in_array($status, self::CANCELLABLE_STATUSES, true)
            || ($order && (
                $order->pickup_started_at !== null
                || $order->picked_up_at !== null
                || in_array($orderStatus, ['picking_up', 'picked_up', 'delivering', 'in_transit', 'delivered'], true)
            ));

        if ($pickupAlreadyStarted) {
            return [
                'allowed'              => false,
                'reason_code'          => 'pickup_already_started',
                'message'              => 'Pesanan tidak dapat dibatalkan secara mandiri karena kurir sudah memulai proses penjemputan atau pengiriman.',
                'minutes_until_pickup' => null,
            ];
        }

        // 4. Cek batas waktu 2 jam (120 menit) untuk pesanan dengan jadwal pickup
        $scheduledAt = $this->getPickupScheduledAtJakarta($order);
        $isScheduled = (bool) $this->scheduled_pickup || ($order && (bool) $order->scheduled_pickup) || $scheduledAt !== null;

        if ($isScheduled && $scheduledAt !== null) {
            $diffSeconds = $now->diffInSeconds($scheduledAt, false);
            $diffMinutes = $diffSeconds >= 0
                ? (int) floor($diffSeconds / 60)
                : (int) ceil($diffSeconds / 60);

            // Aturan: Waktu pickup - Waktu sekarang > 120 menit (> 7200 detik)
            $cutoffSeconds = self::CUSTOMER_CANCEL_CUTOFF_MINUTES * 60;

            if ($diffSeconds <= 0) {
                return [
                    'allowed'              => false,
                    'reason_code'          => 'pickup_time_passed',
                    'message'              => 'Pembatalan mandiri hanya dapat dilakukan lebih dari 2 jam sebelum jadwal pickup. Karena jadwal pickup kamu sudah terlalu dekat, silakan hubungi admin City Courier untuk mendapatkan bantuan.',
                    'minutes_until_pickup' => $diffMinutes,
                ];
            }

            if ($diffSeconds <= $cutoffSeconds) {
                return [
                    'allowed'              => false,
                    'reason_code'          => 'within_2_hours',
                    'message'              => 'Pembatalan mandiri hanya dapat dilakukan lebih dari 2 jam sebelum jadwal pickup. Karena jadwal pickup kamu sudah terlalu dekat, silakan hubungi admin City Courier untuk mendapatkan bantuan.',
                    'minutes_until_pickup' => $diffMinutes,
                ];
            }

            return [
                'allowed'              => true,
                'reason_code'          => null,
                'message'              => null,
                'minutes_until_pickup' => $diffMinutes,
            ];
        }

        return [
            'allowed'              => true,
            'reason_code'          => null,
            'message'              => null,
            'minutes_until_pickup' => null,
        ];
    }

    public function getCanCustomerCancelAttribute(): bool
    {
        return $this->evaluateCustomerCancellation()['allowed'];
    }

    public function getCancellationBlockReasonAttribute(): ?string
    {
        return $this->evaluateCustomerCancellation()['reason_code'];
    }

    public function getMinutesUntilPickupAttribute(): ?int
    {
        $scheduledAt = $this->getPickupScheduledAtJakarta();
        if (!$scheduledAt) {
            return null;
        }
        $now = Carbon::now('Asia/Jakarta');
        $diffSeconds = $now->diffInSeconds($scheduledAt, false);
        return $diffSeconds >= 0
            ? (int) floor($diffSeconds / 60)
            : (int) ceil($diffSeconds / 60);
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
            $baseUrl = 'https://www.neonflux.my.id';
        }
        return $baseUrl . '/track/' . ($this->tracking_number ?? $this->shipment_number);
    }

    public function getQrCodeUrlAttribute(): string
    {
        $baseUrl = rtrim(\App\Models\Setting::get('app_url', config('app.url')), '/');
        if (empty($baseUrl) || $baseUrl === 'http://localhost') {
            $baseUrl = 'https://www.neonflux.my.id';
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
        $dt = $this->getPickupScheduledAtJakarta();
        if ($this->scheduled_pickup && $dt) {
            $day = $dt->isToday() ? 'Hari ini' : ($dt->isTomorrow() ? 'Besok' : $dt->format('d/m/Y'));
            $time = $this->pickup_time ?: $dt->format('h:i A');
            // Hindari duplikasi tanggal jika pickup_time sudah memuat koma (e.g. "30 Oktober 2026, 08:00 AM")
            if (str_contains($time, ',')) {
                $parts = explode(',', $time);
                $time = trim(end($parts));
            }
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
