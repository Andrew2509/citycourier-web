<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
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
            'picked_up'  => Shipment::where('status', 'picked_up')->count(),
            'in_transit' => Shipment::where('status', 'in_transit')->count(),
            'delivered'  => Shipment::where('status', 'delivered')->count(),
            'cancelled'  => Shipment::where('status', 'cancelled')->count(),
        ];

        return view('admin.shipments.index', compact('shipments', 'statusCounts'));
    }

    public function show(Shipment $shipment)
    {
        return view('admin.shipments.show', compact('shipment'));
    }

    public function update(Request $request, Shipment $shipment)
    {
        $request->validate([
            'status'          => 'required|in:pending,confirmed,picked_up,in_transit,delivered,cancelled',
            'tracking_number' => 'nullable|string|max:100',
            'notes'           => 'nullable|string',
            'location'        => 'nullable|string|max:255',
            'log_description' => 'nullable|string|max:255',
        ]);

        $oldStatus = $shipment->status;
        
        $shipment->update([
            'status'          => $request->status,
            'tracking_number' => $request->tracking_number,
            'notes'           => $request->notes,
        ]);

        // Auto log status change
        if ($oldStatus !== $request->status) {
            $statusLabels = [
                'pending'    => 'Menunggu',
                'confirmed'  => 'Dikonfirmasi',
                'picked_up'  => 'Paket Diambil',
                'in_transit' => 'Dalam Perjalanan',
                'delivered'  => 'Terkirim',
                'cancelled'  => 'Dibatalkan',
            ];

            $shipment->logs()->create([
                'status'      => $request->status,
                'location'    => $request->location ?? 'Gudang Pusat',
                'description' => $request->log_description ?? "Status diperbarui menjadi " . ($statusLabels[$request->status] ?? $request->status),
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
}
