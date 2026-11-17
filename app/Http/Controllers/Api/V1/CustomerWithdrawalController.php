<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\PayoutService;
use App\Services\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomerWithdrawalController extends Controller
{
    public function __construct(
        private readonly PayoutService $payoutService,
        private readonly AccountSecurityService $accountSecurity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));

        $paginator = PayoutTransaction::where('user_id', $request->user()->id)
            ->where('owner_type', 'customer')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat penarikan dana pelanggan berhasil dimuat.',
            'data' => [
                'items' => collect($paginator->items())->map(fn (PayoutTransaction $p) => $p->toApiArray()),
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
            'amount' => ['required', 'integer', 'min:1000'],
            'destination_id' => ['required', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'transaction_pin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ]);

        $user = $request->user();
        if ($user->hasTransactionPin()) {
            $pin = (string) ($validated['transaction_pin'] ?? '');
            if ($pin === '') {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRANSACTION_PIN_REQUIRED',
                    'message' => 'Masukkan PIN transaksi 6 digit untuk menarik saldo.',
                ], 422);
            }

            $pinCheck = $this->accountSecurity->verifyPin($user, $pin, $request);
            if (! ($pinCheck['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error_code' => $pinCheck['code'] ?? 'PIN_INVALID',
                    'message' => $pinCheck['message'] ?? 'PIN transaksi salah.',
                    'attempts_left' => $pinCheck['attempts_left'] ?? null,
                    'retry_after' => $pinCheck['retry_after'] ?? null,
                ], (int) ($pinCheck['status_code'] ?? 422));
            }
        }

        $idempotencyKey = (string) ($request->header('Idempotency-Key')
            ?: ($validated['idempotency_key'] ?? Str::uuid()->toString()));

        try {
            $result = $this->payoutService->requestCustomerWithdrawal(
                $request->user(),
                (int) $validated['amount'],
                (int) $validated['destination_id'],
                $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'message' => 'Permintaan penarikan wallet berhasil diproses.',
                'data' => array_merge($result['payout']->toApiArray(), [
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
            if (str_starts_with($msg, 'CUSTOMER_WITHDRAWAL_DISABLED_IN_PRODUCTION:')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'CUSTOMER_WITHDRAWAL_DISABLED_IN_PRODUCTION',
                    'message' => substr($msg, strlen('CUSTOMER_WITHDRAWAL_DISABLED_IN_PRODUCTION:')),
                ], 403);
            }
            if (str_starts_with($msg, 'INSUFFICIENT_BALANCE:')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INSUFFICIENT_BALANCE',
                    'message' => substr($msg, strlen('INSUFFICIENT_BALANCE:')),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'WITHDRAWAL_FAILED',
                'message' => $msg,
            ], 422);
        }
    }
}
