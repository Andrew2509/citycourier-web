<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Log;

class ShipmentNotificationService
{
    protected WhatsAppManager $whatsapp;

    public function __construct(WhatsAppManager $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Kirim notifikasi WhatsApp otomatis saat Pesanan Berhasil dibuat.
     * Menggunakan QR Code sebagai media jika Fonnte mendukung, dengan fallback otomatis ke teks.
     * Dilengkapi perlindungan deduplikasi (idempotent).
     */
    public function sendOrderSuccessNotification(Shipment $shipment, bool $force = false): array
    {
        // 1. Pencegahan pesan berulang
        if (!$force && $shipment->whatsapp_notification === 'sent') {
            Log::info("WhatsApp notification already sent for shipment {$shipment->tracking_number}, skipping.");
            return [
                'success' => true,
                'already_sent' => true,
                'message' => 'Notifikasi WhatsApp sudah pernah dikirim sebelumnya.',
            ];
        }

        $phone = $shipment->sender_phone ?: $shipment->customer_phone;
        if (empty($phone)) {
            Log::warning("Cannot send WhatsApp for shipment {$shipment->tracking_number}: sender_phone is empty.");
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp pengirim tidak tersedia.',
            ];
        }

        // 2. Format data aktual dari database
        $customerName   = $shipment->customer_name ?: ($shipment->sender_name ?: 'Pelanggan');
        $trackingNumber = $shipment->tracking_number ?: $shipment->shipment_number;

        $originCity = $shipment->origin_name ?: 'Sidoarjo';
        $destCity   = $shipment->destination_name ?: 'Surabaya';
        $senderInfo = ($shipment->sender_name ?: 'Pengirim') . ' - ' . $originCity;
        $receiverInfo = ($shipment->receiver_name ?: 'Penerima') . ' - ' . $destCity;

        $vehicle = $shipment->vehicle_display_name;
        $schedulePickup = $shipment->schedule_pickup_text;
        $cost = $shipment->formatted_total_cost;
        $trackingUrl = $shipment->tracking_url;
        $qrCodeUrl = $shipment->qr_code_url;

        // 3. Susun template pesan sesuai spesifikasi
        $message = "Halo, {$customerName} 👋\n\n"
            . "Pesanan City Courier kamu berhasil dibuat! 🎉\n\n"
            . "📦 NOMOR RESI\n"
            . "{$trackingNumber}\n\n"
            . "📍 PENGIRIM\n"
            . "{$senderInfo}\n\n"
            . "📍 PENERIMA\n"
            . "{$receiverInfo}\n\n"
            . "🚚 KENDARAAN\n"
            . "{$vehicle}\n\n"
            . "📅 JADWAL PICKUP\n"
            . "{$schedulePickup}\n\n"
            . "💰 BIAYA PENGIRIMAN\n"
            . "{$cost}\n\n"
            . "📦 STATUS\n"
            . "Menunggu Kurir\n\n"
            . "Kamu dapat memantau pesanan melalui Live Tracking.\n\n"
            . "🔗 Live Tracking:\n"
            . "{$trackingUrl}\n\n"
            . "Terima kasih telah menggunakan City Courier.";

        try {
            // Kirim pesan via WhatsAppManager (Fonnte mendukung $qrCodeUrl sebagai media/caption)
            $result = $this->whatsapp->sendMessage($phone, $message, $qrCodeUrl);

            if (!empty($result['success'])) {
                $shipment->update([
                    'whatsapp_notification' => 'sent',
                    'whatsapp_sent_at'      => now(),
                ]);

                Log::info("WhatsApp order success notification sent for resi {$trackingNumber} to {$phone}");

                return [
                    'success' => true,
                    'message' => 'Notifikasi WhatsApp berhasil dikirim.',
                    'data'    => $result,
                ];
            }

            // Gagal dari provider (token habis, nomor tidak valid, dsb)
            $shipment->update([
                'whatsapp_notification' => 'failed',
            ]);

            $errMsg = $result['message'] ?? 'Provider WhatsApp error';
            Log::error("Fonnte/WhatsApp error for shipment {$trackingNumber}: {$errMsg}");

            return [
                'success' => false,
                'message' => $errMsg,
            ];
        } catch (\Throwable $e) {
            // Order tetap berhasil, kegagalan WhatsApp dicatat di log
            $shipment->update([
                'whatsapp_notification' => 'failed',
            ]);

            Log::error("Exception sending WhatsApp for shipment {$trackingNumber}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengirim WhatsApp: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim notifikasi saat kurir menerima pesanan (status: assigned / picking_up).
     */
    public function sendCourierAssignedNotification(Shipment $shipment): array
    {
        $phone = $shipment->sender_phone ?: $shipment->customer_phone;
        if (empty($phone)) return ['success' => false, 'message' => 'No phone'];

        $trackingNumber = $shipment->tracking_number ?: $shipment->shipment_number;
        $trackingUrl    = $shipment->tracking_url;

        $message = "🚚 Kurir telah menerima pesanan kamu.\n\n"
            . "Nomor Resi:\n"
            . "{$trackingNumber}\n\n"
            . "Kurir sedang menuju lokasi pickup.\n\n"
            . "🔗 Live Tracking:\n"
            . "{$trackingUrl}";

        try {
            return $this->whatsapp->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::error("Error courier assigned notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Kirim notifikasi saat paket sudah diambil (status: picked_up / delivering).
     */
    public function sendPickedUpNotification(Shipment $shipment): array
    {
        $phone = $shipment->sender_phone ?: $shipment->customer_phone;
        if (empty($phone)) return ['success' => false, 'message' => 'No phone'];

        $trackingNumber = $shipment->tracking_number ?: $shipment->shipment_number;
        $trackingUrl    = $shipment->tracking_url;

        $message = "📦 Barang kamu sudah berhasil diambil oleh kurir.\n\n"
            . "Nomor Resi:\n"
            . "{$trackingNumber}\n\n"
            . "Status:\n"
            . "Dalam Perjalanan\n\n"
            . "🔗 Live Tracking:\n"
            . "{$trackingUrl}";

        try {
            return $this->whatsapp->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::error("Error picked up notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Kirim notifikasi saat pesanan selesai (status: delivered).
     */
    public function sendDeliveredNotification(Shipment $shipment): array
    {
        $phone = $shipment->sender_phone ?: $shipment->customer_phone;
        if (empty($phone)) return ['success' => false, 'message' => 'No phone'];

        $trackingNumber = $shipment->tracking_number ?: $shipment->shipment_number;

        $message = "✅ Pesanan telah selesai.\n\n"
            . "Nomor Resi:\n"
            . "{$trackingNumber}\n\n"
            . "Terima kasih telah menggunakan City Courier! 🙏";

        try {
            return $this->whatsapp->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::error("Error delivered notification: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

