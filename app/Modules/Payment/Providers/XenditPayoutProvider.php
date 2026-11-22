<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PayoutProviderInterface;
use App\Modules\Payout\Services\AccountInquiryService;
use App\Modules\Payout\Services\XenditPayoutService;

class XenditPayoutProvider implements PayoutProviderInterface
{
    public function __construct(
        private readonly XenditPayoutService $payoutService,
        private readonly AccountInquiryService $accountInquiryService
    ) {}

    public function providerCode(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        return $this->payoutService->isConfigured();
    }

    public function validateDestination(string $channelCode, string $accountNumber, string $accountHolderName): array
    {
        return $this->payoutService->validateDestination($channelCode, $accountNumber, $accountHolderName);
    }

    public function inquireDestination(string $destinationType, string $channelCode, string $accountNumber): array
    {
        return $this->accountInquiryService->inquire($destinationType, $channelCode, $accountNumber);
    }

    public function createPayout(array $payload): array
    {
        return $this->payoutService->createPayout($payload);
    }

    public function getPayoutStatus(string $providerReference, ?string $referenceId = null): array
    {
        return $this->payoutService->getPayoutStatus($providerReference, $referenceId);
    }

    public function verifyPayoutWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        return $this->payoutService->verifyWebhookSignature($headers, $rawBody, $payload);
    }

    public function parsePayoutWebhook(array $payload): array
    {
        return $this->payoutService->parsePayoutWebhook($payload);
    }
}
