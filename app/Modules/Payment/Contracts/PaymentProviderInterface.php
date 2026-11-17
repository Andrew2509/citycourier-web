<?php

namespace App\Modules\Payment\Contracts;

interface PaymentProviderInterface
{
    public function providerCode(): string;

    public function isConfigured(): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableChannels(string $mode): array;

    /**
     * Create a money-in payment request (VA, QRIS, E-Wallet).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTopupPayment(array $payload): array;

    /**
     * Query latest payment status from provider.
     *
     * @return array<string, mixed>
     */
    public function getPaymentStatus(string $providerReference): array;

    /**
     * Verify incoming webhook cryptographic signature / callback token.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $headers, string $rawBody, array $payload): bool;

    /**
     * Normalize incoming payment webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parsePaymentWebhook(array $payload): array;
}
