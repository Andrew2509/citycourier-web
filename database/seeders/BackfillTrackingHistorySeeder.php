<?php

namespace Database\Seeders;

use App\Models\Shipment;
use App\Models\ShipmentLog;
use Illuminate\Database\Seeder;

class BackfillTrackingHistorySeeder extends Seeder
{
    /**
     * Status labels untuk riwayat pelacakan
     */
    private array $statusLabels = [
        'pending'         => 'Pesanan Dibuat',
        'confirmed'       => 'Pesanan Dikonfirmasi',
        'assigned'        => 'Kurir Ditugaskan',
        'picking_up'      => 'Kurir Menuju Lokasi Pickup',
        'picked_up'       => 'Paket Telah Dijemput',
        'delivering'      => 'Paket Dalam Perjalanan',
        'delivered'       => 'Paket Telah Diterima',
        'cancelled'       => 'Pesanan Dibatalkan',
    ];

    private array $statusDescriptions = [
        'pending'         => 'Pesanan baru dibuat dan menunggu pembayaran.',
        'confirmed'       => 'Pembayaran telah dikonfirmasi.',
        'assigned'        => 'Kurir telah ditugaskan. Langsung menjemput paket di lokasi pengirim.',
        'picking_up'      => 'Kurir sedang menuju lokasi pengambilan paket.',
        'picked_up'       => 'Paket berhasil dijemput dari lokasi pengirim.',
        'delivering'      => 'Paket sedang dalam perjalanan ke lokasi penerima.',
        'delivered'       => 'Paket telah berhasil diterima oleh penerima.',
        'cancelled'       => 'Pengiriman dibatalkan.',
    ];

    private array $statusLocations = [
        'pending'         => 'Sistem',
        'confirmed'       => 'Sistem',
        'assigned'        => 'Menuju Lokasi Pickup',
        'picking_up'      => 'Menuju Lokasi Pickup',
        'picked_up'       => 'Lokasi Pickup',
        'delivering'      => 'Dalam Perjalanan',
        'delivered'       => 'Lokasi Tujuan',
        'cancelled'       => 'Dibatalkan',
    ];

    /**
     * Urutan status dari awal sampai akhir
     */
    private array $statusOrder = [
        'pending',
        'confirmed',
        'assigned',
        'picking_up',
        'picked_up',
        'delivering',
        'delivered',
    ];

    public function run(): void
    {
        $shipments = Shipment::withCount('logs')
            ->having('logs_count', '=', 0)
            ->get();

        $this->command->info("Membuat riwayat untuk {$shipments->count()} shipment...");

        foreach ($shipments as $shipment) {
            $this->createLogsForShipment($shipment);
        }

        $this->command->info('Selesai!');
    }

    private function createLogsForShipment(Shipment $shipment): void
    {
        $currentStatus = $shipment->status;
        
        // Tentukan status mana saja yang perlu dibuat log-nya
        $statusesToCreate = $this->getStatusesUpTo($currentStatus);

        $createdAt = $shipment->created_at;
        $minutesOffset = 0;

        foreach ($statusesToCreate as $status) {
            $timestamp = $createdAt->copy()->addMinutes($minutesOffset);

            ShipmentLog::create([
                'shipment_id' => $shipment->id,
                'status'      => $status,
                'location'    => $this->statusLocations[$status] ?? 'Sistem',
                'description' => $this->statusDescriptions[$status] ?? "Status: {$status}",
                'latitude'    => $shipment->sender_latitude,
                'longitude'   => $shipment->sender_longitude,
                'created_at'  => $timestamp,
                'updated_at'  => $timestamp,
            ]);

            // Tambah delay antar status untuk realistis
            $offsets = [
                'pending'    => 0,
                'confirmed'  => 30,
                'assigned'   => 60,
                'picking_up' => 90,
                'picked_up'  => 120,
                'delivering' => 150,
                'delivered'  => 180,
            ];
            $minutesOffset += $offsets[$status] ?? 10;
        }
    }

    /**
     * Dapatkan semua status dari awal sampai status saat ini
     */
    private function getStatusesUpTo(string $currentStatus): array
    {
        // Jika status cancelled, buat pending + cancelled
        if ($currentStatus === 'cancelled') {
            return ['pending', 'cancelled'];
        }

        $statuses = [];
        foreach ($this->statusOrder as $status) {
            $statuses[] = $status;
            if ($status === $currentStatus) {
                break;
            }
        }

        return $statuses;
    }
}
