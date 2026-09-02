<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourierController extends Controller
{
    protected TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

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
     * Update courier live location dan simpan riwayat GPS.
     * PUT /api/courier/location
     * 
     * Request:
     * {
     *   "shipment_id": 123,
     *   "latitude": -7.2756,
     *   "longitude": 112.7378,
     *   "accuracy": 8.5
     * }
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipment_id' => 'required|exists:shipments,id',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'accuracy'    => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Validasi bahwa shipment milik kurir ini
        $shipment = \App\Models\Shipment::find($request->shipment_id);
        if (!$shipment) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment tidak ditemukan.',
            ], 404);
        }

        // Cek apakah ada order aktif untuk kurir ini dengan shipment ini
        $order = \App\Models\Order::where('courier_id', $courier->id)
            ->where('order_number', $shipment->shipment_number)
            ->whereIn('status', ['assigned', 'picking_up', 'delivering'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada pengiriman aktif untuk shipment ini.',
            ], 403);
        }

        try {
            // Simpan lokasi GPS ke courier_locations
            $location = $this->trackingService->saveCourierLocation(
                $courier->id,
                $shipment->id,
                $request->latitude,
                $request->longitude,
                $request->accuracy
            );

            return response()->json([
                'success' => true,
                'message' => 'Lokasi berhasil diperbarui.',
                'data' => [
                    'latitude'     => $location->latitude,
                    'longitude'    => $location->longitude,
                    'recorded_at'  => $location->recorded_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan lokasi: ' . $e->getMessage(),
            ], 500);
        }
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

        // Active orders (in progress for this courier)
        $activeOrders = \App\Models\Order::where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'picking_up', 'delivering'])
            ->count();

        // Total delivered shipments for this courier
        $totalShipments = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_balance' => $currentBalance,
                'today_earnings' => $todayEarnings,
                'today_orders' => $todayOrders,
                'total_earnings' => $netEarnings,
                'total_withdrawals' => $totalWithdrawals,
                'active_orders' => $activeOrders,
                'total_shipments' => $totalShipments,
            ],
        ]);
    }

    /**
     * Get courier earnings summary for the Dompet/Pendapatan screen.
     * GET /api/courier/earnings
     */
    public function earnings(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Pendapatan bersih kurir = 90% dari harga order yang selesai diantar.
        $delivered = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered');

        $totalEarnings = (clone $delivered)->sum('price') * 0.9;
        $todayEarnings = (clone $delivered)->whereDate('delivered_at', today())->sum('price') * 0.9;
        $weekEarnings = (clone $delivered)
            ->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('price') * 0.9;
        $monthEarnings = (clone $delivered)
            ->whereBetween('delivered_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('price') * 0.9;

        $todayOrders = (clone $delivered)->whereDate('delivered_at', today())->count();

        // Withdrawals
        $totalWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->where('status', 'completed')
            ->sum('amount');
        $pendingWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        // Saldo: gunakan wallet bila sudah punya saldo, jika tidak hitung dari pendapatan - penarikan.
        $wallet = \App\Models\Wallet::where('courier_id', $courier->id)->first();
        $computedBalance = $totalEarnings - $totalWithdrawals - $pendingWithdrawals;
        $balance = ($wallet && (float) $wallet->available_balance > 0)
            ? (float) $wallet->available_balance
            : max($computedBalance, 0);

        // Aktivitas terbaru (pendapatan order + penarikan dana), digabung dan diurutkan terbaru dulu.
        $recentOrders = (clone $delivered)->latest('delivered_at')->limit(50)->get();
        $recentWithdrawals = \App\Models\Withdrawal::where('courier_id', $courier->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        $activities = [];

        foreach ($recentOrders as $order) {
            $activities[] = [
                'at' => $order->delivered_at ? $order->delivered_at->format('Y-m-d H:i:s') : null,
                'type' => 'income',
                'title' => 'Pengantaran #' . $order->order_number,
                'amount' => round($order->price * 0.9, 2),
                'time' => $order->delivered_at ? $order->delivered_at->format('H:i') : '-',
                'date_label' => $this->dateLabel($order->delivered_at),
                'status' => 'Pesanan selesai',
            ];
        }

        foreach ($recentWithdrawals as $withdrawal) {
            $activities[] = [
                'at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                'type' => 'withdrawal',
                'title' => 'Penarikan Dana',
                'amount' => -1 * (float) $withdrawal->amount,
                'time' => $withdrawal->created_at->format('H:i'),
                'date_label' => $this->dateLabel($withdrawal->created_at),
                'status' => $this->withdrawalStatusLabel($withdrawal->status),
            ];
        }

        usort($activities, function ($a, $b) {
            return strcmp($b['at'] ?? '', $a['at'] ?? '');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'today' => round($todayEarnings),
                'week' => round($weekEarnings),
                'month' => round($monthEarnings),
                'total' => round($totalEarnings),
                'balance' => round($balance),
                'pending_balance' => round((float) ($wallet->pending_balance ?? 0)),
                'today_orders' => $todayOrders,
                'total_withdrawals' => round($totalWithdrawals),
                'recent_activity' => array_slice($activities, 0, 20),
            ],
        ]);
    }

    /**
     * Short human label for an activity date.
     */
    private function dateLabel($dt)
    {
        if (!$dt) return '-';
        if ($dt->isToday()) return 'HARI INI';
        if ($dt->isYesterday()) return 'KEMARIN';
        return $dt->format('d M');
    }

    /**
     * Indonesian label for withdrawal status.
     */
    private function withdrawalStatusLabel($status)
    {
        $labels = [
            'completed' => 'Berhasil',
            'approved' => 'Disetujui',
            'pending' => 'Menunggu',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];
        return $labels[$status] ?? ucfirst((string) $status);
    }

    /**
     * Get courier profile details and stats for mobile app.
     * GET /api/courier/profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        $courier = $user->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Calculate total shipments (delivered)
        $totalShipments = \App\Models\Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->count();

        // Account age in months
        $createdAt = $courier->created_at;
        $months = $createdAt ? $createdAt->diffInMonths(now()) : 0;
        $accountAge = $months > 0 ? "$months Bulan" : "Baru";

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $courier->phone ?? $user->phone,
                    'photo' => $courier->photo,
                ],
                'courier_details' => [
                    'id' => $courier->id,
                    'nik' => $courier->nik,
                    'vehicle_type' => $courier->vehicle_type,
                    'vehicle_plate' => $courier->vehicle_plate,
                    'is_verified' => $courier->is_verified,
                    'is_active' => $courier->is_active,
                ],
                'stats' => [
                    'total_shipments' => $totalShipments,
                    'account_age' => $accountAge,
                    'rating' => '4.8', // Static for now, can add rating system later
                ]
            ],
        ]);
    }
}
