<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentLog;
use App\Models\CourierLocation;
use App\Models\Courier;
use Illuminate\Support\Facades\DB;

class TrackingService
{
    /**
     * Status labels untuk riwayat pelacakan
     */
    private array $statusLabels = [
        'pending'         => 'Pesanan Dibuat',
        'confirmed'       => 'Pesanan Dikonfirmasi',
        'assigned'        => 'Kurir Ditugaskan',
        'picking_up'      => 'Kurir Sedang Menjemput',
        'picked_up'       => 'Paket Dalam Perjalanan ke Penerima',
        'delivering'      => 'Segera Tiba',
        'delivered'       => 'Paket Telah Diterima',
        'cancelled'       => 'Pesanan Dibatalkan',
    ];

    /**
     * Deskripsi otomatis untuk setiap status
     */
    private array $statusDescriptions = [
        'pending'         => 'Pesanan baru dibuat dan menunggu pembayaran.',
        'confirmed'       => 'Pembayaran telah dikonfirmasi.',
        'assigned'        => 'Kurir telah ditugaskan. Langsung menjemput paket di lokasi pengirim.',
        'picking_up'      => 'Kurir sedang dalam perjalanan menjemput paket.',
        'picked_up'       => 'Paket telah dijemput dan sedang dalam perjalanan ke lokasi penerima.',
        'delivering'      => 'Kurir hampir sampai di lokasi penerima.',
        'delivered'       => 'Paket telah berhasil diterima oleh penerima.',
        'cancelled'       => 'Pengiriman dibatalkan.',
    ];

    /**
     * Buat riwayat pelacakan otomatis saat status berubah.
     * Dipanggil dari OrderController::updateStatus atau ShipmentController.
     */
    public function createStatusHistory(
        Shipment $shipment,
        string $newStatus,
        ?int $courierId = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $accuracy = null,
        ?string $customDescription = null
    ): ShipmentLog {
        $description = $customDescription ?? ($this->statusDescriptions[$newStatus] ?? "Status: {$newStatus}");

        $log = $shipment->logs()->create([
            'courier_id'  => $courierId,
            'status'      => $newStatus,
            'location'    => $this->getStatusLocation($newStatus),
            'description' => $description,
            'latitude'    => $latitude,
            'longitude'   => $longitude,
            'accuracy'    => $accuracy,
        ]);

        return $log;
    }

    /**
     * Buat riwayat pelacakan saat pesanan pertama kali dibuat.
     */
    public function createOrderCreatedHistory(Shipment $shipment): ShipmentLog
    {
        return $this->createStatusHistory(
            $shipment,
            'pending',
            null,
            $shipment->sender_latitude,
            $shipment->sender_longitude,
            null,
            'Pesanan berhasil dibuat. Menunggu pembayaran dan konfirmasi.'
        );
    }

    /**
     * Simpan lokasi GPS kurir ke courier_locations.
     * Hanya menyimpan jika ada perubahan jarak minimal atau interval waktu.
     *
     * $shipmentId boleh null → menyimpan "lokasi umum" kurir tanpa konteks
     * pengiriman (dipakai saat kurir online/navigasi/terima pesanan).
     */
    public function saveCourierLocation(
        int $courierId,
        ?int $shipmentId,
        float $latitude,
        float $longitude,
        ?float $accuracy = null,
        ?float $speedKmh = null,
        ?int $batteryPercent = null
    ): CourierLocation {
        $courier = Courier::find($courierId);
        if (!$courier) {
            throw new \RuntimeException('Courier not found');
        }

        // Cek apakah ada perubahan signifikan dari lokasi terakhir
        $lastLocation = CourierLocation::where('courier_id', $courierId)
            ->when($shipmentId !== null, fn ($q) => $q->where('shipment_id', $shipmentId))
            ->latest('recorded_at')
            ->first();

        if ($lastLocation) {
            $distance = $this->calculateDistance(
                $lastLocation->latitude, $lastLocation->longitude,
                $latitude, $longitude
            );
            $timeDiff = now()->diffInSeconds($lastLocation->recorded_at);

            // Skip jika jarak < 10 meter DAN waktu < 5 detik
            if ($distance < 10 && $timeDiff < 5) {
                return $lastLocation;
            }
        }

        $location = CourierLocation::create([
            'courier_id'      => $courierId,
            'shipment_id'     => $shipmentId,
            'latitude'        => $latitude,
            'longitude'       => $longitude,
            'accuracy'        => $accuracy,
            'speed_kmh'       => $speedKmh,
            'battery_percent' => $batteryPercent,
            'recorded_at'     => now(),
        ]);

        // Update lokasi terakhir di tabel courier
        $courier->update([
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ]);

        return $location;
    }

