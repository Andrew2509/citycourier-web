<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use Illuminate\Database\Seeder;

class DemoDashboardSeeder extends Seeder
{
    /**
     * Seed data untuk Ci-Work Dashboard, disesuaikan dengan desain mockup:
     * 1 tugas aktif (delivering), 2 selesai hari ini (omzet Rp 30.000),
     * 3 order antrean pending yang siap di-auto-assign, absensi & telemetri kurir.
     */
    public function run(): void
    {
        $now = now();

        // Cleanup fixture lama (Jakarta batch `CC-20260417...`) yang statusnya aktif/tetap
        Order::where('order_number', 'like', 'CC-20260417%')
            ->whereIn('status', ['pending', 'assigned', 'picking_up', 'delivering'])
            ->update(['status' => 'cancelled']);

        // ─── Courier demo ─────────────────────────────────────────────
        $courier = Courier::firstOrCreate(
            ['id' => 6],
            ['user_id' => 1, 'is_verified' => true, 'is_active' => true]
        );

        $courier->update([
            'is_active'    => true,
            'is_verified'  => true,
            'rating_avg'   => 4.9,
            'rating_count' => 128,
            'vehicle_type' => $courier->vehicle_type ?: 'motor',
        ]);

        // ─── Absensi hari ini ─────────────────────────────────────────
        Attendance::updateOrCreate(
            ['courier_id' => 6],
            [
                'check_in_at'    => $now->copy()->setTime(6, 50),
                'status'         => 'online',
                'drop_point_name'=> 'Drop Point Bratang',
                'latitude'       => -7.29124,
                'longitude'      => 112.75921,
                'device'         => 'Android 12 (SM-A525F)',
                'note'           => 'Check-in pagi rutin (sistem demo)',
            ]
        );

        // ─── Order aktif (1, delivering) ─────────────────────────────
        $featuredSn = 'SHP-DEMO-DELIVERING';
        $featured   = $this->makeDemoOrder([
            'sn'            => $featuredSn,
            'tracking'      => 'CC260908DBC4',
            'shipment_st'   => 'delivering',
            'order_st'      => 'delivering',
            'price'         => 30000,
            'weight'        => 1.2,
            'desc'          => 'Dokumen & Makanan Ringan',
            'pickup'        => ['Reny Swalayan, Bratang Gede No. 42', -7.29124, 112.75921],
            'dest'          => ['Universitas 17 Agustus 1945 (UNTAG), Gedung Rektorat Lt. 2, Jl. Semolowaru 45', -7.3035, 112.7819],
            'courier_id'    => 6,
            'created_at'    => $now->copy()->subMinutes(45),
            'picked_up_at'  => $now->copy()->subMinutes(40),
            'delivering_at' => $now->copy()->subMinutes(7),
            'delivered_at'  => null,
        ]);

        // ─── 2 order selesai hari ini (omzet Rp 30.000) ──────────────
        $this->makeDemoOrder([
            'sn'            => 'SHP-DEMO-DONE-1',
            'tracking'      => 'CC260908DBD0',
            'shipment_st'   => 'delivered',
            'order_st'      => 'delivered',
            'price'         => 15000,
            'weight'        => 0.5,
            'desc'          => 'Dokumen penting',
            'pickup'        => ['Reny Swalayan, Bratang Gede No. 42', -7.29124, 112.75921],
            'dest'          => ['Kantor Pemasaran, Jl. Ngagel Jaya 88', -7.2981, 112.7609],
            'courier_id'    => 6,
            'created_at'    => $now->copy()->subHours(6),
            'picked_up_at'  => $now->copy()->subHours(5),
            'delivering_at' => $now->copy()->subHours(4),
            'delivered_at'  => $now->copy()->subHours(2),
        ]);

        $this->makeDemoOrder([
            'sn'            => 'SHP-DEMO-DONE-2',
            'tracking'      => 'CC260908DBD1',
            'shipment_st'   => 'delivered',
            'order_st'      => 'delivered',
            'price'         => 15000,
            'weight'        => 0.8,
            'desc'          => 'Kado ulang tahun',
            'pickup'        => ['Reny Swalayan, Bratang Gede No. 42', -7.29124, 112.75921],
            'dest'          => ['Apartemen Menur Pumpungan, Jl. Menur Pumpungan 46', -7.3003, 112.7702],
            'courier_id'    => 6,
            'created_at'    => $now->copy()->subHours(8),
            'picked_up_at'  => $now->copy()->subHours(7),
            'delivering_at' => $now->copy()->subHours(6),
            'delivered_at'  => $now->copy()->subHours(4),
        ]);

        // ─── 3 antrean pending (siap auto-assign) ────────────────────
        $this->makeDemoOrder([
            'sn'          => 'SHP-DEMO-PENDING-1',
            'tracking'    => 'CC260908DBD2',
            'shipment_st' => 'pending',
            'order_st'    => 'pending',
            'price'       => 25000,
            'weight'      => 1.0,
            'desc'        => 'Pakaian',
            'pickup'      => ['H&M Tunjungan Plaza, Jl. Tunjungan 1', -7.2608, 112.7497],
            'dest'        => ['Jl. Kertajaya Indah No. 33', -7.2891, 112.7901],
            'courier_id'  => null,
            'created_at'  => $now->copy()->subMinutes(18),
        ]);

        $this->makeDemoOrder([
            'sn'          => 'SHP-DEMO-PENDING-2',
            'tracking'    => 'CC260908DBD3',
            'shipment_st' => 'pending',
            'order_st'    => 'pending',
            'price'       => 40000,
            'weight'      => 2.5,
            'desc'        => 'Elektronik & perlengkapan',
            'pickup'      => ['Mitra 10, Jl. Mayjen Sungkono 95', -7.2872, 112.7246],
            'dest'        => ['Jl. Karang Menjangan No. 8', -7.2671, 112.7563],
            'courier_id'  => null,
            'created_at'  => $now->copy()->subMinutes(12),
        ]);

        $this->makeDemoOrder([
            'sn'          => 'SHP-DEMO-PENDING-3',
            'tracking'    => 'CC260908DBD4',
            'shipment_st' => 'pending',
            'order_st'    => 'pending',
            'price'       => 30000,
            'weight'      => 1.5,
            'desc'        => 'Makanan & minuman',
            'pickup'      => ['Richeese Factory, Jl. Ngagel Jaya 22', -7.2952, 112.7478],
            'dest'        => ['Jl. Kalijudan No. 77', -7.2787, 112.7722],
            'courier_id'  => null,
            'created_at'  => $now->copy()->subMinutes(6),
        ]);

        // ─── Telemetri kurir (sedang menuju ke tujuan) ───────────────
        $featuredShipmentId = $featured ? $featured->id : null;
        $latestLoc = CourierLocation::where('courier_id', 6)->latest('recorded_at')->first();

        $locData = [
            'courier_id'     => 6,
            'shipment_id'    => $featuredShipmentId,
            'latitude'       => -7.3000,
            'longitude'      => 112.7650, // Jl. Nginden Semolo (menuju UNTAG)
            'accuracy'       => 4,
            'speed_kmh'      => 32,
            'battery_percent'=> 84,
            'recorded_at'    => $now->copy()->subSeconds(15),
        ];

        if ($latestLoc) {
            $latestLoc->update($locData);
        } else {
            CourierLocation::create($locData);
        }

        $courier->update([
            'latitude'  => -7.3000,
            'longitude' => 112.7650,
        ]);

        // ─── Sinkronisasi settings dispatch ──────────────────────────
        Setting::set('dispatch_avg_speed_kmh', '10', 'dispatch');
        Setting::set('dispatch_coverage_radius_km', '8.5', 'dispatch');
    }

