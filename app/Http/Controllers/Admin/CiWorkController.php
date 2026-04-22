<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CiWorkController extends Controller
{
    /**
     * Ci-Work Operational Dashboard.
     */
    public function index()
    {
        $stats = [
            'online_couriers' => Courier::where('is_active', true)->count(),
            'active_tasks' => Order::whereIn('status', ['picking_up', 'delivering'])->count(),
            'completed_today' => Order::where('status', 'delivered')
                ->whereDate('delivered_at', today())
                ->count(),
            'total_earnings_today' => Order::where('status', 'delivered')
                ->whereDate('delivered_at', today())
                ->sum('price'),
        ];

        $recentTasks = Order::with('courier.user')
            ->whereIn('status', ['picking_up', 'delivering'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin.ci-work.index', compact('stats', 'recentTasks'));
    }

    /**
     * Courier Attendance monitoring.
     */
    public function attendance()
    {
        $couriers = Courier::with('user')
            ->where('is_verified', true)
            ->latest()
            ->paginate(10);

        return view('admin.ci-work.attendance', compact('couriers'));
    }

    /**
     * Active tasks management.
     */
    public function tasks()
    {
        $tasks = Order::with('courier.user')
            ->whereIn('status', ['assigned', 'picking_up', 'delivering'])
            ->latest()
            ->paginate(10);

        return view('admin.ci-work.tasks', compact('tasks'));
    }

    /**
     * Finance and Payout management.
     */
    public function finance()
    {
        // Calculate earnings per courier
        $earnings = Courier::with('user')
            ->withCount(['orders as completed_orders' => function($query) {
                $query->where('status', 'delivered');
            }])
            ->withSum(['orders as total_earnings' => function($query) {
                $query->where('status', 'delivered');
            }], 'price')
            ->where('is_verified', true)
            ->latest()
            ->paginate(10);

        $withdrawals = \App\Models\Withdrawal::with('courier.user')
            ->latest()
            ->paginate(10, ['*'], 'withdrawals_page');

        return view('admin.ci-work.finance', compact('earnings', 'withdrawals'));
    }

    /**
     * Update withdrawal status.
     */
    public function updateWithdrawalStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,completed',
            'admin_notes' => 'nullable|string',
        ]);

        $withdrawal = \App\Models\Withdrawal::findOrFail($id);
        
        $updateData = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ];

        if ($request->status !== 'pending') {
            $updateData['processed_at'] = now();
        }

        $withdrawal->update($updateData);

        return back()->with('success', 'Status penarikan berhasil diperbarui.');
    }
}