    /**
     * Ambil lokasi terbaru kurir untuk shipment tertentu.
     */
    public function getLatestCourierLocation(int $courierId, int $shipmentId): ?CourierLocation
    {
        return CourierLocation::where('courier_id', $courierId)
            ->where('shipment_id', $shipmentId)
            ->latest('recorded_at')
            ->first();
    }

    /**
     * Ambil data tracking lengkap untuk customer.
     * Mengembalikan status, lokasi kurir, dan timeline.
     */
    public function getTrackingData(string $trackingNumber): ?array
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)
            ->orWhere('shipment_number', $trackingNumber)
            ->first();

        if (!$shipment) {
            return null;
        }

        // Ambil timeline dari shipment_logs
        $logs = $shipment->logs()
            ->with('courier')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'status'      => $log->status,
                    'label'       => $this->statusLabels[$log->status] ?? $log->status,
                    'description' => $log->description,
                    'location'    => $log->location,
                    'latitude'    => $log->latitude,
                    'longitude'   => $log->longitude,
                    'timestamp'   => $log->created_at->toIso8601String(),
                ];
            });

        // Ambil lokasi terakhir kurir (jika ada)
        $courierLocation = null;
        $courierName = null;
        $courierPhone = null;
        $courierPhoto = null;
        $courierVehicleBrand = null;
        $courierVehicleModel = null;
        $courierVehiclePlate = null;
        $courierVehicleType = null;
        $courierDetails = null;
        $courierVehicleDesc = null;

        if ($shipment->status !== 'pending' && $shipment->status !== 'confirmed') {
            // Cari order yang terkait dengan shipment ini
            $order = \App\Models\Order::where('order_number', $shipment->shipment_number)
                ->whereNotNull('courier_id')
                ->first();

            $courierId = $order ? $order->courier_id : null;
            if (!$courierId) {
                $lastLogWithCourier = $shipment->logs()->whereNotNull('courier_id')->latest()->first();
                if ($lastLogWithCourier) {
                    $courierId = $lastLogWithCourier->courier_id;
                }
            }

            if ($courierId) {
                // 1. Coba ambil dari courier_locations (GPS real-time)
                $latestLocation = $this->getLatestCourierLocation($courierId, $shipment->id);
                if ($latestLocation) {
                    $courierLocation = [
                        'latitude'  => $latestLocation->latitude,
                        'longitude' => $latestLocation->longitude,
                    ];
                }

                // 2. Fallback: ambil lokasi terakhir dari tabel couriers
                if (!$courierLocation) {
                    $courier = Courier::find($courierId);
                    if ($courier && $courier->latitude && $courier->longitude) {
                        $courierLocation = [
                            'latitude'  => $courier->latitude,
                            'longitude' => $courier->longitude,
                        ];
                    }
                }

                // 3. Fallback: gunakan lokasi pengirim (origin) jika kurir belum update GPS
                if (!$courierLocation && $shipment->sender_latitude && $shipment->sender_longitude) {
                    $courierLocation = [
                        'latitude'  => $shipment->sender_latitude,
                        'longitude' => $shipment->sender_longitude,
                    ];
                }

                // Ambil info kurir lengkap
                $courier = Courier::with('user')->find($courierId);
                if ($courier) {
                    $courierName = $courier->user->name ?? $courier->name ?? 'Kurir';
                    $courierPhone = $courier->phone ?? $courier->user?->phone;
                    $courierPhoto = $courier->photo
                        ? (str_starts_with($courier->photo, 'http') ? $courier->photo : url('storage/' . $courier->photo))
                        : null;
                    $courierVehicleBrand = $courier->vehicle_brand;
                    $courierVehicleModel = $courier->vehicle_model;
                    $courierVehiclePlate = $courier->vehicle_plate;
                    $courierVehicleType  = $courier->vehicle_type ?? 'motor';

                    $brandModel = trim(($courierVehicleBrand ?? '') . ' ' . ($courierVehicleModel ?? ''));
                    $courierVehicleDesc = !empty($brandModel) && !empty($courierVehiclePlate)
                        ? "{$brandModel} • {$courierVehiclePlate}"
                        : (!empty($brandModel) ? $brandModel : ($courierVehiclePlate ?? ''));

                    $courierDetails = [
                        'id'                  => $courier->id,
                        'name'                => $courierName,
                        'phone'               => $courierPhone,
                        'photo'               => $courierPhoto,
                        'photo_url'           => $courierPhoto,
                        'avatar'              => $courierPhoto,
                        'vehicle_type'        => $courierVehicleType,
                        'vehicle_brand'       => $courierVehicleBrand,
                        'vehicle_model'       => $courierVehicleModel,
                        'vehicle_plate'       => $courierVehiclePlate,
                        'plate_number'        => $courierVehiclePlate,
                        'vehicle_description' => $courierVehicleDesc,
                    ];
                }
            }
        }

        // Sumber lokasi pemesanan (pengiriman → penerima). Bila koordinat atau
        // alamat shipment kosong, ambil dari Order terkait (source of truth).
        $originAddress = $shipment->sender_address;
        $originLat     = $shipment->sender_latitude;
        $originLng     = $shipment->sender_longitude;
        $destAddress   = $shipment->receiver_address;
        $destLat       = $shipment->receiver_latitude;
        $destLng       = $shipment->receiver_longitude;

        if (($originAddress === null || $destAddress === null) ||
            ($originLat === null || $destLat === null)) {
            $order = \App\Models\Order::where('order_number', $shipment->shipment_number)->first();
            if ($order) {
                $originAddress = $originAddress ?? $order->pickup_address;
                $destAddress   = $destAddress   ?? $order->delivery_address;
                if ($originLat === null) {
                    $originLat = $order->pickup_latitude;
                    $originLng = $order->pickup_longitude;
                }
                if ($destLat === null) {
                    $destLat = $order->delivery_latitude;
                    $destLng = $order->delivery_longitude;
                }
            }
        }

        $cancelEval = $shipment->evaluateCustomerCancellation();
        $pickupScheduledAt = $shipment->getPickupScheduledAtJakarta();

        return [
            'tracking_number'           => $shipment->tracking_number,
            'shipment_number'           => $shipment->shipment_number,
            'status'                    => $shipment->status,
            'status_label'              => $this->statusLabels[$shipment->status] ?? $shipment->status,
            'sender_name'               => $shipment->sender_name,
            'sender_phone'              => $shipment->sender_phone,
            'receiver_name'             => $shipment->receiver_name,
            'receiver_phone'            => $shipment->receiver_phone,
            'vehicle'                   => $shipment->vehicle_type ?? $shipment->vehicle ?? 'motor',
            'package_weight'            => (float) ($shipment->package_weight ?? 1.0),
            'payment_method'            => $shipment->payment_method ?? 'COD',
            'total_cost'                => (int) ($shipment->total_cost ?? 0),
            'scheduled_pickup'          => (bool) $shipment->scheduled_pickup,
            'pickup_scheduled_at'       => $pickupScheduledAt?->toIso8601String(),
            'pickup_time'               => $shipment->pickup_time,
            'schedule_pickup_text'      => $shipment->schedule_pickup_text ?? 'Sekarang',
            'can_customer_cancel'       => $cancelEval['allowed'],
            'cancellation_block_reason' => $cancelEval['reason_code'],
            'cancellation_block_message'=> $cancelEval['message'],
            'minutes_until_pickup'      => $cancelEval['minutes_until_pickup'],
            'cancelled_at'              => $shipment->cancelled_at?->toIso8601String(),
            'cancelled_by'              => $shipment->cancelled_by,
            'cancelled_by_label'        => $shipment->cancelled_by_label,
            'cancellation_reason'       => $shipment->cancellation_reason,
            'cancelled_from_status'     => $shipment->cancelled_from_status,
            'qr_code_url'               => $shipment->qr_code_url,
            'origin' => [
                'address'   => $originAddress,
                'latitude'  => $originLat,
                'longitude' => $originLng,
            ],
            'destination' => [
                'address'   => $destAddress,
                'latitude'  => $destLat,
                'longitude' => $destLng,
            ],
            'courier_location'      => $courierLocation,
            'courier_name'          => $courierName,
            'courier_phone'         => $courierPhone,
            'courier_photo'         => $courierPhoto,
            'courier_avatar'        => $courierPhoto,
            'courier_vehicle_brand' => $courierVehicleBrand,
            'courier_vehicle_model' => $courierVehicleModel,
            'courier_vehicle_plate' => $courierVehiclePlate,
            'vehicle_brand'         => $courierVehicleBrand,
            'vehicle_model'         => $courierVehicleModel,
            'vehicle_plate'         => $courierVehiclePlate,
            'courier'               => $courierDetails,
            'vehicle'               => !empty($courierVehicleDesc) ? $courierVehicleDesc : ($shipment->vehicle_type ?? $shipment->vehicle ?? 'motor'),
            'timeline'              => $logs->toArray(),
            'estimated_delivery'    => $shipment->estimated_delivery?->toIso8601String(),
        ];
    }

    /**
     * Dapatkan lokasi default berdasarkan status.
     */
    private function getStatusLocation(string $status): string
    {
        $locations = [
            'pending'    => 'Sistem',
            'confirmed'  => 'Sistem',
            'assigned'   => 'Menuju Lokasi Pengirim',
            'picking_up' => 'Sedang Menjemput Paket',
            'picked_up'  => 'Dalam Perjalanan ke Penerima',
            'delivering' => 'Segera Sampai',
            'delivered'  => 'Lokasi Penerima',
            'cancelled'  => 'Dibatalkan',
        ];
        return $locations[$status] ?? 'Tidak Diketahui';
    }

    /**
     * Hitung jarak antara dua koordinat (Haversine formula).
     * Mengembalikan jarak dalam meter.
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
