<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Jobs\PaymentWebhookJob;
use App\Modules\Payment\Models\PaymentWebhook;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\TopUp\Services\TopupService;
use Illuminate\Support\Facades\DB;
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
            PaymentWebhookJob::dispatch($webhook->id, $category)->afterCommit();
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

        $topup = TopupTransaction::query()
            ->when($externalId !== '', fn ($q) => $q->where('reference', $externalId))
            ->when($externalId === '' && $providerRef !== '', fn ($q) => $q->where('provider_reference', $providerRef))
            ->first();

        if (! $topup && $providerRef !== '') {
            $topup = TopupTransaction::where('provider_reference', $providerRef)->first();
        }

        if (! $topup) {
            return;
        }

        if ($status === 'paid') {
            $this->topupService->markPaidAndSettle($topup, $providerRef ?: null, $payload);
        } elseif (in_array($status, ['failed', 'expired'], true)) {
            $this->topupService->markFailedOrExpired($topup, $status, $parsed['failure_reason'] ?? "Webhook status {$status}");
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

        if (! $payout) {
            throw new InvalidArgumentException("Matching payout not found for webhook ({$externalId} / {$providerRef}).");
        }

        if ($status === 'completed') {
            $this->payoutService->markPayoutSucceeded($payout, $providerRef ?: $payout->provider_reference);
        } elseif (in_array($status, ['failed', 'reversed'], true)) {
            $this->payoutService->markPayoutFailedAndRelease(
                $payout,
                (string) ($parsed['failure_code'] ?? strtoupper($status)),
                (string) ($parsed['failure_reason'] ?? "Payout {$status} reported via webhook")
            );
        }
    }
}
