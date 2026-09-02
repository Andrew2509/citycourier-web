<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShipmentController extends Controller
{
    protected TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Create a new shipment request from the Flutter app.
     * POST /api/shipments
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name'       => 'required|string|max:255',
            'customer_phone'      => 'required|string|max:20',
            'sender_name'         => 'required|string|max:255',
            'sender_phone'        => 'required|string|max:20',
            'sender_address'      => 'required|string',
            'receiver_name'       => 'required|string|max:255',
            'receiver_phone'      => 'required|string|max:20',
            'receiver_address'    => 'required|string',
            'package_weight'      => 'required|numeric|min:0.01',
            'courier_code'        => 'required|string',
            'courier_name'        => 'required|string',
            'courier_service'     => 'required|string',
            'shipping_cost'       => 'required|integer|min:0',
            'total_cost'          => 'required|integer|min:0',
            'payment_method'      => 'nullable|in:COD,TUNAI,VA,QRIS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $paymentMethod = $request->payment_method;

        // COD/TUNAI: status langsung confirmed (bayar nanti)
        // VA/QRIS: status pending (tunggu pembayaran online)
        $initialStatus = in_array($paymentMethod, ['COD', 'TUNAI']) ? 'confirmed' : 'pending';

        $shipment = Shipment::create([
            'user_id'             => $request->user()?->id,
            'customer_name'       => $request->customer_name,
            'customer_phone'      => $request->customer_phone,
            'sender_name'         => $request->sender_name,
            'sender_phone'        => $request->sender_phone,
            'sender_address'      => $request->sender_address,
            'sender_latitude'     => $request->sender_latitude,
            'sender_longitude'    => $request->sender_longitude,
            'origin_name'         => $request->origin_name,
            'origin_id'           => $request->origin_id,
            'receiver_name'       => $request->receiver_name,
            'receiver_phone'      => $request->receiver_phone,
            'receiver_address'    => $request->receiver_address,
            'receiver_latitude'   => $request->receiver_latitude,
            'receiver_longitude'  => $request->receiver_longitude,
            'destination_name'    => $request->destination_name,
            'destination_id'      => $request->destination_id,
            'package_description' => $request->package_description,
            'package_weight'      => $request->package_weight,
            'courier_code'        => $request->courier_code,
            'courier_name'        => $request->courier_name,
            'courier_service'     => $request->courier_service,
            'etd'                 => $request->etd,
            'shipping_cost'       => $request->shipping_cost,
            'insurance'           => $request->boolean('insurance'),
            'wood_packing'        => $request->boolean('wood_packing'),
            'total_cost'          => $request->total_cost,
            'notes'               => $request->notes,
            'payment_method'      => $paymentMethod,
            'status'              => $initialStatus,
        ]);

        // Riwayat pelacakan
        $desc = match ($paymentMethod) {
            'COD'   => 'Pesanan dibuat. Pembayaran Cash on Delivery (COD).',
            'TUNAI' => 'Pesanan dibuat. Pembayaran tunai saat pickup.',
            'VA'    => 'Pesanan dibuat. Menunggu pembayaran via Virtual Account.',
            'QRIS'  => 'Pesanan dibuat. Menunggu pembayaran via QRIS.',
            default => 'Pesanan berhasil dibuat.',
        };
        $this->trackingService->createStatusHistory($shipment, 'pending', null, null, null, null, $desc);

        // Jika COD/TUNAI, langsung buat riwayat confirmed juga
        if ($initialStatus === 'confirmed') {
            $this->trackingService->createStatusHistory(
                $shipment, 'confirmed', null, null, null, null,
                $paymentMethod === 'COD'
                    ? 'Pesanan dikonfirmasi. Pembayaran saat pengiriman (COD).'
                    : 'Pesanan dikonfirmasi. Pembayaran tunai saat pickup.'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Permintaan pengiriman berhasil dibuat.',
            'data'    => $shipment,
        ], 201);
    }

    /**
     * Get shipment history for the authenticated user.
     * GET /api/shipments
     */
    public function index(Request $request)
    {
        $query = Shipment::latest();

        if ($request->user()) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('customer_phone', $request->user()->phone);
            });
        }

        if ($request->has('status') && $request->status) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        $shipments = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $shipments,
        ]);
    }

    /**
     * Get details of a single shipment.
     * GET /api/shipments/{id}
     */
    public function show(Request $request, Shipment $shipment)
    {
        return response()->json([
            'success' => true,
            'data'    => $shipment,
        ]);
    }

    /**
     * Track a shipment by its number (legacy).
     * GET /api/shipments/track/{number}
     */
    public function track(Request $request, $number)
    {
        $shipment = Shipment::where('shipment_number', $number)
            ->orWhere('tracking_number', $number)
            ->with(['logs' => function ($query) {
                $query->latest();
            }])
            ->first();

        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor resi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $shipment,
        ]);
    }

    /**
     * Get detailed tracking data for customer.
     * GET /api/shipments/{tracking_number}/tracking
     */
    public function tracking(Request $request, $trackingNumber)
    {
        $trackingData = $this->trackingService->getTrackingData($trackingNumber);

        if (!$trackingData) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor resi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $trackingData,
        ]);
    }

    /**
     * Get shipment counts by status.
     * GET /api/shipments/stats
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = Shipment::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('customer_phone', $user->phone);
        });

        $stats = [
            'pending'     => (clone $query)->where('status', 'pending')->count(),
            'confirmed'   => (clone $query)->where('status', 'confirmed')->count(),
            'assigned'    => (clone $query)->where('status', 'assigned')->count(),
            'picking_up'  => (clone $query)->where('status', 'picking_up')->count(),
            'picked_up'   => (clone $query)->where('status', 'picked_up')->count(),
            'delivering'  => (clone $query)->where('status', 'delivering')->count(),
            'delivered'   => (clone $query)->where('status', 'delivered')->count(),
            'cancelled'   => (clone $query)->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * Confirm payment.
     * POST /api/shipments/{shipment}/confirm-payment
     *
     * Flow:
     * - VA/QRIS: Customer bayar online, konfirmasi saat buat order
     * - COD: Bayar tunai saat kurir mengantar (dikonfirmasi kurir)
     * - TUNAI: Bayar tunai saat pickup (dikonfirmasi kurir)
     */
    public function confirmPayment(Request $request, Shipment $shipment)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:COD,TUNAI,VA,QRIS',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $paymentMethod = $request->payment_method;

        // ── VA/QRIS: Konfirmasi di awal (saat buat order) ──
        if (in_array($paymentMethod, ['VA', 'QRIS'])) {
            if ($shipment->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesanan ini sudah diproses.',
                ], 400);
            }

            $shipment->update([
                'status' => 'confirmed',
                'payment_method' => $paymentMethod,
            ]);

            $desc = $paymentMethod === 'VA'
                ? 'Pembayaran via Virtual Account berhasil.'
                : 'Pembayaran via QRIS berhasil.';

            $this->trackingService->createStatusHistory(
                $shipment, 'confirmed', null, null, null, null, $desc
            );

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran ' . $paymentMethod . ' dikonfirmasi.',
                'data'    => $shipment->fresh(),
            ]);
        }

        // ── COD/TUNAI: Bisa dari pending (saat buat order) ──
        // Jika status pending, langsung confirmed (bayar nanti)
        // Jika status delivering/delivered, tandai pembayaran diterima
        if ($shipment->status === 'pending') {
            // Saat customer pilih COD/TUNAI di payment method screen
            $shipment->update([
                'status' => 'confirmed',
                'payment_method' => $paymentMethod,
            ]);

            $desc = $paymentMethod === 'COD'
                ? 'Pesanan dikonfirmasi. Pembayaran Cash on Delivery (COD).'
                : 'Pesanan dikonfirmasi. Pembayaran tunai saat pickup.';

            $this->trackingService->createStatusHistory(
                $shipment, 'confirmed', null, null, null, null, $desc
            );

            return response()->json([
                'success' => true,
                'message' => 'Pesanan dikonfirmasi. Pembayaran ' . $paymentMethod . ' saat pengiriman.',
                'data'    => $shipment->fresh(),
            ]);
        }

        // Jika sudah delivering/delivered, tandai pembayaran diterima
        $shipment->update([
            'payment_method' => $paymentMethod,
        ]);

        $desc = $paymentMethod === 'COD'
            ? 'Pembayaran COD berhasil diterima kurir.'
            : 'Pembayaran tunai berhasil diterima kurir.';

        $this->trackingService->createStatusHistory(
            $shipment, $shipment->status, null, null, null, null, $desc
        );

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran ' . $paymentMethod . ' berhasil.',
            'data'    => $shipment->fresh(),
        ]);
    }
}
