<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\DropPoint;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Attendance;
use App\Models\CourierLocation;
use App\Services\TrackingService;

class CiWorkController extends Controller
{
    protected TrackingService $tracking;

    public function __construct(TrackingService $tracking)
    {
        $this->tracking = $tracking;
    }

    /**
     * Ci-Work Operational Dashboard.
     */
    public function index()
    {
        $data = $this->dashboardData();

        return view('admin.ci-work.index', $data);
    }

    /**
     * Build the full Ci-Work dashboard payload (stats + tasks + queue).
     */
    protected function dashboardData(): array
    {
        $commissionRate = (float) Setting::get('finance_commission_rate', 10);
        $avgSpeed       = (float) Setting::get('dispatch_avg_speed_kmh', 10);
        $radius         = (float) Setting::get('dispatch_coverage_radius_km', 8.5);
        $dropPointName  = Setting::get('attendance_drop_point', 'Drop Point Bratang');

        $couriersActive = Courier::with('user')->where('is_active', true)->get();
        $attendedToday  = Attendance::whereDate('check_in_at', today())->distinct()->count('courier_id');

        $deliveredToday = Order::where('status', 'delivered')->whereDate('delivered_at', today())->get();

        $activeOrders  = Order::with(['courier.user', 'shipment'])
            ->whereIn('status', ['assigned', 'picking_up', 'delivering'])
            ->latest()
            ->get();

        $pendingOrders = Order::with('shipment')->where('status', 'pending')->latest()->get();

        // Latest telemetry per active courier
        $locations = CourierLocation::whereIn('courier_id', $couriersActive->pluck('id')->all())
            ->latest('recorded_at')
            ->get()
            ->groupBy('courier_id')
            ->map->first();

        $batteries  = $locations->filter(fn ($l) => $l->battery_percent !== null)->pluck('battery_percent');
        $avgBattery = $batteries->isNotEmpty() ? (int) round($batteries->avg()) : null;

        $motorCount = $couriersActive->filter(fn ($c) => strtolower($c->vehicle_type ?? '') === 'motor')->count();
        $totalActive = $couriersActive->count();
        $motorPct    = $totalActive ? (int) round($motorCount / $totalActive * 100) : 0;

        $weights     = $activeOrders->map(fn ($o) => (float) $o->package_weight)->filter(fn ($w) => $w > 0);
        $capacityPct = $weights->isNotEmpty() ? (int) max(0, min(100, round($weights->avg() / 15 * 100))) : 0;

        $tasks = $activeOrders->map(fn ($o) => $this->enrichTask($o, $locations[$o->courier_id] ?? null, $avgSpeed))->values();

        $featured   = $tasks->first();
        $extraTasks = $tasks->slice(1)->values();

        $earningsToday = (float) $deliveredToday->sum('price');

        $stats = [
            'online_couriers'      => $totalActive,
            'standby_count'        => $attendedToday,
            'active_tasks'         => $tasks->count(),
            'completed_today'      => $deliveredToday->count(),
            'total_earnings_today' => $earningsToday,
            'net_earnings'         => round($earningsToday * (100 - $commissionRate) / 100, 0),
            'commission_rate'      => $commissionRate,
            'on_time_pct'          => 100,
            'pending_count'        => $pendingOrders->count(),
            'pending_amount'       => (float) $pendingOrders->sum('price'),
            'avg_battery'          => $avgBattery,
            'motor_pct'            => $motorPct,
            'motor_units'          => $motorCount,
            'delivery_units'       => $totalActive - $motorCount,
            'capacity_pct'         => $capacityPct,
            'attended_today'       => $attendedToday,
            'unverified_count'     => Courier::where('is_verified', false)->count(),
            'drop_point'           => $dropPointName,
            'coverage_radius_km'   => $radius,
            'featured_eta'         => $featured ? $featured['eta'] : null,
        ];

        $queue = $pendingOrders->map(function ($o) {
            return [
                'id'         => $o->id,
                'tracking'   => $o->tracking_number,
                'desc'       => $o->package_description,
                'weight'     => (float) $o->package_weight,
                'price'      => (float) $o->price,
                'pickup_addr'=> $o->pickup_address,
                'dest_addr'  => $o->delivery_address,
                'created_at' => $o->created_at?->diffForHumans(),
            ];
        })->values();

        return compact('stats', 'featured', 'extraTasks', 'queue', 'couriersActive');
    }

