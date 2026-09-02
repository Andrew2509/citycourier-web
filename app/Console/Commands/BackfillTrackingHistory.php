<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Models\ShipmentLog;
use Illuminate\Console\Command;

class BackfillTrackingHistory extends Command
{
    protected $signature = 'tracking:backfill';
    protected $description = 'Buat riwayat pelacakan untuk shipment yang belum punya logs';

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
        'assigned'        => 'Kurir telah ditugaskan. Langsung menjemput paket di lokasi pengirim.'
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

    private array $statusOrder = [
        'pending', 'confirmed', 'assigned', 'picking_up',
        'picked_up', 'delivering', 'delivered',
    ];

    public function handle(): int
    {
        $shipments = Shipment::withCount('logs')
            ->having('logs_count', '=', 0)
            ->get();

        $this->info("Ditemukan {$shipments->count()} shipment tanpa riwayat.");

        if ($shipments->isEmpty()) {
            $this->info('Semua shipment sudah punya riwayat. Tidak ada yang perlu dilakukan.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($shipments->count());
        $bar->start();

        foreach ($shipments as $shipment) {
            $this->createLogsForShipment($shipment);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Selesai! Riwayat pelacakan berhasil dibuat.');
        return self::SUCCESS;
    }

    private function createLogsForShipment(Shipment $shipment): void
    {
        $currentStatus = $shipment->status;
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

            $minutesOffset += match ($status) {
                'pending'    => 0,
                'confirmed'  => 30,
                'assigned'   => 60,
                'picking_up' => 90,
                'picked_up'  => 120,
                'delivering' => 150,
                'delivered'  => 180,
                default      => 10,
            };
        }
    }

    private function getStatusesUpTo(string $currentStatus): array
    {
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
