<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\TopUp\Services\TopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomerTopupController extends Controller
{
    public function __construct(
        private readonly TopupService $topupService
    ) {}

    public function methods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Metode top-up berhasil dimuat.',
            'data' => $this->topupService->getAvailableMethods(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1000'],
            'payment_type' => ['required', 'string', 'in:va,qris,ewallet'],
            'channel_code' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? Str::uuid()->toString()));

        try {
            $result = $this->topupService->createTopup(
                $request->user(),
                (int) $validated['amount'],
                (string) $validated['payment_type'],
                (string) $validated['channel_code'],
                $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'message' => $result['idempotent_replay']
                    ? 'Permintaan top-up mengembalikan transaksi sebelumnya (idempotent).'
                    : 'Permintaan top-up wallet berhasil dibuat.',
                'data' => array_merge($result['topup']->toApiArray(), [
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
            $msg = $e->getMessage();
            if (str_starts_with($msg, 'PROVIDER_DISABLED:')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'PROVIDER_DISABLED',
                    'message' => substr($msg, strlen('PROVIDER_DISABLED:')),
                ], 403);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'TOPUP_FAILED',
                'message' => $msg,
            ], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $topup = TopupTransaction::where('user_id', $request->user()->id)->findOrFail($id);
        $topup = $this->topupService->refreshAndSyncStatus($topup);

        return response()->json([
            'success' => true,
            'message' => 'Detail top-up berhasil dimuat.',
            'data' => $topup->toApiArray(),
        ]);
    }

    public function simulatePaid(Request $request, int $id): JsonResponse
    {
        $topup = TopupTransaction::where('user_id', $request->user()->id)->findOrFail($id);

        try {
            $settled = $this->topupService->simulatePaid($topup);

            return response()->json([
                'success' => true,
                'message' => 'Simulasi pembayaran top-up berhasil diselesaikan dan saldo telah dikreditkan.',
                'data' => $settled->toApiArray(),
            ]);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'production') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error_code' => $code === 403 ? 'FORBIDDEN_IN_PRODUCTION' : 'SIMULATION_FAILED',
                'message' => $e->getMessage(),
            ], $code);
        }
    }
}
