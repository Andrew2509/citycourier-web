<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\RajaOngkirMatcherService;
use App\Services\ShipmentNotificationService;
use App\Services\TrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Shipment::latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('sender_name', 'like', "%{$search}%")
                  ->orWhere('receiver_name', 'like', "%{$search}%");
            });
        }

        $shipments = $query->paginate(20);

        $statusCounts = [
            'all'        => Shipment::count(),
            'pending'    => Shipment::where('status', 'pending')->count(),
            'confirmed'  => Shipment::where('status', 'confirmed')->count(),
            'assigned'   => Shipment::where('status', 'assigned')->count(),
            'picking_up' => Shipment::where('status', 'picking_up')->count(),
            'picked_up'  => Shipment::where('status', 'picked_up')->count(),
            'delivering' => Shipment::whereIn('status', ['delivering', 'in_transit'])->count(),
            'delivered'  => Shipment::where('status', 'delivered')->count(),
            'cancelled'  => Shipment::where('status', 'cancelled')->count(),
        ];

        return view('admin.shipments.index', compact('shipments', 'statusCounts'));
    }

    /**
     * Show form to create a new shipment.
     */
    public function create(RajaOngkirMatcherService $matcher)
    {
        $dropPoints = \App\Models\DropPoint::all();
        $provinces = $matcher->getProvincesList();

        return view('admin.shipments.create', compact('dropPoints', 'provinces'));
    }

    /**
     * Reverse geocode / match location against RajaOngkir region data.
     */
    public function matchRegion(Request $request, RajaOngkirMatcherService $matcher)
    {
        $result = $matcher->match($request->all());
        return response()->json($result);
    }

    /**
     * Get provinces for manual dropdown selection.
     */
    public function getProvinces(RajaOngkirMatcherService $matcher)
    {
        return response()->json([
            'success' => true,
            'data'    => $matcher->getProvincesList()
        ]);
    }

    /**
     * Get cities for manual dropdown selection.
     */
    public function getCities(Request $request, RajaOngkirMatcherService $matcher)
    {
        return response()->json([
            'success' => true,
            'data'    => $matcher->getCitiesList($request->query('province_id'))
        ]);
    }

    /**
     * Get districts (kecamatan) for manual dropdown selection.
     */
    public function getDistricts(Request $request, RajaOngkirMatcherService $matcher)
    {
        return response()->json([
            'success' => true,
            'data'    => $matcher->getDistrictsList($request->query('city_id'))
        ]);
    }

    /**
     * Store a newly created shipment.
     */
    public function store(Request $request, TrackingService $trackingService, ShipmentNotificationService $notificationService)
    {
        $validated = $request->validate([
            'sender_name'         => 'required|string|max:255',
            'sender_phone'        => 'required|string|max:20',
            'sender_address'      => 'required|string',
            'sender_latitude'     => 'nullable|numeric',
            'sender_longitude'    => 'nullable|numeric',
            'origin_id'           => 'required|string|max:50',
            'origin_name'         => 'required|string|max:255',

            'receiver_name'       => 'required|string|max:255',
            'receiver_phone'      => 'required|string|max:20',
            'receiver_address'    => 'required|string',
            'receiver_latitude'   => 'nullable|numeric',
            'receiver_longitude'  => 'nullable|numeric',
            'destination_id'      => 'required|string|max:50',
            'destination_name'    => 'required|string|max:255',

            'package_description' => 'required|string|max:255',
            'package_weight'      => 'required|numeric|min:0.01',
            'vehicle'             => 'required|in:motor,mobil',
            'courier_service'     => 'nullable|string|max:100',
            'shipping_cost'       => 'required|integer|min:0',
            'total_cost'          => 'required|integer|min:0',
            'payment_method'      => 'required|in:TUNAI,COD',
            'notes'               => 'nullable|string',
        ]);

        $vehicle = $validated['vehicle'];
        $courierCode = $vehicle === 'mobil' ? 'MOBIL' : 'MOTOR';
        $courierName = 'City Courier ' . ucfirst($vehicle);
        $courierService = $validated['courier_service'] ?: 'Instant';

        $shipment = Shipment::create([
            'user_id'             => auth()->id(),
            'customer_name'       => $validated['sender_name'],
            'customer_phone'      => $validated['sender_phone'],
            'sender_name'         => $validated['sender_name'],
            'sender_phone'        => $validated['sender_phone'],
            'sender_address'      => $validated['sender_address'],
            'sender_latitude'     => $validated['sender_latitude'] ?? null,
            'sender_longitude'    => $validated['sender_longitude'] ?? null,
            'origin_name'         => $validated['origin_name'],
            'origin_id'           => $validated['origin_id'],

            'receiver_name'       => $validated['receiver_name'],
            'receiver_phone'      => $validated['receiver_phone'],
            'receiver_address'    => $validated['receiver_address'],
            'receiver_latitude'   => $validated['receiver_latitude'] ?? null,
            'receiver_longitude'  => $validated['receiver_longitude'] ?? null,
            'destination_name'    => $validated['destination_name'],
            'destination_id'      => $validated['destination_id'],

            'package_description' => $validated['package_description'],
            'package_weight'      => $validated['package_weight'],
            'vehicle'             => $vehicle,
            'courier_code'        => $courierCode,
            'courier_name'        => $courierName,
            'courier_service'     => $courierService,
            'shipping_cost'       => $validated['shipping_cost'],
            'total_cost'          => $validated['total_cost'],
            'payment_method'      => $validated['payment_method'],
            'status'              => 'confirmed', // Admin creation is confirmed and ready for pickup
            'notes'               => $validated['notes'] ?? null,
        ]);

        // Create tracking history
        $trackingService->createStatusHistory(
            $shipment,
            'confirmed',
            null,
            isset($validated['sender_latitude']) ? (float)$validated['sender_latitude'] : null,
            isset($validated['sender_longitude']) ? (float)$validated['sender_longitude'] : null,
            null,
            'Pesanan pengiriman baru dibuat oleh Admin City Courier. Menunggu penugasan kurir.'
        );

        // Optionally send WhatsApp notification
        try {
            $notificationService->sendOrderSuccessNotification($shipment);
        } catch (\Exception $e) {
            \Log::warning('Failed auto-sending WA from admin create shipment: ' . $e->getMessage());
        }

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', "Pengiriman baru berhasil dibuat dengan nomor resi {$shipment->tracking_number}.");
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['order.courier.user', 'order.lastCancelledCourier.user', 'order.rejections.courier.user', 'logs']);
        return view('admin.shipments.show', compact('shipment'));
    }

    public function update(Request $request, Shipment $shipment)
    {
        $request->validate([
            'status'              => 'required|in:pending,confirmed,assigned,picking_up,picked_up,in_transit,delivering,delivered,cancelled',
            'tracking_number'     => 'nullable|string|max:100',
            'notes'               => 'nullable|string',
            'location'            => 'nullable|string|max:255',
            'log_description'     => 'nullable|string|max:255',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $oldStatus = $shipment->status;
        $newStatus = in_array($request->status, ['in_transit', 'delivering']) ? 'delivering' : $request->status;
        $now = Carbon::now('Asia/Jakarta');

        $shipmentUpdate = [
            'status'          => $newStatus,
            'tracking_number' => $request->tracking_number ?? $shipment->tracking_number,
            'notes'           => $request->notes,
        ];

        $cancelReason = null;
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $cancelReason = trim((string) ($request->cancellation_reason ?: ($request->log_description ?: ($request->notes ?: 'Dibatalkan oleh Admin City Courier'))));
            $shipmentUpdate['cancelled_at'] = $now;
            $shipmentUpdate['cancelled_by'] = 'admin';
            $shipmentUpdate['cancellation_reason'] = $cancelReason;
            $shipmentUpdate['cancelled_from_status'] = $oldStatus;
        }

        $shipment->update($shipmentUpdate);

        // Sinkronisasi ke Order jika ada
        $order = \App\Models\Order::where('order_number', $shipment->shipment_number)->first();
        if ($order) {
            $orderUpdate = ['status' => $newStatus];
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $orderUpdate['cancelled_at'] = $now;
                $orderUpdate['cancelled_by'] = 'admin';
                $orderUpdate['cancellation_reason'] = $cancelReason;
                $orderUpdate['cancelled_from_status'] = $order->status;
            }
            $order->update($orderUpdate);
        }

        // Auto log status change
        if ($oldStatus !== $newStatus) {
            $statusLabels = [
                'pending'    => 'Pesanan Dibuat',
                'confirmed'  => 'Pesanan Dikonfirmasi',
                'assigned'   => 'Kurir Ditugaskan',
                'picking_up' => 'Menjemput Paket',
                'picked_up'  => 'Paket Telah Dijemput',
                'delivering' => 'Paket Dalam Perjalanan',
                'delivered'  => 'Paket Telah Diterima',
                'cancelled'  => 'Pesanan Dibatalkan',
            ];

            $defaultLogDesc = $newStatus === 'cancelled'
                ? "Pesanan dibatalkan oleh Admin City Courier (status sebelumnya: {$oldStatus}). Alasan: {$cancelReason}"
                : "Status diperbarui menjadi " . ($statusLabels[$newStatus] ?? $newStatus);

            $shipment->logs()->create([
                'status'      => $newStatus,
                'location'    => $request->location ?? ($newStatus === 'cancelled' ? 'Admin Panel' : 'Lokasi Pickup'),
                'description' => $request->log_description ?: $defaultLogDesc,
            ]);
        }

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', 'Status pengiriman berhasil diperbarui.');
    }

    public function addLog(Request $request, Shipment $shipment)
    {
        $request->validate([
            'status'      => 'required|string',
            'location'    => 'required|string',
            'description' => 'required|string',
        ]);

        $shipment->logs()->create($request->all());

        return redirect()->back()->with('success', 'Riwayat pelacakan berhasil ditambahkan.');
    }

    public function destroy(Shipment $shipment)
    {
        $shipment->delete();
        return redirect()->route('admin.shipments.index')
            ->with('success', 'Data pengiriman berhasil dihapus.');
    }

    public function destroyAll()
    {
        Shipment::query()->delete();
        return redirect()->route('admin.shipments.index')
            ->with('success', 'Semua data pengiriman berhasil dihapus.');
    }

    public function sendWhatsapp(Request $request, Shipment $shipment, \App\Services\ShipmentNotificationService $notificationService)
    {
        $result = $notificationService->sendOrderSuccessNotification($shipment, true);

        if (!empty($result['success'])) {
            $targetPhone = $shipment->sender_phone ?: $shipment->customer_phone;
            return redirect()->back()->with('success', 'Notifikasi WhatsApp berhasil dikirim ke pengirim (' . $targetPhone . ').');
        }

        return redirect()->back()->with('error', 'Gagal mengirim WhatsApp: ' . ($result['message'] ?? 'Terjadi kesalahan.'));
    }
}
