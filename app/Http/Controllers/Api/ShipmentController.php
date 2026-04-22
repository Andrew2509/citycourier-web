<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShipmentController extends Controller
{
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $shipment = Shipment::create([
            'shipment_number'     => Shipment::generateShipmentNumber(),
            'user_id'             => $request->user()?->id,
            'customer_name'       => $request->customer_name,
            'customer_phone'      => $request->customer_phone,
            'sender_name'         => $request->sender_name,
            'sender_phone'        => $request->sender_phone,
            'sender_address'      => $request->sender_address,
            'origin_name'         => $request->origin_name,
            'origin_id'           => $request->origin_id,
            'receiver_name'       => $request->receiver_name,
            'receiver_phone'      => $request->receiver_phone,
            'receiver_address'    => $request->receiver_address,
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
            'status'              => 'pending',
        ]);

        return response()->json([
            'success'          => true,
            'message'          => 'Permintaan pengiriman berhasil dibuat.',
            'data'             => $shipment,
        ], 201);
    }

    /**
     * Get shipment history for the authenticated user.
     * GET /api/shipments
     */
    public function index(Request $request)
    {
        $query = Shipment::latest();

        // If user is authenticated, filter by their shipments
        if ($request->user()) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                  ->orWhere('customer_phone', $request->user()->phone);
            });
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
}
