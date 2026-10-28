<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Milon\Barcode\Facades\DNS2DFacade;

class TrackingWebController extends Controller
{
    protected TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Tampilan publik Live Tracking untuk pelanggan/penerima.
     * Dapat diakses via WhatsApp link: /track/{number}
     */
    public function show(string $number)
    {
        try {
            $shipment = Shipment::where('tracking_number', $number)
                ->orWhere('shipment_number', $number)
                ->with(['logs' => function ($q) {
                    $q->latest();
                }])
                ->first();
        } catch (\Throwable $e) {
            $shipment = null;
        }

        if (!$shipment) {
            return response()->view('tracking.not-found', [
                'number' => $number,
            ], 404);
        }

        $trackingData = $this->trackingService->getTrackingData($shipment->tracking_number);

        // Status map yang seragam dengan persyaratan
        $displayStatus = match ($shipment->status) {
            'pending', 'confirmed' => 'Menunggu Kurir',
            'assigned'             => 'Kurir Ditugaskan',
            'picking_up'           => 'Kurir Menuju Pickup',
            'picked_up'            => 'Barang Sudah Diambil',
            'delivering'           => 'Dalam Perjalanan',
            'delivered'            => 'Selesai',
            'cancelled'            => 'Dibatalkan',
            default                => ucfirst($shipment->status),
        };

        $isCourierAssigned = !in_array($shipment->status, ['pending', 'confirmed', 'cancelled']) 
            && !empty($trackingData['courier_location']);

        return view('tracking.show', [
            'shipment'          => $shipment,
            'tracking'          => $trackingData,
            'displayStatus'     => $displayStatus,
            'isCourierAssigned' => $isCourierAssigned,
        ]);
    }

    /**
     * API data tracking untuk polling live (auto-refresh di halaman web).
     * GET /track/{number}/data
     */
    public function data(string $number)
    {
        try {
            $shipment = Shipment::where('tracking_number', $number)
                ->orWhere('shipment_number', $number)
                ->first();
        } catch (\Throwable $e) {
            $shipment = null;
        }

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Resi tidak ditemukan',
            ], 404);
        }

        $trackingData = $this->trackingService->getTrackingData($shipment->tracking_number);

        $displayStatus = match ($shipment->status) {
            'pending', 'confirmed' => 'Menunggu Kurir',
            'assigned'             => 'Kurir Ditugaskan',
            'picking_up'           => 'Kurir Menuju Pickup',
            'picked_up'            => 'Barang Sudah Diambil',
            'delivering'           => 'Dalam Perjalanan',
            'delivered'            => 'Selesai',
            'cancelled'            => 'Dibatalkan',
            default                => ucfirst($shipment->status),
        };

        return response()->json([
            'success'          => true,
            'status'           => $shipment->status,
            'display_status'   => $displayStatus,
            'is_assigned'      => !in_array($shipment->status, ['pending', 'confirmed', 'cancelled']),
            'tracking'         => $trackingData,
        ]);
    }

    /**
     * Menghasilkan QR Code PNG yang sama dengan nomor resi order.
     * GET /track/{number}/qrcode.png atau /shipments/{number}/qrcode.png
     */
    public function qrcode(string $number)
    {
        $shipment = Shipment::where('tracking_number', $number)
            ->orWhere('shipment_number', $number)
            ->first();

        $code = $shipment ? $shipment->tracking_number : $number;
        $base64 = DNS2DFacade::getBarcodePNG($code, 'QRCODE', 10, 10);
        $image = base64_decode($base64);

        return response($image, 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