    /**
     * Flatten an active order into a render-ready task payload.
     */
    protected function enrichTask(Order $task, ?CourierLocation $loc, float $avgSpeed): array
    {
        $courier = $task->courier;
        $user    = $courier?->user;

        $clat = $loc?->latitude ?? $courier?->latitude;
        $clng = $loc?->longitude ?? $courier?->longitude;

        $fill = function ($a, $b) {
            return $a ?? $b;
        };
        $pl  = $task->pickup_latitude ?? $task->shipment?->sender_latitude;
        $pg  = $task->pickup_longitude ?? $task->shipment?->sender_longitude;
        $dlat = $task->delivery_latitude ?? $task->shipment?->receiver_latitude;
        $dlng = $task->delivery_longitude ?? $task->shipment?->receiver_longitude;

        $dlatF = $dlat !== null && $dlat !== '' ? (float) $dlat : null;
        $dlngF = $dlng !== null && $dlng !== '' ? (float) $dlng : null;
        $clatF = $clat !== null && $clat !== '' ? (float) $clat : null;
        $clngF = $clng !== null && $clng !== '' ? (float) $clng : null;

        $distanceKm = null;
        $eta        = null;
        if ($clatF !== null && $clngF !== null && $dlatF !== null && $dlngF !== null) {
            $distanceKm = round($this->haversineKm($clatF, $clngF, $dlatF, $dlngF), 1);
            $eta        = $avgSpeed > 0 ? (int) max(1, round($distanceKm / $avgSpeed * 60)) : null;
        }

        $lastSeen = $loc && $loc->recorded_at ? (int) max(0, $loc->recorded_at->diffInSeconds(now())) : null;
        $phone    = $courier?->phone ?: $user?->phone ?: '';

        $wa = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
        if ($wa) {
            $wa = str_starts_with($wa, '0') ? '62' . substr($wa, 1) : $wa;
            $wa = str_starts_with($wa, '62') ? $wa : '62' . $wa;
        }

        $photo = null;
        if ($courier && $courier->photo && is_file(public_path('storage/' . $courier->photo))) {
            $photo = url('storage/' . $courier->photo);
        }
        if (! $photo && $user?->avatar) {
            $photo = $user->photo_url;
        }

        $statusLabels = [
            'assigned'   => 'Ditugaskan',
            'picking_up' => 'Sedang Jemput',
            'delivering' => 'Dalam Pengantaran',
        ];

        return [
            'id'             => $task->id,
            'tracking'       => $task->tracking_number,
            'status'         => $task->status,
            'status_label'   => $statusLabels[$task->status] ?? ucfirst($task->status),
            'price'          => (float) $task->price,
            'weight'         => (float) $task->package_weight,
            'desc'           => $task->package_description,
            'pickup_addr'    => $fill($task->pickup_address, $task->shipment?->sender_address),
            'dest_addr'      => $fill($task->delivery_address, $task->shipment?->receiver_address),
            'dest_lat'       => $dlatF,
            'dest_lng'       => $dlngF,
            'courier_lat'    => $clatF,
            'courier_lng'    => $clngF,
            'accuracy'       => $loc && $loc->accuracy !== null ? (float) $loc->accuracy : null,
            'last_seen'      => $lastSeen,
            'speed'          => $loc && $loc->speed_kmh !== null ? (float) $loc->speed_kmh : null,
            'courier_name'   => $user?->name ?: 'Kurir',
            'courier_initial'=> strtoupper(substr($user?->name ?: 'K', 0, 1)),
            'courier_photo'  => $photo,
            'vehicle'        => $courier ? ucfirst($courier->vehicle_type ?: 'Motor') : 'Motor',
            'plate'          => $courier?->vehicle_plate,
            'phone'          => $phone,
            'wa'             => $wa,
            'rating_avg'     => $courier && $courier->rating_avg !== null ? (float) $courier->rating_avg : null,
            'rating_count'   => (int) ($courier?->rating_count ?? 0),
            'distance'       => $distanceKm,
            'eta'            => $eta,
            'target'         => $eta ? now()->addMinutes($eta)->format('H:i') : null,
            'pickup_time'    => $task->status === 'delivering' && $task->picked_up_at ? $task->picked_up_at->format('H:i') : null,
        ];
    }

