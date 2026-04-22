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
        ]);

        $shipment->update([
            'status'          => $request->status,
            'tracking_number' => $request->tracking_number,
            'notes'           => $request->notes,
        ]);

        return redirect()->route('admin.shipments.show', $shipment)
            ->with('success', 'Status pengiriman berhasil diperbarui.');
    }
}
