<?php

namespace App\Modules\Payment\Jobs;

use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconciliationJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        LedgerReconciliationService $reconciliationService,
        WalletLedgerManager $walletManager
    ): void {
        $report = $reconciliationService->runReconciliation();

        $walletManager->writeAuditLog(
            null,
            'system',
            null,
            'scheduled_reconciliation',
            null,
            null,
            null,
            [
                'is_clean' => $report['is_clean'],
                'wallets_checked' => $report['wallets_checked'],
                'mismatched_wallets_count' => $report['mismatched_wallets_count'],
                'imbalance_amount' => $report['imbalance_amount'],
            ],
            $report['is_clean'] ? 'Scheduled reconciliation clean' : 'Reconciliation discrepancy detected'
        );

        if (! $report['is_clean']) {
            Log::critical('City Courier Wallet Ledger Reconciliation Discrepancy Detected', $report);
        }
    }
}
