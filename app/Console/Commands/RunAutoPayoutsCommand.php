<?php

namespace App\Console\Commands;

use App\Modules\Payment\Jobs\ReconciliationJob;
use App\Modules\Payout\Services\PayoutService;
use Illuminate\Console\Command;

class RunAutoPayoutsCommand extends Command
{
    protected $signature = 'finance:run-auto-payouts {--reconcile : Also run double-entry ledger reconciliation}';

    protected $description = 'Settle mature courier earnings and trigger scheduled automatic courier payouts.';

    public function handle(PayoutService $payoutService): int
    {
        $result = $payoutService->runScheduledCourierAutoPayouts();

        $this->info("Released earnings: {$result['released_earnings']}, Scheduled payouts: {$result['scheduled_payouts']}");

        if ($this->option('reconcile')) {
            ReconciliationJob::dispatchSync();
            $this->info('Ledger reconciliation executed.');
        }

        return self::SUCCESS;
    }
}
