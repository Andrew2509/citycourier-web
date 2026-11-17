<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Services\OrderWalletPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomerWalletPaymentController extends Controller
{
    public function __construct(
        private readonly OrderWalletPaymentService $paymentService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipment_id' => ['nullable', 'integer', 'required_without:order_id'],
            'order_id' => ['nullable', 'integer', 'required_without:shipment_id'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? Str::uuid()->toString()));

        try {
            $result = $this->paymentService->payWithWallet(
                $request->user(),
                isset($validated['shipment_id']) ? (int) $validated['shipment_id'] : null,
                isset($validated['order_id']) ? (int) $validated['order_id'] : null,
                $idempotencyKey
            );

            $tx = $result['payment_transaction'];

            return response()->json([
                'success' => true,
                'message' => $result['idempotent_replay']
                    ? 'Pembayaran wallet sudah diproses sebelumnya (idempotent).'
                    : 'Pembayaran order menggunakan wallet berhasil.',
                'data' => [
                    'payment_transaction' => [
                        'id' => $tx->id,
                        'reference' => $tx->reference,
                        'payment_method' => $tx->payment_method,
                        'amount' => (int) $tx->total_amount,
                        'status' => $tx->status,
                        'mode' => $tx->mode,
                        'paid_at' => $tx->paid_at?->toIso8601String(),
                    ],
                    'wallet_available_balance' => isset($result['wallet'])
                        ? (int) ($result['wallet']->balanceRecord?->available_balance ?? $result['wallet']->available_balance)
                        : null,
                    'shipment_id' => $tx->shipment_id,
                    'order_id' => $tx->order_id,
                    'idempotent_replay' => $result['idempotent_replay'],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            $errorCode = 'PAYMENT_FAILED';
            $status = 422;

            if (str_starts_with($msg, 'INSUFFICIENT_BALANCE:')) {
                $errorCode = 'INSUFFICIENT_BALANCE';
                $msg = substr($msg, strlen('INSUFFICIENT_BALANCE:'));
            } elseif (str_contains($msg, 'akses')) {
                $errorCode = 'FORBIDDEN';
                $status = 403;
            }

            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'message' => $msg,
            ], $status);
        }
    }
}
