<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get orders for the authenticated courier.
     * GET /api/orders
     */
    public function index(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $query = Order::where('courier_id', $courier->id);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get available (pending) orders for couriers to pick.
     * GET /api/orders/available
     */
    public function available()
    {
        $orders = Order::where('status', 'pending')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Update order status.
     * PATCH /api/update-status-order
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'status' => 'required|in:assigned,picking_up,delivering,delivered,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        $order = Order::find($request->order_id);

        // If assigning a pending order
        if ($request->status === 'assigned' && $order->status === 'pending') {
            $order->update([
                'courier_id' => $courier->id,
                'status' => 'assigned',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil diambil.',
                'data' => $order->fresh(),
            ]);
        }

        // Check ownership for status updates
        if ($order->courier_id !== $courier->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke order ini.',
            ], 403);
        }

        // Update status with timestamps
        $updateData = ['status' => $request->status];

        if ($request->status === 'picking_up') {
            $updateData['picked_up_at'] = now();
            if ($request->hasFile('photo')) {
                $updateData['pickup_photo'] = $request->file('photo')->store('orders/pickup', 'public');
            }
        }

        if ($request->status === 'delivered') {
            $updateData['delivered_at'] = now();
            if ($request->hasFile('photo')) {
                $updateData['delivery_photo'] = $request->file('photo')->store('orders/delivery', 'public');
            }
        }

        $order->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui.',
            'data' => $order->fresh(),
        ]);
    }
}