    /**
     * Buat pasangan Shipment + Order yang sinkron (idempotent).
     */
    protected function makeDemoOrder(array $d)
    {
        $shipment = Shipment::updateOrCreate(
            ['shipment_number' => $d['sn']],
            [
                'tracking_number'     => $d['tracking'],
                'customer_name'       => 'Pelanggan Demo',
                'customer_phone'      => '081234567890',
                'sender_name'         => 'Drop Point Bratang',
                'sender_phone'        => '081343323155',
                'sender_address'      => $d['pickup'][0],
                'sender_latitude'     => $d['pickup'][1],
                'sender_longitude'    => $d['pickup'][2],
                'receiver_name'       => 'Penerima Demo',
                'receiver_phone'      => '081298765432',
                'receiver_address'    => $d['dest'][0],
                'receiver_latitude'   => $d['dest'][1],
                'receiver_longitude'  => $d['dest'][2],
                'package_description' => $d['desc'],
                'package_weight'      => $d['weight'],
                'total_cost'          => $d['price'],
                'payment_method'      => 'cod',
                'status'              => $d['shipment_st'],
                'courier_code'        => 'CITY-COURIER',
                'notes'               => 'Data demo dashboard',
            ]
        );

        $order = Order::updateOrCreate(
            ['order_number' => $d['sn']],
            [
                'courier_id'          => $d['courier_id'],
                'customer_name'       => 'Pelanggan Demo',
                'customer_phone'      => '081234567890',
                'pickup_address'      => $d['pickup'][0],
                'pickup_latitude'     => $d['pickup'][1],
                'pickup_longitude'    => $d['pickup'][2],
                'delivery_address'    => $d['dest'][0],
                'delivery_latitude'   => $d['dest'][1],
                'delivery_longitude'  => $d['dest'][2],
                'package_description' => $d['desc'],
                'package_weight'      => $d['weight'],
                'price'               => $d['price'],
                'status'              => $d['order_st'],
                'notes'               => 'Data demo dashboard',
            ]
        );

        $order->update([
            'created_at'    => $d['created_at'] ?? now(),
            'picked_up_at'  => $d['picked_up_at'] ?? null,
            'delivering_at' => $d['delivering_at'] ?? null,
            'delivered_at'  => $d['delivered_at'] ?? null,
        ]);

        return $shipment;
    }
}