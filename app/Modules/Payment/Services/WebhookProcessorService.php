<?php

namespace App\Modules\Payment\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\Withdrawal;
use App\Modules\Payment\Jobs\PaymentWebhookJob;
use App\Modules\Payment\Models\PaymentWebhook;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\TopUp\Services\TopupService;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class WebhookProcessorService
{
    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly TopupService $topupService,
        private readonly PayoutService $payoutService
    ) {}

    /**
     * Validate signature, store idempotent webhook event, and enqueue/process.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     * @return array{webhook: PaymentWebhook, duplicate: bool, signature_valid: bool}
     */
    public function receiveWebhook(
        string $providerCode,
        string $category, // payment | payout
        array $headers,
        string $rawBody,
        array $payload
    ): array {
        $providerCode = strtolower($providerCode);

        $signatureValid = $category === 'payout'
            ? $this->providerResolver->resolveWebhookPayoutProvider($providerCode)->verifyPayoutWebhookSignature($headers, $rawBody, $payload)
            : $this->providerResolver->resolveWebhookPaymentProvider($providerCode)->verifyWebhookSignature($headers, $rawBody, $payload);

        $parsed = $category === 'payout'
            ? $this->providerResolver->resolveWebhookPayoutProvider($providerCode)->parsePayoutWebhook($payload)
            : $this->providerResolver->resolveWebhookPaymentProvider($providerCode)->parsePaymentWebhook($payload);

        $eventType = (string) ($parsed['event_type'] ?? "{$providerCode}.{$category}");
        $externalId = (string) ($parsed['external_id'] ?? '');
        $providerReference = (string) ($parsed['provider_reference'] ?? '');

        $dedupeSeed = implode('|', [
            $providerCode,
            $category,
            $signatureValid ? 'valid' : 'invalid',
            $eventType,
            $externalId,
            $providerReference,
            (string) ($parsed['status'] ?? ''),
            sha1($rawBody !== '' ? $rawBody : json_encode($payload)),
        ]);
        $webhookHash = hash('sha256', $dedupeSeed);

        $existing = PaymentWebhook::where('webhook_hash', $webhookHash)->first();
        if ($existing) {
            return [
                'webhook' => $existing,
                'duplicate' => true,
                'signature_valid' => $signatureValid,
            ];
        }

        // Strip secret header values before persistence
        $safeHeaders = [];
        foreach ($headers as $k => $v) {
            $lower = strtolower((string) $k);
            if (in_array($lower, ['x-callback-token', 'authorization'], true)) {
                $safeHeaders[$k] = '[REDACTED]';
            } else {
                $safeHeaders[$k] = $v;
            }
        }

        $webhook = PaymentWebhook::create([
            'provider' => $providerCode,
            'event_type' => $eventType,
            'external_id' => $externalId ?: null,
            'provider_reference' => $providerReference ?: null,
            'webhook_hash' => $webhookHash,
            'signature_valid' => $signatureValid,
            'processing_status' => $signatureValid ? 'queued' : 'rejected_invalid_signature',
            'headers' => $safeHeaders,
            'payload' => $payload,
        ]);

        if ($signatureValid) {
            PaymentWebhookJob::dispatchSync($webhook->id, $category);
            $webhook = $webhook->fresh() ?? $webhook;
        }

        return [
            'webhook' => $webhook,
            'duplicate' => false,
            'signature_valid' => $signatureValid,
        ];
    }

    public function processStoredWebhook(int $webhookId, string $category = 'payment'): PaymentWebhook
    {
        return DB::transaction(function () use ($webhookId, $category) {
            $webhook = PaymentWebhook::where('id', $webhookId)->lockForUpdate()->firstOrFail();

            if (! $webhook->signature_valid) {
                $webhook->processing_status = 'rejected_invalid_signature';
                $webhook->save();

                return $webhook;
            }

            if ($webhook->processing_status === 'processed') {
                return $webhook;
            }

            $payload = $webhook->payload ?? [];

            try {
                if ($category === 'payout') {
                    $this->handlePayoutEvent($webhook->provider, $payload);
                } else {
                    $this->handlePaymentEvent($webhook->provider, $payload);
                }

                $webhook->processing_status = 'processed';
                $webhook->processed_at = now();
                $webhook->error_message = null;
                $webhook->save();
            } catch (\Throwable $e) {
                $webhook->processing_status = 'failed';
                $webhook->error_message = $e->getMessage();
                $webhook->save();
            }

            return $webhook->fresh();
        });
    }

    private function handlePaymentEvent(string $providerCode, array $payload): void
    {
        $parsed = $this->providerResolver->resolveWebhookPaymentProvider($providerCode)->parsePaymentWebhook($payload);
        $externalId = $parsed['external_id'];
        $providerRef = $parsed['provider_reference'];
        $status = $parsed['status'];

        if (Schema::hasTable('topup_transactions')) {
            $topup = TopupTransaction::query()
                ->when($externalId !== '', fn ($q) => $q->where('reference', $externalId))
                ->when($externalId === '' && $providerRef !== '', fn ($q) => $q->where('provider_reference', $providerRef))
                ->first();

            if (! $topup && $providerRef !== '') {
                $topup = TopupTransaction::where('provider_reference', $providerRef)->first();
            }

            if ($topup) {
                if ($status === 'paid') {
                    $this->topupService->markPaidAndSettle($topup, $providerRef ?: null, $payload);
                } elseif (in_array($status, ['failed', 'expired', 'canceled'], true)) {
                    $this->topupService->markFailedOrExpired($topup, $status, $parsed['failure_reason'] ?? "Webhook status {$status}");
                }

                return;
            }
        }

        // Tangani juga pembayaran checkout pesanan (tabel payments) dari Xendit
        $orderPayment = Payment::query()
            ->when($providerRef !== '', fn ($q) => $q->where('payment_id', $providerRef))
            ->when($providerRef === '' && $externalId !== '', fn ($q) => $q->where('payment_id', $externalId))
            ->first();

        if (! $orderPayment && $externalId !== '') {
            $orderPayment = Payment::where('payment_id', $externalId)
                ->orWhere('metadata->xendit_reference_id', $externalId)
                ->first();
        }

        if (! $orderPayment) {
            return;
        }

        if (in_array($orderPayment->status, ['paid', 'canceled', 'expired', 'failed'], true)) {
            return;
        }

        if ($status === 'paid') {
            $orderPayment->update([
                'status' => 'paid',
                'paid_at' => $orderPayment->paid_at ?? now(),
                'callback_data' => $payload,
            ]);

            if ($orderPayment->order_id && Schema::hasTable('orders')) {
                $orderUpdates = [];
                if (Schema::hasColumn('orders', 'payment_status')) {
                    $orderUpdates['payment_status'] = 'paid';
                }
                if (! empty($orderUpdates)) {
                    Order::where('id', $orderPayment->order_id)->update($orderUpdates);
                }
            }
            if ($orderPayment->shipment_id && Schema::hasTable('shipments')) {
                Shipment::where('id', $orderPayment->shipment_id)->update(['status' => 'confirmed']);
            }
        } elseif (in_array($status, ['failed', 'expired', 'canceled'], true)) {
            $mapped = $status === 'canceled' ? 'canceled' : ($status === 'expired' ? 'expired' : 'failed');
            $orderPayment->update([
                'status' => $mapped,
                'callback_data' => $payload,
            ]);
        }
    }

    private function handlePayoutEvent(string $providerCode, array $payload): void
    {
        $parsed = $this->providerResolver->resolveWebhookPayoutProvider($providerCode)->parsePayoutWebhook($payload);
        $externalId = $parsed['external_id'];
        $providerRef = $parsed['provider_reference'];
        $status = $parsed['status'];

        $payout = PayoutTransaction::query()
            ->when($externalId !== '', fn ($q) => $q->where('reference', $externalId))
            ->when($externalId === '' && $providerRef !== '', fn ($q) => $q->where('provider_reference', $providerRef))
            ->first();

        if (! $payout && $providerRef !== '') {
            $payout = PayoutTransaction::where('provider_reference', $providerRef)->first();
        }

        if ($payout) {
            if ($status === 'completed') {
                $this->payoutService->markPayoutSucceeded($payout, $providerRef ?: $payout->provider_reference);
            } elseif (in_array($status, ['failed', 'reversed'], true)) {
                $this->payoutService->markPayoutFailedAndRelease(
                    $payout,
                    (string) ($parsed['failure_code'] ?? strtoupper($status)),
                    (string) ($parsed['failure_reason'] ?? "Payout {$status} reported via webhook")
                );
            }

            return;
        }

        // Tangani juga penarikan kurir di tabel legacy `withdrawals` yang disalurkan melalui Xendit
        if (Schema::hasTable('withdrawals')) {
            $legacyRef = $providerRef !== '' ? $providerRef : $externalId;
            $legacy = $legacyRef !== ''
                ? Withdrawal::where('provider_reference', $legacyRef)
                    ->when($externalId !== '', fn ($q) => $q->orWhere('idempotency_key', $externalId))
                    ->first()
                : null;

            if ($legacy) {
                $withdrawalStatus = match ($status) {
                    'completed' => 'success',
                    'failed', 'reversed' => 'failed',
                    default => 'processing',
                };
                app(WithdrawalService::class)->handleStatusUpdate(
                    $legacyRef,
                    $withdrawalStatus,
                    $parsed['failure_reason'] ?? null
                );

                return;
            }
        }

        throw new InvalidArgumentException("Matching payout not found for webhook ({$externalId} / {$providerRef}).");
    }
}
