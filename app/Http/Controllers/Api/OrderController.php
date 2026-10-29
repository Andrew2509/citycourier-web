<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrackingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * Get orders for the authenticated courier with optional filter.
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

        $query = Order::with('shipment')->where('courier_id', $courier->id);

        // Filter by specific status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by schedule category
        if ($request->filled('schedule_tab')) {
            $tab = $request->schedule_tab;
            $today = Carbon::today('Asia/Jakarta');

            if ($tab === 'today') {
                // Pickup terjadwal hari ini
                $query->where(function ($q) use ($today) {
                    $q->whereDate('pickup_scheduled_at', $today)
                      ->orWhere(function ($sq) use ($today) {
                          $sq->where('scheduled_pickup', true)
                             ->whereDate('created_at', $today);
                      });
                })->orderBy('pickup_scheduled_at', 'asc');
            } elseif ($tab === 'scheduled') {
                // Pickup terjadwal (mendatang)
                $query->where('status', 'assigned')
                      ->where('scheduled_pickup', true)
                      ->where('pickup_scheduled_at', '>=', Carbon::now('Asia/Jakarta'))
                      ->orderBy('pickup_scheduled_at', 'asc');
            } elseif ($tab === 'picking_up') {
                // Dalam proses penjemputan
                $query->where('status', 'picking_up');
            } elseif ($tab === 'completed') {
                // Selesai
                $query->whereIn('status', ['picked_up', 'delivering', 'delivered']);
            }
        }

        $orders = $query->latest()->paginate(25);

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
        $orders = Order::with('shipment')->where('status', 'pending')
            ->orderByRaw('scheduled_pickup DESC, pickup_scheduled_at ASC, id DESC')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get active (in-progress) order for the courier.
     * GET /api/orders/active
     */
    public function active(Request $request)
    {
        $courier = $request->user()->courier;

        if (!$courier) {
            return response()->json([
                'success' => false,
                'message' => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        // Active statuses: assigned, picking_up, picked_up, delivering
        $order = Order::with('shipment')->where('courier_id', $courier->id)
            ->whereIn('status', ['assigned', 'picking_up', 'picked_up', 'delivering'])
            ->latest('updated_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Update order status dengan proteksi konkurensi, penjagaan jadwal, dan riwayat pelacakan.
     * PATCH /api/update-status-order
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id'   => 'required|exists:orders,id',
            'status'     => 'required|in:assigned,picking_up,picked_up,delivering,delivered,cancelled',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'accuracy'   => 'nullable|numeric',
            'notes'      => 'nullable|string',
            'issue_reason' => 'nullable|string',
            'issue_notes'  => 'nullable|string',
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

        $newStatus = $request->status;

        // 1. Klaim / Terima pesanan (status: assigned) dengan Locking Concurrency
        if ($newStatus === 'assigned') {
            return DB::transaction(function () use ($request, $courier) {
                $order = Order::where('id', $request->order_id)->lockForUpdate()->first();

                if (!$order) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order tidak ditemukan.',
                    ], 404);
                }

                if ($order->status !== 'pending') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pesanan ini sudah diterima oleh kurir lain atau tidak lagi tersedia.',
                    ], 409);
                }

                $order->update([
                    'courier_id' => $courier->id,
                    'status'     => 'assigned',
                ]);

                if ($order->shipment) {
                    $order->shipment->update(['status' => 'assigned']);

                    $courierName = $courier->user?->name ?? $courier->name ?? 'Kurir';
                    $vehicleName = $order->vehicle_display_name;
                    $desc = "Pesanan telah diterima oleh {$courierName} ({$vehicleName}).";
                    if ($order->scheduled_pickup && $order->pickup_time) {
                        $desc .= " Dijadwalkan penjemputan: {$order->pickup_time}.";
                    }

                    $this->trackingService->createStatusHistory(
                        $order->shipment,
                        'assigned',
                        $courier->id,
                        $request->latitude,
                        $request->longitude,
                        null,
                        $desc
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan berhasil diambil.',
                    'data'    => $order->fresh(['shipment']),
                ]);
            });
        }

        // 2. Operasi pada pesanan yang sudah dimiliki kurir
        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan.',
            ], 404);
        }

        if ($order->courier_id !== $courier->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk memperbarui order ini.',
            ], 403);
        }

        // 3. Validasi Jadwal Pickup (Schedule Enforcement) saat Mulai Pickup (picking_up)
        if ($newStatus === 'picking_up') {
            if ($order->scheduled_pickup && $order->pickup_scheduled_at) {
                $now = Carbon::now('Asia/Jakarta');
                $scheduled = Carbon::parse($order->pickup_scheduled_at, 'Asia/Jakarta');
                $toleranceMinutes = 30; // Toleransi awal 30 menit
                $toleranceStart = (clone $scheduled)->subMinutes($toleranceMinutes);

                if ($now->lt($toleranceStart)) {
                    $minsLeft = (int) ceil($now->diffInRealMinutes($toleranceStart, false));
                    $schedStr = $scheduled->format('d M Y, h:i A');
                    return response()->json([
                        'success' => false,
                        'message' => "Penjemputan belum dapat dimulai. Jadwal pickup pelanggan adalah {$schedStr}. Anda dapat mulai 30 menit sebelum jadwal (dalam {$minsLeft} menit lagi).",
                        'data'    => [
                            'scheduled_at'    => $scheduled->toIso8601String(),
                            'tolerance_start' => $toleranceStart->toIso8601String(),
                            'minutes_left'    => $minsLeft,
                        ],
                    ], 422);
                }
            }
        }

        // 4. Update data dan foto sesuai transisi status
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'picking_up') {
            $updateData['pickup_started_at'] = Carbon::now('Asia/Jakarta');
        } elseif ($newStatus === 'picked_up') {
            $updateData['picked_up_at'] = Carbon::now('Asia/Jakarta');
            if ($request->hasFile('photo')) {
                $updateData['pickup_photo'] = $request->file('photo')->store('orders/pickup', 'public');
            }
        } elseif ($newStatus === 'delivering') {
            $updateData['delivering_at'] = Carbon::now('Asia/Jakarta');
        } elseif ($newStatus === 'delivered') {
            $updateData['delivered_at'] = Carbon::now('Asia/Jakarta');
            if ($request->hasFile('photo')) {
                $updateData['delivery_photo'] = $request->file('photo')->store('orders/delivery', 'public');
            }
        } elseif ($newStatus === 'cancelled') {
            if ($request->filled('issue_reason')) {
                $updateData['issue_reason'] = $request->issue_reason;
            }
            if ($request->filled('issue_notes')) {
                $updateData['issue_notes'] = $request->issue_notes;
            }
        }

        $order->update($updateData);

        // 5. Sinkronisasi ke Shipment & pembuatan log pelacakan
        if ($order->shipment) {
            $shipmentStatusMap = [
                'assigned'   => 'assigned',
                'picking_up' => 'picking_up',
                'picked_up'  => 'picked_up',
                'delivering' => 'delivering',
                'delivered'  => 'delivered',
                'cancelled'  => 'cancelled',
            ];
            $shipmentStatus = $shipmentStatusMap[$newStatus] ?? null;

            if ($shipmentStatus) {
                $order->shipment->update(['status' => $shipmentStatus]);

                // Deskripsi status ramah pelanggan
                $descriptions = [
                    'assigned'   => 'Kurir telah ditugaskan dan siap melayani penjemputan paket.',
                    'picking_up' => 'Kurir sedang dalam perjalanan menuju lokasi penjemputan barang.',
                    'picked_up'  => 'Paket telah berhasil diserahkan pengirim dan diterima oleh kurir.',
                    'delivering' => 'Paket sedang dalam perjalanan menuju alamat penerima.',
                    'delivered'  => 'Paket telah berhasil diantarkan dan diterima di tujuan.',
                    'cancelled'  => 'Pesanan dibatalkan: ' . ($request->issue_notes ?? $request->notes ?? '-'),
                ];
                $statusDesc = $descriptions[$shipmentStatus] ?? 'Status pesanan diperbarui menjadi ' . $shipmentStatus;

                $this->trackingService->createStatusHistory(
                    $order->shipment,
                    $shipmentStatus,
                    $courier->id,
                    $request->latitude,
                    $request->longitude,
                    null,
                    $statusDesc
                );

                // Catat telemetri lokasi kurir jika ada koordinat
                if ($request->latitude && $request->longitude) {
                    $this->trackingService->saveCourierLocation(
                        $courier->id,
                        $order->shipment->id,
                        $request->latitude,
                        $request->longitude,
                        $request->accuracy
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Status order berhasil diperbarui.',
            'data'    => $order->fresh(['shipment']),
        ]);
    }

    /**
     * Laporkan kendala penjemputan tanpa membatalkan order secara sepihak.
     * POST /api/orders/report-issue
     */
    public function reportIssue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id'     => 'required|exists:orders,id',
            'issue_reason' => 'required|string|max:100',
            'issue_notes'  => 'nullable|string',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
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
            return response()->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
        }

        $order = Order::find($request->order_id);
        if ($order->courier_id !== $courier->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $order->update([
            'issue_reason' => $request->issue_reason,
            'issue_notes'  => $request->issue_notes,
        ]);

        if ($order->shipment) {
            $reasonLabels = [
                'customer_unreachable' => 'Pengirim tidak dapat dihubungi di lokasi.',
                'package_not_ready'    => 'Barang belum siap untuk dijemput.',
                'reschedule_requested' => 'Pengirim meminta penyesuaian jadwal pickup.',
                'address_not_found'    => 'Alamat penjemputan sulit ditemukan / tidak sesuai.',
                'other'                => 'Kendala penjemputan dilaporkan oleh kurir.',
            ];
            $reasonText = $reasonLabels[$request->issue_reason] ?? $request->issue_reason;
            $fullDesc = "Laporan kendala: {$reasonText}" . ($request->issue_notes ? " ({$request->issue_notes})" : "");

            $this->trackingService->createStatusHistory(
                $order->shipment,
                $order->shipment->status,
                $courier->id,
                $request->latitude,
                $request->longitude,
                null,
                $fullDesc
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Laporan kendala berhasil dicatat.',
            'data'    => $order->fresh(['shipment']),
        ]);
    }
}
