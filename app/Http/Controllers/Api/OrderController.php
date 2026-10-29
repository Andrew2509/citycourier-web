<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRejection;
use App\Models\Shipment;
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
                // Pickup terjadwal hari ini (yang masih aktif, bukan dibatalkan)
                $query->whereNotIn('status', ['cancelled'])
                    ->where(function ($q) use ($today) {
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
     * Excludes orders that have already been rejected by the authenticated courier.
     * GET /api/orders/available
     */
    public function available(Request $request)
    {
        $courier = $request->user()?->courier;

        $query = Order::with('shipment')->where('status', 'pending');

        if ($courier) {
            $query->where(function ($q) use ($courier) {
                $q->whereNull('courier_id')
                  ->orWhere('courier_id', $courier->id);
            })->whereDoesntHave('rejections', function ($q) use ($courier) {
                $q->where('courier_id', $courier->id);
            });
        }

        $orders = $query->orderByRaw('scheduled_pickup DESC, pickup_scheduled_at ASC, id DESC')
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

        // Active statuses: assigned, picking_up, picked_up, delivering (cancelled is excluded)
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
            'order_id'     => 'required|exists:orders,id',
            'status'       => 'required|in:assigned,picking_up,picked_up,delivering,delivered,cancelled',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'accuracy'     => 'nullable|numeric',
            'notes'        => 'nullable|string',
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

                $shipment = Shipment::where('shipment_number', $order->order_number)->lockForUpdate()->first();

                if ($order->status === 'cancelled' || ($shipment && $shipment->status === 'cancelled')) {
                    return response()->json([
                        'success'    => false,
                        'error_code' => 'order_cancelled',
                        'message'    => 'Pesanan ini telah dibatalkan oleh pelanggan dan tidak dapat diambil.',
                    ], 409);
                }

                if ($order->status !== 'pending') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pesanan ini sudah diterima oleh kurir lain atau tidak lagi tersedia.',
                    ], 409);
                }

                $order->update([
                    'courier_id'            => $courier->id,
                    'status'                => 'assigned',
                    'courier_cancel_status' => null,
                ]);

                if ($shipment) {
                    $shipment->update(['status' => 'assigned']);

                    $courierName = $courier->user?->name ?? $courier->name ?? 'Kurir';
                    $vehicleName = $order->vehicle_display_name;
                    $desc = "Pesanan telah diterima oleh {$courierName} ({$vehicleName}).";
                    if ($order->scheduled_pickup && $order->pickup_time) {
                        $desc .= " Dijadwalkan penjemputan: {$order->pickup_time}.";
                    }

                    $this->trackingService->createStatusHistory(
                        $shipment,
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

        // 2. Jika kurir meminta status 'cancelled', arahkan ke mekanisme pembatalan penugasan kurir
        if ($newStatus === 'cancelled') {
            $request->merge([
                'reason' => $request->input('reason') ?: ($request->input('issue_reason') ?: 'Tidak dapat menjemput pada jadwal yang ditentukan.'),
                'notes'  => $request->input('notes') ?: $request->input('issue_notes'),
            ]);
            return $this->cancelAssignment($request, $request->order_id);
        }

        // 3. Operasi pada pesanan yang sudah dimiliki kurir (dengan DB Transaction & Row Locking)
        return DB::transaction(function () use ($request, $courier, $newStatus) {
            $order = Order::where('id', $request->order_id)->lockForUpdate()->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tidak ditemukan.',
                ], 404);
            }

            $shipment = Shipment::where('shipment_number', $order->order_number)->lockForUpdate()->first();

            if ($order->courier_id !== $courier->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk memperbarui order ini.',
                ], 403);
            }

            // Cek jika pesanan sudah dibatalkan oleh pelanggan atau admin
            if ($order->status === 'cancelled' || ($shipment && $shipment->status === 'cancelled')) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_cancelled',
                    'message'    => 'Pesanan ini telah dibatalkan dan tidak dapat diproses oleh kurir.',
                ], 409);
            }

            $now = Carbon::now('Asia/Jakarta');

            // 3. Validasi Jadwal Pickup (Schedule Enforcement) saat Mulai Pickup (picking_up)
            if ($newStatus === 'picking_up') {
                $scheduled = $order->getPickupScheduledAtJakarta() ?? ($shipment ? $shipment->getPickupScheduledAtJakarta($order) : null);
                if (($order->scheduled_pickup || ($shipment && $shipment->scheduled_pickup)) && $scheduled) {
                    $toleranceMinutes = 30; // Toleransi awal 30 menit
                    $toleranceStart = $scheduled->copy()->subMinutes($toleranceMinutes);

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
            $previousOrderStatus = $order->status;
            $updateData = ['status' => $newStatus];

            if ($newStatus === 'picking_up') {
                $updateData['pickup_started_at'] = $now;
            } elseif ($newStatus === 'picked_up') {
                $updateData['picked_up_at'] = $now;
                if ($request->hasFile('photo')) {
                    $updateData['pickup_photo'] = $request->file('photo')->store('orders/pickup', 'public');
                }
            } elseif ($newStatus === 'delivering') {
                $updateData['delivering_at'] = $now;
            } elseif ($newStatus === 'delivered') {
                $updateData['delivered_at'] = $now;
                if ($request->hasFile('photo')) {
                    $updateData['delivery_photo'] = $request->file('photo')->store('orders/delivery', 'public');
                }
            } elseif ($newStatus === 'cancelled') {
                $cancelReason = $request->issue_notes ?: ($request->issue_reason ?: ($request->notes ?: 'Dibatalkan oleh kurir'));
                if ($request->filled('issue_reason')) {
                    $updateData['issue_reason'] = $request->issue_reason;
                }
                if ($request->filled('issue_notes')) {
                    $updateData['issue_notes'] = $request->issue_notes;
                }
                $updateData['cancelled_at'] = $now;
                $updateData['cancelled_by'] = 'courier';
                $updateData['cancellation_reason'] = $cancelReason;
                $updateData['cancelled_from_status'] = $previousOrderStatus;
            }

            $order->update($updateData);

            // 5. Sinkronisasi ke Shipment & pembuatan log pelacakan
            if ($shipment) {
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
                    $previousShipmentStatus = $shipment->status;
                    $shipmentUpdate = ['status' => $shipmentStatus];
                    if ($shipmentStatus === 'cancelled') {
                        $cancelReason = $request->issue_notes ?: ($request->issue_reason ?: ($request->notes ?: 'Dibatalkan oleh kurir'));
                        $shipmentUpdate['cancelled_at'] = $now;
                        $shipmentUpdate['cancelled_by'] = 'courier';
                        $shipmentUpdate['cancellation_reason'] = $cancelReason;
                        $shipmentUpdate['cancelled_from_status'] = $previousShipmentStatus;
                    }
                    $shipment->update($shipmentUpdate);

                    // Deskripsi status ramah pelanggan
                    $descriptions = [
                        'assigned'   => 'Kurir telah ditugaskan dan siap melayani penjemputan paket.',
                        'picking_up' => 'Kurir sedang dalam perjalanan menuju lokasi penjemputan barang.',
                        'picked_up'  => 'Paket telah berhasil diserahkan pengirim dan diterima oleh kurir.',
                        'delivering' => 'Paket sedang dalam perjalanan menuju alamat penerima.',
                        'delivered'  => 'Paket telah berhasil diantarkan dan diterima di tujuan.',
                        'cancelled'  => 'Pesanan dibatalkan oleh kurir (status sebelumnya: ' . $previousShipmentStatus . '). Alasan: ' . ($request->issue_notes ?? $request->notes ?? '-'),
                    ];
                    $statusDesc = $descriptions[$shipmentStatus] ?? 'Status pesanan diperbarui menjadi ' . $shipmentStatus;

                    $this->trackingService->createStatusHistory(
                        $shipment,
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
                            $shipment->id,
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
        });
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

        if ($order->status === 'cancelled' || $order->shipment?->status === 'cancelled') {
            return response()->json([
                'success'    => false,
                'error_code' => 'order_cancelled',
                'message'    => 'Pesanan ini telah dibatalkan dan tidak dapat diproses lagi.',
            ], 409);
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

    /**
     * Pembatalan / Pelepasan Penugasan Pesanan oleh Kurir.
     * POST /api/orders/cancel-assignment
     * POST /api/orders/{order}/cancel-assignment
     */
    public function cancelAssignment(Request $request, $orderParam = null)
    {
        $orderId = $orderParam instanceof Order
            ? $orderParam->id
            : ($orderParam ?? $request->input('order_id'));

        $rawReason = trim((string) ($request->input('reason') ?: $request->input('issue_reason') ?: ''));
        $rawNotes  = trim((string) ($request->input('notes') ?: $request->input('issue_notes') ?: ''));

        if (empty($orderId)) {
            return response()->json([
                'success'    => false,
                'error_code' => 'missing_order_id',
                'title'      => 'Pembatalan Tidak Dapat Diproses',
                'message'    => 'ID pesanan wajib disertakan.',
            ], 422);
        }

        if ($rawReason === '') {
            return response()->json([
                'success'    => false,
                'error_code' => 'missing_reason',
                'title'      => 'Pembatalan Tidak Dapat Diproses',
                'message'    => 'Alasan pembatalan penugasan wajib dipilih.',
            ], 422);
        }

        // Pemetaan kode alasan ke teks standar bahasa Indonesia
        $reasonMap = [
            'schedule_conflict'      => 'Tidak dapat menjemput pada jadwal yang ditentukan.',
            'vehicle_issue'          => 'Kendala kendaraan.',
            'location_hard_to_reach' => 'Lokasi pickup sulit dijangkau.',
            'personal_emergency'     => 'Kendala pribadi atau keadaan darurat.',
            'other'                  => 'Alasan lainnya.',
        ];
        $resolvedReason = $reasonMap[$rawReason] ?? $rawReason;
        $isOtherReason  = in_array(strtolower($rawReason), ['other', 'alasan lainnya', 'alasan lainnya.'], true);

        if ($isOtherReason && $rawNotes === '') {
            return response()->json([
                'success'    => false,
                'error_code' => 'missing_other_notes',
                'title'      => 'Pembatalan Tidak Dapat Diproses',
                'message'    => 'Keterangan tambahan wajib diisi jika memilih alasan lainnya.',
            ], 422);
        }

        $courier = $request->user()?->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'error_code' => 'courier_not_found',
                'title'      => 'Pembatalan Tidak Dapat Diproses',
                'message'    => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        return DB::transaction(function () use ($request, $courier, $orderId, $resolvedReason, $rawNotes) {
            $order = Order::where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_not_found',
                    'title'      => 'Pembatalan Tidak Dapat Diproses',
                    'message'    => 'Pesanan tidak ditemukan.',
                ], 404);
            }

            $shipment = Shipment::where('shipment_number', $order->order_number)->lockForUpdate()->first();

            // 1. Idempotensi: jika kurir yang sama menekan tombol dua kali setelah pelepasan berhasil
            if (
                $order->status === 'pending' &&
                $order->courier_id === null &&
                (int) $order->courier_cancelled_by_id === (int) $courier->id &&
                in_array($order->courier_cancel_status, ['released', 'approved'], true)
            ) {
                return response()->json([
                    'success'           => true,
                    'already_processed' => true,
                    'action_taken'      => 'released',
                    'priority'          => $order->courier_cancel_priority ?? 'normal',
                    'title'             => 'Penugasan Berhasil Dibatalkan',
                    'message'           => 'Penugasan pesanan ini sudah dilepas dari akun kamu.',
                    'data'              => $order->fresh(['shipment']),
                ], 200);
            }

            // 2. Idempotensi: jika kurir yang sama sudah mengajukan pembatalan dan sedang menunggu persetujuan admin
            if (
                (int) $order->courier_id === (int) $courier->id &&
                $order->courier_cancel_status === 'pending_approval'
            ) {
                return response()->json([
                    'success'           => true,
                    'already_processed' => true,
                    'action_taken'      => 'pending_approval',
                    'priority'          => $order->courier_cancel_priority ?? 'normal',
                    'title'             => 'Permintaan Pembatalan Terkirim',
                    'message'           => 'Permintaan kamu sedang diperiksa oleh admin. Status penugasan akan diperbarui setelah keputusan diberikan.',
                    'data'              => $order->fresh(['shipment']),
                ], 200);
            }

            // 3. Validasi otorisasi kepemilikan penugasan kurir
            if ((int) $order->courier_id !== (int) $courier->id) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'forbidden_not_assigned_courier',
                    'title'      => 'Pembatalan Tidak Dapat Diproses',
                    'message'    => 'Anda tidak memiliki hak untuk membatalkan penugasan pesanan milik kurir lain atau yang belum ditugaskan kepada Anda.',
                ], 403);
            }

            // 4. Validasi status: pesanan sudah dibatalkan oleh pelanggan/admin
            if ($order->status === 'cancelled' || ($shipment && $shipment->status === 'cancelled')) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_cancelled',
                    'title'      => 'Pembatalan Tidak Dapat Diproses',
                    'message'    => 'Pesanan ini telah dibatalkan sebelumnya dan tidak dapat diproses lagi.',
                ], 422);
            }

            // 5. Validasi status: pesanan sudah selesai (delivered)
            if ($order->status === 'delivered' || ($shipment && $shipment->status === 'delivered')) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_already_completed',
                    'title'      => 'Pembatalan Tidak Dapat Diproses',
                    'message'    => 'Pesanan yang sudah selesai dikirim tidak dapat dibatalkan penugasannya.',
                ], 422);
            }

            // 6. Validasi status: barang sudah diambil atau sedang dikirim (picked_up / delivering / in_transit)
            if (
                in_array($order->status, ['picked_up', 'delivering', 'in_transit'], true) ||
                $order->picked_up_at !== null ||
                ($shipment && in_array($shipment->status, ['picked_up', 'delivering', 'in_transit'], true))
            ) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'item_already_picked_up',
                    'title'      => 'Pembatalan Tidak Dapat Diproses',
                    'message'    => 'Barang sudah diambil atau sedang dalam pengiriman. Pembatalan penugasan secara langsung tidak diizinkan. Silakan laporkan kendala kepada admin City Courier.',
                ], 422);
            }

            $now = Carbon::now('Asia/Jakarta');

            // 7. Evaluasi apakah waktu pickup tersisa <= 2 jam (120 menit) untuk penandaan prioritas tinggi ke admin
            // Penting: Kurir TIDAK diblokir oleh aturan 2 jam pelanggan, namun diberi prioritas tinggi ke admin.
            $scheduledAt = $order->getPickupScheduledAtJakarta()
                ?? ($shipment ? $shipment->getPickupScheduledAtJakarta($order) : null);
            $isScheduled = (bool) $order->scheduled_pickup
                || ($shipment && (bool) $shipment->scheduled_pickup)
                || $scheduledAt !== null;

            $isWithinTwoHours = false;
            $minutesUntilPickup = null;
            if ($isScheduled && $scheduledAt !== null) {
                $diffSeconds = $now->diffInSeconds($scheduledAt, false);
                $minutesUntilPickup = $diffSeconds >= 0
                    ? (int) floor($diffSeconds / 60)
                    : (int) ceil($diffSeconds / 60);

                if ($diffSeconds <= (Shipment::CUSTOMER_CANCEL_CUTOFF_MINUTES * 60)) {
                    $isWithinTwoHours = true;
                }
            }

            $priority = $isWithinTwoHours ? 'urgent' : 'normal';
            $courierName = $courier->user?->name ?? $courier->name ?? "Kurir #{$courier->id}";
            $fullReasonDisplay = $resolvedReason . ($rawNotes !== '' ? " ({$rawNotes})" : '');
            $priorityPrefix = $isWithinTwoHours ? '[PRIORITAS TINGGI - Pickup <= 2 Jam] ' : '';

            // 8. Tentukan apakah memerlukan persetujuan admin atau pelepasan penugasan langsung:
            // - Jika proses pickup sudah dimulai (status 'picking_up' atau pickup_started_at != null) -> WAJIB persetujuan admin
            // - Jika kebijakan sistem mengharuskan persetujuan admin -> WAJIB persetujuan admin
            $policyRequiresApproval = \App\Models\Setting::get('courier_cancel_require_admin_approval', '0') === '1'
                || $request->boolean('require_admin_approval', false);
            $pickupStarted = $order->status === 'picking_up' || $order->pickup_started_at !== null;
            $requiresAdminApproval = $pickupStarted || $policyRequiresApproval;

            if ($requiresAdminApproval) {
                $order->update([
                    'courier_cancel_status'   => 'pending_approval',
                    'courier_cancel_reason'   => $resolvedReason,
                    'courier_cancel_notes'    => $rawNotes !== '' ? $rawNotes : null,
                    'courier_cancelled_at'    => $now,
                    'courier_cancelled_by_id' => $courier->id,
                    'courier_cancel_priority' => $priority,
                    'issue_reason'            => $resolvedReason,
                    'issue_notes'             => $rawNotes !== '' ? $rawNotes : null,
                ]);

                if ($shipment) {
                    $logDesc = "{$priorityPrefix}Permintaan pembatalan penugasan diajukan oleh kurir {$courierName} (Menunggu Persetujuan Admin). Alasan: {$fullReasonDisplay}";
                    $this->trackingService->createStatusHistory(
                        $shipment,
                        $shipment->status,
                        $courier->id,
                        $request->latitude,
                        $request->longitude,
                        null,
                        $logDesc
                    );
                }

                return response()->json([
                    'success'              => true,
                    'already_processed'    => false,
                    'action_taken'         => 'pending_approval',
                    'priority'             => $priority,
                    'is_within_2_hours'    => $isWithinTwoHours,
                    'minutes_until_pickup' => $minutesUntilPickup,
                    'title'                => 'Permintaan Pembatalan Terkirim',
                    'message'              => 'Permintaan kamu sedang diperiksa oleh admin. Status penugasan akan diperbarui setelah keputusan diberikan.',
                    'data'                 => $order->fresh(['shipment']),
                ], 200);
            }

            // 9. Pelepasan penugasan langsung (sebelum pickup dimulai / status 'assigned')
            // Pesanan dikembalikan ke antrean ('pending' pada Order, 'confirmed' pada Shipment)
            $order->update([
                'courier_id'              => null,
                'status'                  => 'pending',
                'pickup_started_at'       => null,
                'courier_cancel_status'   => 'released',
                'courier_cancel_reason'   => $resolvedReason,
                'courier_cancel_notes'    => $rawNotes !== '' ? $rawNotes : null,
                'courier_cancelled_at'    => $now,
                'courier_cancelled_by_id' => $courier->id,
                'courier_cancel_priority' => $priority,
                'issue_reason'            => $resolvedReason,
                'issue_notes'             => $rawNotes !== '' ? $rawNotes : null,
            ]);

            if ($shipment) {
                $shipment->update(['status' => 'confirmed']);

                $logDesc = "{$priorityPrefix}Penugasan dilepas oleh kurir {$courierName}. Alasan: {$fullReasonDisplay}. Pesanan dikembalikan ke antrean penugasan kurir.";
                $this->trackingService->createStatusHistory(
                    $shipment,
                    'confirmed',
                    $courier->id,
                    $request->latitude,
                    $request->longitude,
                    null,
                    $logDesc
                );
            }

            return response()->json([
                'success'              => true,
                'already_processed'    => false,
                'action_taken'         => 'released',
                'priority'             => $priority,
                'is_within_2_hours'    => $isWithinTwoHours,
                'minutes_until_pickup' => $minutesUntilPickup,
                'title'                => 'Penugasan Berhasil Dibatalkan',
                'message'              => 'Penugasan pesanan ini sudah dilepas dari akun kamu.',
                'data'                 => $order->fresh(['shipment']),
            ], 200);
        });
    }

    /**
     * Tolak penawaran pesanan oleh kurir sebelum pesanan diterima (status 'pending').
     * POST /api/orders/reject atau POST /api/orders/{order}/reject
     */
    public function rejectOrder(Request $request, $orderParam = null)
    {
        $orderId = $orderParam ?? $request->input('order_id');
        if ($orderId && !$request->has('order_id')) {
            $request->merge(['order_id' => $orderId]);
        }

        $validator = Validator::make($request->all(), [
            'order_id'  => 'required',
            'reason'    => 'required|string|max:255',
            'notes'     => 'nullable|string|max:1000',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success'    => false,
                'error_code' => 'validation_failed',
                'title'      => 'Penolakan Tidak Dapat Diproses',
                'message'    => 'Alasan penolakan pesanan wajib dipilih.',
                'errors'     => $validator->errors(),
            ], 422);
        }

        $rawReason = trim((string) $request->input('reason'));
        $rawNotes  = trim((string) $request->input('notes', ''));

        $reasonMap = [
            'pickup_too_far'          => 'Lokasi pickup terlalu jauh.',
            'unavailable_at_schedule' => 'Tidak tersedia pada jadwal pickup.',
            'vehicle_issue'           => 'Kendaraan tidak sesuai atau sedang bermasalah.',
            'capacity_mismatch'       => 'Kapasitas barang tidak sesuai.',
            'handling_other_order'    => 'Sedang menangani pesanan lain.',
            'other'                   => 'Alasan lainnya.',
        ];
        $resolvedReason = $reasonMap[$rawReason] ?? $rawReason;
        $isOtherReason  = in_array(strtolower($rawReason), ['other', 'alasan lainnya', 'alasan lainnya.'], true);

        if ($isOtherReason && $rawNotes === '') {
            return response()->json([
                'success'    => false,
                'error_code' => 'missing_other_notes',
                'title'      => 'Penolakan Tidak Dapat Diproses',
                'message'    => 'Keterangan tambahan wajib diisi jika memilih alasan lainnya.',
            ], 422);
        }

        $courier = $request->user()?->courier;
        if (!$courier) {
            return response()->json([
                'success'    => false,
                'error_code' => 'courier_not_found',
                'title'      => 'Penolakan Tidak Dapat Diproses',
                'message'    => 'Profil kurir tidak ditemukan.',
            ], 404);
        }

        return DB::transaction(function () use ($request, $courier, $orderId, $resolvedReason, $rawNotes) {
            $order = Order::where('id', $orderId)->lockForUpdate()->first();
            if (!$order && is_string($orderId)) {
                $order = Order::where('order_number', $orderId)->lockForUpdate()->first();
            }

            if (!$order) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_not_found',
                    'title'      => 'Penolakan Tidak Dapat Diproses',
                    'message'    => 'Pesanan tidak ditemukan.',
                ], 404);
            }

            $shipment = Shipment::where('shipment_number', $order->order_number)->lockForUpdate()->first();

            // 1. Validasi status: pesanan sudah dibatalkan oleh pelanggan/admin
            if ($order->status === 'cancelled' || ($shipment && $shipment->status === 'cancelled')) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_cancelled',
                    'title'      => 'Penolakan Tidak Dapat Diproses',
                    'message'    => 'Pesanan ini telah dibatalkan dan tidak dapat ditolak.',
                ], 422);
            }

            // 2. Validasi status: pesanan sudah selesai dikirim (delivered)
            if ($order->status === 'delivered' || ($shipment && $shipment->status === 'delivered')) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'order_already_completed',
                    'title'      => 'Penolakan Tidak Dapat Diproses',
                    'message'    => 'Pesanan yang sudah selesai dikirim tidak dapat ditolak.',
                ], 422);
            }

            // 3. Validasi: jika kurir yang sama SUDAH menerima pesanan ini (status != 'pending' dan courier_id == courier->id)
            // Kurir tidak boleh menggunakan tombol Tolak Pesanan setelah menerima pesanan; harus menggunakan Batalkan Pesanan.
            if ((int) $order->courier_id === (int) $courier->id && $order->status !== 'pending') {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'already_accepted_use_cancel_assignment',
                    'title'      => 'Pesanan Sudah Diterima',
                    'message'    => 'Anda sudah menerima pesanan ini. Gunakan fitur Batalkan Pesanan jika tidak dapat melanjutkan penugasan.',
                ], 422);
            }

            // 4. Validasi: jika pesanan sudah diterima oleh kurir lain atau penawaran tidak lagi aktif
            if (
                ($order->courier_id !== null && (int) $order->courier_id !== (int) $courier->id) ||
                $order->status !== 'pending'
            ) {
                return response()->json([
                    'success'    => false,
                    'error_code' => 'already_taken_by_other_courier',
                    'title'      => 'Penawaran Tidak Lagi Aktif',
                    'message'    => 'Pesanan ini sudah diterima oleh kurir lain atau penawaran sudah tidak aktif.',
                ], 409);
            }

            // 5. Idempotensi: jika kurir yang sama sudah menolak pesanan ini sebelumnya
            $existingRejection = OrderRejection::where('order_id', $order->id)
                ->where('courier_id', $courier->id)
                ->first();

            if ($existingRejection) {
                return response()->json([
                    'success'          => true,
                    'already_rejected' => true,
                    'title'            => 'Pesanan Telah Ditolak',
                    'message'          => 'Pesanan berhasil ditolak dan dikembalikan ke sistem penugasan.',
                    'data'             => [
                        'order_id'        => $order->id,
                        'order_number'    => $order->order_number,
                        'tracking_number' => $order->tracking_number,
                        'order_status'    => $order->status,
                        'shipment_status' => $shipment?->status ?? 'confirmed',
                        'rejection'       => $existingRejection,
                    ],
                ], 200);
            }

            $now = Carbon::now('Asia/Jakarta');

            // 6. Simpan riwayat penolakan kurir tanpa membatalkan pesanan pelanggan dan tanpa mengubah jadwal pickup
            $rejection = OrderRejection::create([
                'order_id'    => $order->id,
                'shipment_id' => $shipment?->id,
                'courier_id'  => $courier->id,
                'reason'      => $resolvedReason,
                'notes'       => $rawNotes !== '' ? $rawNotes : null,
                'rejected_at' => $now,
            ]);

            // Jika sebelumnya pesanan ditawarkan spesifik ke kurir ini dalam status 'pending', lepaskan courier_id agar tersedia bagi kurir lain
            if ($order->courier_id !== null || $order->status !== 'pending') {
                $order->update([
                    'courier_id' => null,
                    'status'     => 'pending',
                ]);
            }

            if ($shipment) {
                if ($shipment->status !== 'confirmed') {
                    $shipment->update(['status' => 'confirmed']);
                }

                $courierName = $courier->user?->name ?? $courier->name ?? "Kurir #{$courier->id}";
                $fullReasonDisplay = $resolvedReason . ($rawNotes !== '' ? " ({$rawNotes})" : '');
                $logDesc = "Tawaran pesanan ditolak oleh kurir {$courierName}. Alasan: {$fullReasonDisplay}. Pesanan tetap dalam antrean penugasan.";

                $this->trackingService->createStatusHistory(
                    $shipment,
                    $shipment->status,
                    $courier->id,
                    $request->latitude,
                    $request->longitude,
                    null,
                    $logDesc
                );
            }

            return response()->json([
                'success'          => true,
                'already_rejected' => false,
                'title'            => 'Pesanan Telah Ditolak',
                'message'          => 'Pesanan berhasil ditolak dan dikembalikan ke sistem penugasan.',
                'data'             => [
                    'order_id'            => $order->id,
                    'order_number'        => $order->order_number,
                    'tracking_number'     => $order->tracking_number,
                    'order_status'        => $order->status,
                    'shipment_status'     => $shipment?->status ?? 'confirmed',
                    'pickup_scheduled_at' => $order->getRawOriginal('pickup_scheduled_at'),
                    'pickup_time'         => $order->pickup_time,
                    'rejection'           => $rejection,
                ],
            ], 200);
        });
    }
}
