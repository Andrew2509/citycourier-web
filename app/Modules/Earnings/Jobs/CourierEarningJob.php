<?php

namespace App\Modules\Earnings\Jobs;

use App\Modules\Earnings\Services\CourierEarningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CourierEarningJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $orderId
    ) {}

    public function handle(CourierEarningService $earningService): void
    {
        $earningService->recordDeliveredOrderEarning($this->orderId);
    }
}
