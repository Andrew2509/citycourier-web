<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\Refund\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomerRefundController extends Controller
{
    public function __construct(
        private readonly RefundService $refundService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));

        $paginator = RefundTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $paidShipmentIds = collect()
            ->merge(
                \App\Modules\Payment\Models\PaymentTransaction::where('user_id', $request->user()->id)
                    ->where('status', 'paid')
                    ->whereNotNull('shipment_id')
                    ->pluck('shipment_id')
            )
            ->merge(
                \App\Models\Payment::where('user_id', $request->user()->id)
                    ->where('status', 'paid')
                    ->whereNotNull('shipment_id')
                    ->pluck('shipment_id')
            )
            ->unique()
            ->values();

        $eligibleShipments = $paidShipmentIds->isEmpty()
            ? collect()
            : \App\Models\Shipment::where('user_id', $request->user()->id)
                ->whereIn('id', $paidShipmentIds)
                ->where('status', '!=', 'delivered')
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'shipment_number', 'status', 'payment_method', 'total_cost', 'created_at']);

        return response()->json([
            'success' => true,
            'message' => 'Daftar pengajuan refund berhasil dimuat.',
            'data' => [
                'items' => collect($paginator->items())->map(fn (RefundTransaction $r) => $r->toApiArray()),
                'eligible_shipments' => $eligibleShipments->map(fn ($s) => [
                    'id' => $s->id,
                    'shipment_number' => $s->shipment_number,
                    'status' => $s->status,
                    'total_cost' => (int) $s->total_cost,
                    'created_at' => $s->created_at?->toIso8601String(),
                ]),
                'policy' => [
                    'eligible_statuses' => config('payment_wallet.refund.eligible_statuses', ['pending', 'cancelled', 'failed', 'expired']),
                    'refund_window_hours' => (int) config('payment_wallet.refund.refund_window_hours', 168),
                ],
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_transaction_id' => ['nullable', 'integer', 'required_without:shipment_id'],
            'shipment_id' => ['nullable', 'integer', 'required_without:payment_transaction_id'],
            'amount' => ['nullable', 'integer', 'min:100'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? Str::uuid()->toString()));

        try {
            $result = $this->refundService->requestRefund($request->user(), $validated, $idempotencyKey);

            return response()->json([
                'success' => true,
                'message' => $result['idempotent_replay']
                    ? 'Permintaan refund mengembalikan pengajuan sebelumnya.'
                    : 'Pengajuan refund berhasil dikirim.',
                'data' => array_merge($result['refund']->toApiArray(), [
                    'idempotent_replay' => $result['idempotent_replay'],
                ]),
            ], $result['idempotent_replay'] ? 200 : 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'REFUND_NOT_ELIGIBLE',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
