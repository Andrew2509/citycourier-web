<?php

namespace App\Modules\Payment\Contracts;

interface PayoutProviderInterface
{
    public function providerCode(): string;

    public function isConfigured(): bool;

    /**
     * Validate bank / e-wallet destination format or via provider lookup when supported.
     *
     * @return array{valid: bool, account_holder_name: string|null, message: string|null}
     */
    public function validateDestination(string $channelCode, string $accountNumber, string $accountHolderName): array;

    /**
     * Look up registered account holder name for bank or e-wallet destination.
     *
     * @return array{
     *   supported: bool,
     *   found: bool,
     *   account_holder_name: string|null,
     *   message: string|null,
     *   provider: string
     * }
     */
    public function inquireDestination(string $destinationType, string $channelCode, string $accountNumber): array;

    /**
     * Submit idempotent money-out payout request.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayout(array $payload): array;

    /**
     * Query payout status by provider reference or merchant reference_id before retrying timeouts.
     *
     * @return array<string, mixed>
     */
    public function getPayoutStatus(string $providerReference, ?string $referenceId = null): array;

    /**
     * Verify incoming payout webhook signature / callback token.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayoutWebhookSignature(array $headers, string $rawBody, array $payload): bool;

    /**
     * Normalize payout webhook event.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function parsePayoutWebhook(array $payload): array;
}
