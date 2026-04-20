<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Dashboard with statistics.
     */
    public function dashboard()
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'delivering_orders' => Order::whereIn('status', ['assigned', 'picking_up', 'delivering'])->count(),
            'completed_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'total_couriers' => Courier::count(),
            'active_couriers' => Courier::where('is_active', true)->count(),
            'verified_couriers' => Courier::where('is_verified', true)->count(),
            'unverified_couriers' => Courier::where('is_verified', false)->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('price'),
        ];

        $recentOrders = Order::with('courier.user')
            ->latest()
            ->take(5)
            ->get();

        $activeCouriers = Courier::with('user')
            ->where('is_active', true)
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'activeCouriers'));
    }

    /**
     * Courier management page.
     */
    public function couriers(Request $request)
    {
        $query = Courier::with('user');

        // Filter by verification status
        if ($request->has('filter')) {
            match ($request->filter) {
                'verified' => $query->where('is_verified', true),
                'unverified' => $query->where('is_verified', false),
                'active' => $query->where('is_active', true),
                default => null,
            };
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhere('phone', 'like', "%{$search}%");
        }

        // Labels & Titles
        $viewTitle = 'Manajemen Kurir';
        $viewSubtitle = 'Kelola data dan verifikasi kurir';

        if ($request->has('filter')) {
            match ($request->filter) {
                'verified' => [
                    $viewTitle = 'Daftar Kurir',
                    $viewSubtitle = 'Daftar kurir yang sudah terverifikasi'
                ],
                'unverified' => [
                    $viewTitle = 'Verifikasi Kurir',
                    $viewSubtitle = 'Daftar kurir baru yang menunggu verifikasi'
                ],
                'active' => [
                    $viewTitle = 'Kurir Aktif',
                    $viewSubtitle = 'Daftar kurir yang sedang bertugas/aktif'
                ],
                default => null,
            };
        }

        $couriers = $query->latest()->paginate(10);

        return view('admin.couriers', compact('couriers', 'viewTitle', 'viewSubtitle'));
    }

    /**
     * Toggle courier verification status.
     */
    public function toggleVerifyCourier(Courier $courier)
    {
        $courier->update([
            'is_verified' => !$courier->is_verified,
            'is_active' => !$courier->is_verified, // activate when verified
        ]);

        return back()->with('success', 'Status kurir berhasil diperbarui.');
    }

    /**
     * Toggle courier active status.
     */
    public function toggleActiveCourier(Courier $courier)
    {
        $courier->update(['is_active' => !$courier->is_active]);
        return back()->with('success', 'Status aktif kurir berhasil diperbarui.');
    }

    /**
     * Order management page.
     */
    public function orders(Request $request)
    {
        $query = Order::with('courier.user');

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(10);

        $statusCounts = [
            'all' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'assigned' => Order::where('status', 'assigned')->count(),
            'picking_up' => Order::where('status', 'picking_up')->count(),
            'delivering' => Order::where('status', 'delivering')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        return view('admin.orders', compact('orders', 'statusCounts'));
    }

    /**
     * Show single order detail.
     */
    public function orderDetail(Order $order)
    {
        $order->load('courier.user');
        return view('admin.order-detail', compact('order'));
    }
}
