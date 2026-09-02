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
     */
    public function saveCourierLocation(
        int $courierId,
        int $shipmentId,
        float $latitude,
        float $longitude,
        ?float $accuracy = null
    ): CourierLocation {
        $courier = Courier::find($courierId);
        if (!$courier) {
            throw new \RuntimeException('Courier not found');
        }

        // Cek apakah ada perubahan signifikan dari lokasi terakhir
        $lastLocation = CourierLocation::where('courier_id', $courierId)
            ->where('shipment_id', $shipmentId)
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
            'courier_id'   => $courierId,
            'shipment_id'  => $shipmentId,
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'accuracy'     => $accuracy,
            'recorded_at'  => now(),
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

        if ($shipment->status !== 'pending' && $shipment->status !== 'confirmed') {
            // Cari order yang terkait dengan shipment ini
            $order = \App\Models\Order::where('order_number', $shipment->shipment_number)
                ->whereNotNull('courier_id')
                ->first();

            if ($order && $order->courier_id) {
                $latestLocation = $this->getLatestCourierLocation($order->courier_id, $shipment->id);
                if ($latestLocation) {
                    $courierLocation = [
                        'latitude'  => $latestLocation->latitude,
                        'longitude' => $latestLocation->longitude,
                    ];
                }

                // Ambil info kurir
                $courier = Courier::find($order->courier_id);
                if ($courier) {
                    $courierName = $courier->user->name ?? 'Kurir';
                    $courierPhone = $courier->phone;
                }
            }
        }

        return [
            'tracking_number'   => $shipment->tracking_number,
            'shipment_number'   => $shipment->shipment_number,
            'status'            => $shipment->status,
            'status_label'      => $this->statusLabels[$shipment->status] ?? $shipment->status,
            'origin' => [
                'address'   => $shipment->sender_address,
                'latitude'  => $shipment->sender_latitude,
                'longitude' => $shipment->sender_longitude,
            ],
            'destination' => [
                'address'   => $shipment->receiver_address,
                'latitude'  => $shipment->receiver_latitude,
                'longitude' => $shipment->receiver_longitude,
            ],
            'courier_location'  => $courierLocation,
            'courier_name'      => $courierName,
            'courier_phone'     => $courierPhone,
            'timeline'          => $logs->toArray(),
            'estimated_delivery' => null, // Bisa ditambahkan nanti
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
