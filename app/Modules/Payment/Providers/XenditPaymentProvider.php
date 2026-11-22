<?php

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PaymentProviderInterface;
use App\Modules\Payment\Services\XenditPaymentService;

class XenditPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly XenditPaymentService $paymentService
    ) {}

    public function providerCode(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        return $this->paymentService->isConfigured();
    }

    public function availableChannels(string $mode): array
    {
        return $this->paymentService->availableChannels($mode);
    }

    public function createTopupPayment(array $payload): array
    {
        return $this->paymentService->createPaymentRequest($payload);
    }

    public function getPaymentStatus(string $providerReference): array
    {
        return $this->paymentService->getPaymentStatus($providerReference);
    }

    public function verifyWebhookSignature(array $headers, string $rawBody, array $payload): bool
    {
        return $this->paymentService->verifyWebhookSignature($headers, $rawBody, $payload);
    }

    public function parsePaymentWebhook(array $payload): array
    {
        return $this->paymentService->parsePaymentWebhook($payload);
    }
}