    /**
     * Haversine distance in kilometres.
     */
    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r     = 6371.0;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);
        $a     = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Polling endpoint: JSON stats + rendered realtime zone (featured task + queue).
     */
    public function refreshTasks()
    {
        $data = $this->dashboardData();

        return response()->json([
            'stats'      => $data['stats'],
            'html'       => view('admin.ci-work.partials.active-tasks', [
                'stats'       => $data['stats'],
                'featured'    => $data['featured'],
                'extraTasks'  => $data['extraTasks'],
                'queue'       => $data['queue'],
            ])->render(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Assign every pending order to the nearest available courier.
     */
    public function autoAssign(Request $request)
    {
        $pending = Order::with('shipment')->where('status', 'pending')->get();

        if ($pending->isEmpty()) {
            return response()->json([
                'ok'       => true,
                'assigned' => 0,
                'message'  => 'Tidak ada order tertunda. Semua tugas telah dialokasikan.',
            ]);
        }

        $available = Courier::with('user')->where('is_active', true)->get();

        if ($available->isEmpty()) {
            return response()->json([
                'ok'       => false,
                'assigned' => 0,
                'message'  => 'Tidak ada kurir aktif yang tersedia untuk auto-assign.',
            ]);
        }

        $assigned = 0;

        DB::transaction(function () use ($pending, $available, &$assigned) {
            foreach ($pending as $order) {
                $courier = $this->nearestCourier($order, $available);

                $order->update(['courier_id' => $courier->id, 'status' => 'assigned']);

                if ($order->shipment) {
                    $order->shipment->update(['status' => 'assigned']);
                    $this->tracking->createStatusHistory(
                        $order->shipment,
                        'assigned',
                        $courier->id,
                        $courier->latitude ? (float) $courier->latitude : null,
                        $courier->longitude ? (float) $courier->longitude : null
                    );
                }

                $assigned++;
            }
        });

        return response()->json([
            'ok'       => true,
            'assigned' => $assigned,
            'message'  => "Auto-assign berhasil: {$assigned} order ditetapkan ke kurir terdekat.",
        ]);
    }

    /**
     * Assign a single pending order to the nearest available courier.
     */
    public function assignQueueOrder(Request $request, Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'ok'      => false,
                'message' => "Order #{$order->tracking_number} sudah bukan berstatus pending.",
            ]);
        }

        $available = Courier::with('user')->where('is_active', true)->get();

        if ($available->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tidak ada kurir aktif yang tersedia.',
            ]);
        }

        $courier = $this->nearestCourier($order, $available);

        DB::transaction(function () use ($order, $courier) {
            $order->update(['courier_id' => $courier->id, 'status' => 'assigned']);

            if ($order->shipment) {
                $order->shipment->update(['status' => 'assigned']);
                $this->tracking->createStatusHistory(
                    $order->shipment,
                    'assigned',
                    $courier->id,
                    $courier->latitude ? (float) $courier->latitude : null,
                    $courier->longitude ? (float) $courier->longitude : null
                );
            }
        });

        return response()->json([
            'ok'      => true,
            'message' => "Order #{$order->tracking_number} ditetapkan ke " . ($courier->user?->name ?? 'kurir') . '.',
        ]);
    }

    /**
     * Pick the nearest courier to the order pickup point.
     */
    protected function nearestCourier(Order $order, $couriers)
    {
        if ($couriers->count() === 1) {
            return $couriers->first();
        }

        $pl = $order->pickup_latitude !== null && $order->pickup_latitude !== '' ? (float) $order->pickup_latitude : null;
        $pg = $order->pickup_longitude !== null && $order->pickup_longitude !== '' ? (float) $order->pickup_longitude : null;

        if ($pl === null || $pg === null) {
            return $couriers->first();
        }

        $best  = $couriers->first();
        $bestD = PHP_FLOAT_MAX;

        foreach ($couriers as $c) {
            if (! $c->latitude || ! $c->longitude) {
                continue;
            }
            $d = $this->haversineKm((float) $c->latitude, (float) $c->longitude, $pl, $pg);
            if ($d < $bestD) {
                $bestD = $d;
                $best  = $c;
            }
        }

        return $best;
    }

    /**
     * Courier Attendance monitoring & live tracking.
     */
    public function attendance(Request $request)
    {
        $date      = $request->query('date') ? \Carbon\Carbon::parse($request->query('date'))->toDateString() : today()->toDateString();
        $dateLabel = \Carbon\Carbon::parse($date)->translatedFormat('d M Y');
        $cutoff    = Setting::get('attendance_shift_cutoff', '08:00');
        $dropPoint = Setting::get('attendance_drop_point', 'Drop Point Bratang');

        $couriers = Courier::with([
            'user',
            'attendance'  => fn ($q) => $q->whereDate('check_in_at', $date)->latest(),
            'locations'   => fn ($q) => $q->latest('recorded_at')->take(1),
        ])
            ->with(['orders' => fn ($q) => $q->whereIn('status', ['assigned', 'picking_up', 'delivering'])])
            ->paginate(10);

        $cutoffCarbon = $cutoff ? \Carbon\Carbon::createFromFormat('H:i', $cutoff) : null;

        $chipCounts = ['semua' => 0, 'online' => 0, 'delivering' => 0, 'break' => 0, 'offline' => 0];
        $onTime     = 0;

        foreach ($couriers as $courier) {
            $att = $courier->attendance->first();
            $loc = $courier->locations->first();
            $delivering = $courier->orders->isNotEmpty();

            $status = 'offline';
            if ($delivering) {
                $status = 'delivering';
            } elseif ($att && $att->status === 'break') {
                $status = 'break';
            } elseif ($courier->is_active) {
                $status = 'online';
            }

            $lat = $loc->latitude ?? $courier->latitude;
            $lng = $loc->longitude ?? $courier->longitude;

            $courier->presence_status = $status;
            $courier->att             = $att;
            $courier->loc             = $loc;
            $courier->online          = in_array($status, ['online', 'delivering']);
            $courier->is_late         = $att && $cutoffCarbon
                && (int) $att->check_in_at->format('Hi') > (int) $cutoffCarbon->format('Hi');
            $courier->map_lat         = $lat ? (float) $lat : null;
            $courier->map_lng         = $lng ? (float) $lng : null;
            $courier->last_seen_secs  = $loc && $loc->recorded_at
                ? (int) max(0, $loc->recorded_at->diffInSeconds(now()))
                : null;

            $chipCounts['semua']++;
            $chipCounts[$status]++;
            if ($att && ! $courier->is_late) {
                $onTime++;
            }
        }

        $onlineCount = $chipCounts['online'] + $chipCounts['delivering'];
        $gpsCount    = $couriers->filter(fn ($c) => $c->map_lat && $c->map_lng)->count();

        $times = $couriers->filter(fn ($c) => $c->att && $c->att->check_in_at)
            ->map(fn ($c) => $c->att->check_in_at);

        $avgCheckin = $times->isNotEmpty()
            ? \Carbon\Carbon::createFromTimestamp((int) floor($times->avg(fn ($t) => $t->timestamp)))
            : null;

        $mapCouriers = $couriers->filter(fn ($c) => $c->map_lat && $c->map_lng)->values()->map(fn ($c) => [
            'id'        => $c->id,
            'name'      => $c->user->name ?? 'Kurir',
            'initial'   => strtoupper(substr($c->user->name ?? 'K', 0, 1)),
            'status'    => $c->presence_status,
            'latitude'  => $c->map_lat,
            'longitude' => $c->map_lng,
            'speed'     => $c->loc && $c->loc->speed_kmh !== null ? (float) $c->loc->speed_kmh : null,
            'battery'   => $c->loc ? $c->loc->battery_percent : null,
            'accuracy'  => $c->loc && $c->loc->accuracy != null ? (float) $c->loc->accuracy : null,
            'lastSeen'  => $c->last_seen_secs,
            'city'      => $c->address ?? $c->city ?? '-',
        ]);

        $hub = DropPoint::where('name', 'Drop Point Bratang')->first()
            ?: (object) [
                'name'      => 'Drop Point Bratang',
                'address'   => 'Jl. Bratang Gede No. 42, Ngagelrejo, Surabaya',
                'latitude'  => -7.29124,
                'longitude' => 112.75921,
            ];

        $mapBounds = [
            'latMin' => -7.35,
            'latMax' => -7.20,
            'lngMin' => 112.65,
            'lngMax' => 112.85,
        ];

        return view('admin.ci-work.attendance', compact(
            'couriers',
            'date',
            'dateLabel',
            'cutoff',
            'dropPoint',
            'onlineCount',
            'gpsCount',
            'avgCheckin',
            'onTime',
            'chipCounts',
            'mapCouriers',
            'hub',
            'mapBounds'
        ));
    }

    /**
     * Export attendance log (CSV).
     */
    public function exportAttendance(Request $request)
    {
        $date = $request->query('date') ? \Carbon\Carbon::parse($request->query('date'))->toDateString() : today()->toDateString();

        $attendances = \App\Models\Attendance::with('courier.user')
            ->whereDate('check_in_at', $date)
            ->latest('check_in_at')
            ->get();

        $filename = 'log-kehadiran-' . $date . '.csv';

        return response()->streamDownload(function () use ($attendances, $date) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, ['No', 'Nama Kurir', 'WhatsApp', 'Email', 'Check-In', 'Check-Out', 'Status', 'Drop Point', 'Durasi']);

            foreach ($attendances->values() as $i => $att) {
                fputcsv($output, [
                    $i + 1,
                    $att->courier->user->name ?? '-',
                    $att->courier->user->phone ?? $att->courier->phone ?? '-',
                    $att->courier->user->email ?? '-',
                    $att->check_in_at ? $att->check_in_at->format('H:i') : '-',
                    $att->check_out_at ? $att->check_out_at->format('H:i') : '-',
                    ucfirst($att->status),
                    $att->drop_point_name ?? '-',
                    $att->duration ?? '-',
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Toggle courier active state from the attendance roster.
     */
    public function toggleActive(Request $request, Courier $courier)
    {
        $courier->update(['is_active' => ! $courier->is_active]);

        return back()->with('success', 'Status kurir ' . ($courier->user->name ?? '') . ' diperbarui menjadi ' . ($courier->is_active ? 'Aktif' : 'Nonaktif') . '.');
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