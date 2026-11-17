<?php

namespace App\Modules\Payment\Jobs;

use App\Modules\Payment\Services\WebhookProcessorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $webhookId,
        public readonly string $category = 'payment'
    ) {}

    public function handle(WebhookProcessorService $processor): void
    {
        $processor->processStoredWebhook($this->webhookId, $this->category);
    }
}
