<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Models\PaymentWebhook;

/**
 * Handler terpadu webhook Xendit (Payment & Payout) dengan verifikasi
 * signature `x-callback-token` dan idempotensi berbasis payload hash.
 */
class WebhookHandler
{
    public function __construct(
        private readonly WebhookProcessorService $processor
    ) {}

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     * @return array{webhook: PaymentWebhook, duplicate: bool, signature_valid: bool}
     */
    public function handleXenditPayment(array $headers, string $rawBody, array $payload): array
    {
        return $this->processor->receiveWebhook('xendit', 'payment', $headers, $rawBody, $payload);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     * @return array{webhook: PaymentWebhook, duplicate: bool, signature_valid: bool}
     */
    public function handleXenditPayout(array $headers, string $rawBody, array $payload): array
    {
        return $this->processor->receiveWebhook('xendit', 'payout', $headers, $rawBody, $payload);
    }

    public function replayStoredWebhook(int $webhookId, string $category = 'payment'): PaymentWebhook
    {
        return $this->processor->processStoredWebhook($webhookId, $category);
    }
}
