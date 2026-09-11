<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
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

        $recentTasks = Order::with(['courier.user', 'shipment'])
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
        $tasks = Order::with(['courier.user', 'shipment'])
            ->whereIn('status', ['assigned', 'picking_up', 'delivering'])
            ->latest()
            ->paginate(10);

        $avgDuration = (int) round(
            $tasks->filter(fn($t) => $t->delivered_at && $t->picked_up_at)
                ->map(fn($t) => $t->delivered_at->diffInMinutes($t->picked_up_at))
                ->avg() ?? 0
        );

        return view('admin.ci-work.tasks', compact('tasks', 'avgDuration'));
    }

    /**
     * Finance and Payout management.
     *
     * Data:
     * - $recaps      : Rekapitulasi keuangan per kurir (tugas selesai hari ini, omzet, komisi, bersih, saldo dompet).
     * - $withdrawals : Permintaan penarikan dana (paginated).
     * - $stats       : Ringkasan kartu statistik.
     */
    public function finance()
    {
        $commissionRate = (float) Setting::get('finance_commission_rate', 10);
        $minWithdraw    = (float) Setting::get('finance_withdrawal_minimum', 20000);
        $adminFee       = (float) Setting::get('finance_withdrawal_admin_fee', 0);

        $recaps = Courier::with(['user', 'wallet'])
            ->with(['orders' => fn($q) => $q->where('status', 'delivered')])
            ->where('is_verified', true)
            ->latest()
            ->paginate(10);

        foreach ($recaps as $courier) {
            $todayOrders = $courier->orders->filter(fn($o) => $o->delivered_at && $o->delivered_at->isToday());

            $courier->today_count  = $todayOrders->count();
            $courier->today_omzet  = (float) $todayOrders->sum('price');
            $courier->commission   = round($courier->today_omzet * $commissionRate / 100, 0);
            $courier->net_earnings = $courier->today_omzet - $courier->commission;

            $courier->balance         = $courier->wallet ? (float) $courier->wallet->available_balance : 0.0;
            $courier->pending_balance = $courier->wallet ? (float) $courier->wallet->pending_balance : 0.0;
            $courier->today_orders    = $todayOrders;
        }

        $deliveredToday = Order::where('status', 'delivered')->whereDate('delivered_at', today());
        $omzetToday     = (float) $deliveredToday->sum('price');

        $stats = [
            'omzet'              => $omzetToday,
            'delivered_today'    => $deliveredToday->count(),
            'net'                => round($omzetToday * (100 - $commissionRate) / 100, 0),
            'platform'           => round($omzetToday * $commissionRate / 100, 0),
            'commission_rate'    => $commissionRate,
            'pending_wd_count'   => Withdrawal::where('status', 'pending')->count(),
            'pending_wd_amount'  => (float) Withdrawal::where('status', 'pending')->sum('amount'),
        ];

        $withdrawals = Withdrawal::with(['courier.user', 'wallet'])
            ->latest()
            ->paginate(10, ['*'], 'withdrawals_page');

        $withdrawalCounts = [
            'pending'   => Withdrawal::where('status', 'pending')->count(),
            'completed' => Withdrawal::whereIn('status', ['approved', 'completed'])->count(),
            'rejected'  => Withdrawal::where('status', 'rejected')->count(),
        ];

        return view('admin.ci-work.finance', compact('recaps', 'withdrawals', 'stats', 'withdrawalCounts', 'minWithdraw', 'adminFee'));
    }

    /**
     * Update bagi hasil (commission rate).
     */
    public function updateCommission(Request $request)
    {
        $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:50',
        ]);

        Setting::set('finance_commission_rate', (string) $request->commission_rate, 'finance');

        return back()->with('success', 'Persentase bagi hasil berhasil diperbarui menjadi ' . (float) $request->commission_rate . '%.');
    }

    /**
     * Export laporan keuangan (CSV).
     */
    public function exportFinance()
    {
        $commissionRate = (float) Setting::get('finance_commission_rate', 10);

        $couriers = Courier::with(['user'])
            ->with(['orders' => fn($q) => $q->where('status', 'delivered')])
            ->where('is_verified', true)
            ->latest()
            ->get();

        $now     = now()->format('Y-m-d_H-i');
        $filename = 'laporan-keuangan-kurir-' . $now . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($couriers, $commissionRate) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            fputcsv($output, ['No', 'Nama Kurir', 'WhatsApp', 'Email', 'Tugas Selesai', 'Total Omzet', 'Potongan Komisi (' . $commissionRate . '%)', 'Pendapatan Bersih']);

            $totals = ['tasks' => 0, 'omzet' => 0.0, 'fee' => 0.0, 'net' => 0.0];

            foreach ($couriers->values() as $i => $courier) {
                $omzet = (float) $courier->orders->sum('price');
                $fee   = round($omzet * $commissionRate / 100, 0);
                $net   = $omzet - $fee;

                $totals['tasks'] += $courier->orders->count();
                $totals['omzet'] += $omzet;
                $totals['fee']   += $fee;
                $totals['net']   += $net;

                fputcsv($output, [
                    $i + 1,
                    $courier->user->name ?? '-',
                    $courier->user->phone ?? $courier->phone ?? '-',
                    $courier->user->email ?? '-',
                    $courier->orders->count(),
                    number_format($omzet, 0, ',', '.'),
                    number_format($fee, 0, ',', '.'),
                    number_format($net, 0, ',', '.'),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['TOTAL', '', '', '', $totals['tasks'], number_format($totals['omzet'], 0, ',', '.'), number_format($totals['fee'], 0, ',', '.'), number_format($totals['net'], 0, ',', '.')]);

            fclose($output);
        }, $filename, $headers);
    }

    /**
     * Penyesuaian saldo dompet kurir.
     */
    public function reconcile(Request $request, Courier $courier)
    {
        $request->validate([
            'type'   => 'required|in:tambah,debit',
            'amount' => 'required|numeric|gt:0',
            'note'   => 'nullable|string|max:255',
        ]);

        $amount = (float) $request->amount;

        $wallet = $courier->wallet;
        if (!$wallet) {
            $wallet = Wallet::create([
                'courier_id'        => $courier->id,
                'available_balance' => 0,
                'pending_balance'   => 0,
            ]);
        }

        $current = (float) $wallet->available_balance;
        $newBalance = $request->type === 'tambah'
            ? $current + $amount
            : max(0, $current - $amount);

        DB::transaction(function () use ($wallet, $request, $amount, $newBalance) {
            $wallet->update(['available_balance' => $newBalance]);

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'courier_id'  => $wallet->courier_id,
                'type'        => 'adjustment',
                'amount'      => $amount,
                'fee'         => 0,
                'net_amount'  => $request->type === 'tambah' ? $amount : -$amount,
                'status'      => 'success',
                'description' => ($request->type === 'tambah' ? 'Penambahan saldo' : 'Pengurangan saldo') . ' oleh admin' . ($request->note ? ' — ' . $request->note : ''),
            ]);
        });

        return back()->with('success', 'Penyesuaian saldo ' . $courier->user?->name . ' berhasil disimpan.');
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