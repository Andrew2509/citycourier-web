<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourierController extends Controller
{
    /**
     * Get courier details and status.
     * GET /api/courier/details
     */
    public function details(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $courier->id,
                'work_status' => $courier->is_active ? 'online' : 'offline',
                'latitude' => $courier->latitude,
                'longitude' => $courier->longitude,
                'is_verified' => $courier->is_verified,
            ],
        ]);
    }

    /**
     * Update courier status (Online/Offline).
     * PUT /api/courier/status
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:online,offline,busy,on_delivery',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
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

        $isOnline = $request->status === 'online';
        
        $updateData = ['is_active' => $isOnline];
        
        if ($request->has('latitude') && $request->has('longitude')) {
            $updateData['latitude'] = $request->latitude;
            $updateData['longitude'] = $request->longitude;
        }

        $courier->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui.',
            'data' => [
                'work_status' => $courier->is_active ? 'online' : 'offline',
                'latitude' => $courier->latitude,
                'longitude' => $courier->longitude,
            ],
        ]);
    }

    /**
     * Update courier live location.
     * PUT /api/courier/location
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
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

        $courier->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lokasi berhasil diperbarui.',
        ]);
    }

    /**
     * Get courier statistics (earnings, balance, tasks).
     * GET /api/courier/stats
     */
    public function stats(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Calculate total earnings (90% of order price)
        $totalEarnings = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->sum('price');
        
        $netEarnings = $totalEarnings * 0.9;

        // Calculate total withdrawals (completed)
        $totalWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->where('status', 'completed')
            ->sum('amount');
        
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        $currentBalance = $netEarnings - $totalWithdrawals - $pendingWithdrawals;

        // Today's stats
        $todayEarnings = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->sum('price') * 0.9;

        $todayOrders = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_balance' => $currentBalance,
                'today_earnings' => $todayEarnings,
                'today_orders' => $todayOrders,
                'total_earnings' => $netEarnings,
                'total_withdrawals' => $totalWithdrawals,
            ],
        ]);
    }
}
