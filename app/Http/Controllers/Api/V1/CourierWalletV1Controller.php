<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\WalletTransaction;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Earnings\Services\CourierEarningService;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\DestinationService;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\Wallet\Models\PayoutDestination;
use App\Modules\Wallet\Services\WalletLedgerManager;
use App\Services\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CourierWalletV1Controller extends Controller
{
    public function __construct(
        private readonly WalletLedgerManager $walletManager,
        private readonly CourierEarningService $earningService,
        private readonly DestinationService $destinationService,
        private readonly PayoutService $payoutService,
        private readonly ProviderResolver $providerResolver,
        private readonly ?AccountSecurityService $accountSecurity = null
    ) {}

    public function show(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $this->earningService->syncDeliveredOrdersForCourier($courier);
        $wallet = $this->walletManager->getOrCreateCourierWallet($courier);
        $balance = $wallet->balanceRecord;
        $mode = $this->providerResolver->currentMode();
        $minPayout = $this->payoutService->minCourierPayoutIdr();
        $autoEnabled = $this->payoutService->isCourierAutoPayoutEnabled();

        $defaultDest = PayoutDestination::where('user_id', $request->user()->id)
            ->where('owner_type', 'courier')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        $available = (int) ($balance?->available_balance ?? round((float) $wallet->available_balance));

        $requireVerified = (bool) config('payment_wallet.courier_payout.require_verified_destination', false);

        return response()->json([
            'success' => true,
            'message' => 'Wallet kurir berhasil dimuat.',
            'data' => [
                'wallet_id' => $wallet->id,
                'courier_id' => $courier->id,
                'wallet_type' => 'courier',
                'currency' => $wallet->currency ?: 'IDR',
                'status' => $wallet->status,
                'is_frozen' => $wallet->isFrozen(),
                'freeze_reason' => $wallet->freeze_reason,
                'frozen_at' => $wallet->frozen_at ? Carbon::parse($wallet->frozen_at)->toIso8601String() : null,
                'available_balance' => $available,
                'pending_balance' => (int) ($balance?->pending_balance ?? round((float) $wallet->pending_balance)),
                'reserved_balance' => (int) ($balance?->reserved_balance ?? ($wallet->reserved_balance ?? 0)),
                'mode' => $mode,
                'is_sandbox_or_demo' => $mode !== 'production',
                'active_payout_provider' => $this->providerResolver->safePayoutProviderCode(),
                'security' => [
                    'has_transaction_pin' => $request->user()->hasTransactionPin(),
                    'biometric_enabled' => (bool) ($request->user()->getAttribute('biometric_enabled') ?? false),
                ],
                'earnings_summary' => $this->earningService->getCourierEarningsSummary($courier),
                'payout_policy' => [
                    'auto_payout_enabled' => $autoEnabled,
                    'provider_enabled' => ! $this->providerResolver->isPayoutProviderDisabled(),
                    'min_payout_idr' => $minPayout,
                    'max_payout_idr' => (int) config('payment_wallet.courier_payout.max_amount_idr', 5000000),
                    'fee_idr' => (int) config('payment_wallet.courier_payout.fee_idr', 0),
                    'schedule_cron' => (string) config('payment_wallet.courier_payout.schedule_cron', '0 * * * *'),
                    'eligible_for_next_auto_payout' => $autoEnabled
                        && ! $wallet->isFrozen()
                        && $available >= $minPayout
                        && $defaultDest !== null
                        && (! $requireVerified || $defaultDest->is_verified),
                ],
                'default_destination' => $defaultDest?->toApiArray(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $wallet = $this->walletManager->getOrCreateCourierWallet($courier);
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $query = WalletTransaction::where('wallet_id', $wallet->id)->orderByDesc('id');
        if ($type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat transaksi wallet kurir berhasil dimuat.',
            'data' => [
                'items' => collect($paginator->items())->map(fn (WalletTransaction $tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'direction' => $tx->direction ?? ((float) $tx->amount >= 0 ? 'credit' : 'debit'),
                    'amount' => (int) ($tx->amount_idr ?? abs((int) round((float) $tx->amount))),
                    'signed_amount' => (int) round((float) $tx->amount),
                    'balance_after' => $tx->balance_after !== null ? (int) $tx->balance_after : null,
                    'status' => $tx->status,
                    'reference_id' => $tx->reference ?? $tx->reference_id,
                    'description' => $tx->description,
                    'mode' => $tx->mode ?? 'sandbox',
                    'metadata' => $tx->metadata ?? [],
                    'created_at' => $tx->created_at?->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function transactionDetail(Request $request, int $id): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $wallet = $this->walletManager->getOrCreateCourierWallet($courier);
        $tx = WalletTransaction::where('wallet_id', $wallet->id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail transaksi kurir berhasil dimuat.',
            'data' => [
                'id' => $tx->id,
                'wallet_id' => $tx->wallet_id,
                'type' => $tx->type,
                'direction' => $tx->direction ?? ((float) $tx->amount >= 0 ? 'credit' : 'debit'),
                'amount' => (int) ($tx->amount_idr ?? abs((int) round((float) $tx->amount))),
                'balance_after' => $tx->balance_after !== null ? (int) $tx->balance_after : null,
                'status' => $tx->status,
                'reference_id' => $tx->reference ?? $tx->reference_id,
                'description' => $tx->description,
                'mode' => $tx->mode ?? 'sandbox',
                'metadata' => $tx->metadata ?? [],
                'created_at' => $tx->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $this->earningService->syncDeliveredOrdersForCourier($courier);
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));
        $status = $request->string('status')->toString();

        $query = CourierEarning::where('courier_id', $courier->id)->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan dan riwayat pendapatan kurir berhasil dimuat.',
            'data' => [
                'summary' => $this->earningService->getCourierEarningsSummary($courier),
                'items' => collect($paginator->items())->map(fn (CourierEarning $e) => $e->toApiArray()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $this->earningService->syncDeliveredOrdersForCourier($courier);
        $wallet = $this->walletManager->getOrCreateCourierWallet($courier);
        $perPage = min(50, max(1, (int) $request->integer('per_page', 15)));

        $paginator = PayoutTransaction::where('courier_id', $courier->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $defaultDest = PayoutDestination::where('user_id', $request->user()->id)
            ->where('owner_type', 'courier')
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        $minPayout = $this->payoutService->minCourierPayoutIdr();
        $available = (int) ($wallet->balanceRecord?->available_balance ?? round((float) $wallet->available_balance));
        $missing = [];
        if (! $this->payoutService->isCourierAutoPayoutEnabled()) {
            $missing[] = 'Auto-payout sementara dinonaktifkan oleh admin.';
        }
        if ($wallet->isFrozen()) {
            $missing[] = 'Wallet sedang dibekukan oleh admin.';
        }
        if ($available < $minPayout) {
            $missing[] = 'Saldo tersedia belum mencapai minimum payout Rp '.number_format($minPayout, 0, ',', '.').'.';
        }
        $requireVerified = (bool) config('payment_wallet.courier_payout.require_verified_destination', false);
        if (! $defaultDest || ($requireVerified && ! $defaultDest->is_verified)) {
            $missing[] = 'Tambahkan dan jadikan default rekening bank atau e-wallet tujuan.';
        }

        return response()->json([
            'success' => true,
            'message' => 'Riwayat payout kurir berhasil dimuat.',
            'data' => [
                'auto_payout_status' => [
                    'auto_payout_enabled' => $this->payoutService->isCourierAutoPayoutEnabled(),
                    'eligible_now' => count($missing) === 0,
                    'available_balance' => $available,
                    'reserved_balance' => (int) ($wallet->balanceRecord?->reserved_balance ?? ($wallet->reserved_balance ?? 0)),
                    'min_payout_idr' => $minPayout,
                    'schedule_cron' => (string) config('payment_wallet.courier_payout.schedule_cron', '0 * * * *'),
                    'default_destination' => $defaultDest?->toApiArray(),
                    'missing_requirements' => $missing,
                ],
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

    public function payoutDetail(Request $request, int $id): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $payout = PayoutTransaction::where('courier_id', $courier->id)->findOrFail($id);
        $earnings = CourierEarning::where('payout_transaction_id', $payout->id)->get();

        return response()->json([
            'success' => true,
            'message' => 'Detail payout kurir berhasil dimuat.',
            'data' => array_merge($payout->toApiArray(), [
                'earnings_count' => $earnings->count(),
                'earnings' => $earnings->map(fn (CourierEarning $e) => $e->toApiArray()),
            ]),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $payouts = PayoutTransaction::where('courier_id', $courier->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $notifications = $payouts->map(function (PayoutTransaction $p) {
            $title = match ($p->status) {
                'completed' => 'Payout Berhasil Dikirim',
                'failed', 'reversed' => 'Payout Gagal (Dana Dikembalikan)',
                'processing' => 'Payout Sedang Diproses',
                default => 'Payout Otomatis Dijadwalkan',
            };

            return [
                'id' => 'payout-'.$p->id,
                'payout_id' => $p->id,
                'reference' => $p->reference,
                'status' => $p->status,
                'title' => $title,
                'message' => sprintf(
                    'Rp %s ke %s (%s)%s',
                    number_format((int) $p->net_amount, 0, ',', '.'),
                    $p->channel_code,
                    $p->account_number_masked,
                    $p->failure_reason ? ' — '.$p->failure_reason : ''
                ),
                'mode' => $p->mode,
                'created_at' => ($p->completed_at ?? $p->failed_at ?? $p->created_at)?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi payout kurir berhasil dimuat.',
            'data' => [
                'items' => $notifications,
            ],
        ]);
    }

    public function destinations(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $items = $this->destinationService->listDestinations($request->user(), 'courier');

        return response()->json([
            'success' => true,
            'message' => 'Daftar rekening/e-wallet payout kurir berhasil dimuat.',
            'data' => [
                'items' => $items->map(fn (PayoutDestination $d) => $d->toApiArray()),
                'supported_channels' => $this->destinationService->supportedChannels(),
            ],
        ]);
    }

    public function storeDestination(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $validated = $request->validate([
            'destination_type' => ['required', 'string', 'in:bank,ewallet'],
            'channel_code' => ['required', 'string', 'max:64'],
            'channel_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:6', 'max:40'],
            'account_holder_name' => ['required', 'string', 'min:2', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        try {
            $dest = $this->destinationService->addDestination($request->user(), 'courier', $validated, $courier);

            return response()->json([
                'success' => true,
                'message' => 'Tujuan payout kurir berhasil ditambahkan.',
                'data' => $dest->toApiArray(),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function setDefaultDestination(Request $request, int $id): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $dest = PayoutDestination::where('user_id', $request->user()->id)
            ->where('owner_type', 'courier')
            ->findOrFail($id);

        $updated = $this->destinationService->setDefault($dest, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Rekening/e-wallet default berhasil diperbarui.',
            'data' => $updated->toApiArray(),
        ]);
    }

    public function inquireDestination(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $validated = $request->validate([
            'destination_type' => ['required', 'string', 'in:bank,ewallet'],
            'channel_code' => ['required', 'string', 'max:64'],
            'account_number' => ['required', 'string', 'min:6', 'max:40'],
        ]);

        try {
            $result = $this->destinationService->inquireDestination($request->user(), $validated);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Hasil pengecekan rekening dimuat.',
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function storeWithdrawal(Request $request): JsonResponse
    {
        $courier = $this->resolveCourier($request);
        if (! $courier) {
            return $this->missingCourierResponse();
        }

        $this->earningService->syncDeliveredOrdersForCourier($courier);

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

            $security = $this->accountSecurity ?? app(AccountSecurityService::class);
            $pinCheck = $security->verifyPin($user, $pin, $request);
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
            $result = $this->payoutService->requestCourierWithdrawal(
                $user,
                $courier,
                (int) $validated['amount'],
                (int) $validated['destination_id'],
                $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'message' => 'Permintaan penarikan saldo kurir berhasil diajukan.',
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

    private function resolveCourier(Request $request): ?Courier
    {
        return Courier::where('user_id', $request->user()->id)->first();
    }

    private function missingCourierResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'COURIER_PROFILE_REQUIRED',
            'message' => 'Akun Anda tidak memiliki profil kurir aktif.',
        ], 403);
    }
}
