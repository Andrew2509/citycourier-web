<?php

namespace App\Modules\Payout\Jobs;

use App\Modules\Payout\Services\PayoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PayoutStatusCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $payoutTransactionId
    ) {}

    public function handle(PayoutService $payoutService): void
    {
        $payoutService->verifyPayoutStatusWithProvider($this->payoutTransactionId);
    }
}
