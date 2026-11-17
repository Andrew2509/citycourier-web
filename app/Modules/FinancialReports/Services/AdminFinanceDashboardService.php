<?php

namespace App\Modules\FinancialReports\Services;

use App\Models\Wallet;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Models\PaymentWebhook;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\Wallet\Models\WalletAuditLog;

class AdminFinanceDashboardService
{
    public function __construct(
        private readonly ProviderResolver $providerResolver,
        private readonly LedgerReconciliationService $reconciliationService
    ) {}

    /**
     * Gather real database metrics for Admin Finance Dashboard.
     *
     * @return array<string, mixed>
     */
    public function getDashboardMetrics(): array
    {
        $customerAvailable = (int) Wallet::where('wallet_type', 'customer')->sum('available_balance');
        $courierAvailable = (int) Wallet::where(function ($q) {
            $q->where('wallet_type', 'courier')->orWhereNull('wallet_type');
        })->sum('available_balance');
        $courierPending = (int) Wallet::where(function ($q) {
            $q->where('wallet_type', 'courier')->orWhereNull('wallet_type');
        })->sum('pending_balance');
        $totalReserved = (int) Wallet::sum('reserved_balance');

        $topupsSettledSum = (int) TopupTransaction::whereIn('status', ['paid', 'settled'])->sum('amount');
        $topupsPendingCount = TopupTransaction::where('status', 'pending')->count();
        $topupsFailedCount = TopupTransaction::whereIn('status', ['failed', 'expired'])->count();

        $orderPaymentsSum = (int) PaymentTransaction::where('status', 'paid')->sum('total_amount');

        $customerWithdrawalsSum = (int) PayoutTransaction::where('owner_type', 'customer')
            ->where('status', 'completed')
            ->sum('amount');

        $courierPayoutsCompletedSum = (int) PayoutTransaction::where('owner_type', 'courier')
            ->where('status', 'completed')
            ->sum('amount');
        $payoutsProcessingCount = PayoutTransaction::whereIn('status', ['pending', 'processing'])->count();
        $payoutsFailedCount = PayoutTransaction::whereIn('status', ['failed', 'reversed'])->count();

        $commissionRevenueSum = (int) CourierEarning::sum('commission_amount');
        $courierPendingEarningsSum = (int) CourierEarning::where('status', 'pending')->sum('net_amount');

        $refundsPendingCount = RefundTransaction::whereIn('status', ['requested', 'approved'])->count();
        $refundsCompletedSum = (int) RefundTransaction::where('status', 'completed')->sum('amount');

        $frozenWalletsCount = Wallet::where(function ($q) {
            $q->where('status', 'frozen')->orWhereNotNull('frozen_at');
        })->count();

        $failedWebhooksCount = PaymentWebhook::whereIn('processing_status', ['failed', 'rejected_invalid_signature'])->count();

        return [
            'mode' => $this->providerResolver->currentMode(),
            'active_payment_provider' => $this->providerResolver->activePaymentProviderCode(),
            'active_payout_provider' => $this->providerResolver->activePayoutProviderCode(),
            'customer_withdrawal_enabled' => $this->providerResolver->isCustomerWithdrawalEnabled(),
            'wallets' => [
                'customer_available_idr' => $customerAvailable,
                'courier_available_idr' => $courierAvailable,
                'courier_pending_idr' => $courierPending,
                'total_reserved_idr' => $totalReserved,
                'frozen_count' => $frozenWalletsCount,
                'total_count' => Wallet::count(),
            ],
            'flows' => [
                'topups_settled_idr' => $topupsSettledSum,
                'topups_pending_count' => $topupsPendingCount,
                'topups_failed_count' => $topupsFailedCount,
                'order_payments_idr' => $orderPaymentsSum,
                'customer_withdrawals_idr' => $customerWithdrawalsSum,
                'courier_payouts_completed_idr' => $courierPayoutsCompletedSum,
                'payouts_processing_count' => $payoutsProcessingCount,
                'payouts_failed_count' => $payoutsFailedCount,
                'platform_commission_idr' => $commissionRevenueSum,
                'courier_pending_earnings_idr' => $courierPendingEarningsSum,
                'refunds_pending_count' => $refundsPendingCount,
                'refunds_completed_idr' => $refundsCompletedSum,
                'failed_webhooks_count' => $failedWebhooksCount,
            ],
            'reconciliation' => $this->reconciliationService->runReconciliation(),
            'recent_ledger' => WalletLedgerEntry::orderByDesc('id')->limit(12)->get(),
            'recent_audit_logs' => WalletAuditLog::with('actor')->orderByDesc('id')->limit(10)->get(),
        ];
    }

    /**
     * Build date-filtered financial report summary.
     *
     * @return array<string, mixed>
     */
    public function buildPeriodReport(?string $fromDate = null, ?string $toDate = null, ?string $modeFilter = null): array
    {
        $applyDateFilter = function ($query, string $column = 'created_at') use ($fromDate, $toDate) {
            if ($fromDate) {
                $query->whereDate($column, '>=', $fromDate);
            }
            if ($toDate) {
                $query->whereDate($column, '<=', $toDate);
            }
        };

        $topupsQuery = TopupTransaction::query()->whereIn('status', ['paid', 'settled']);
        $paymentsQuery = PaymentTransaction::query()->where('status', 'paid');
        $earningsQuery = CourierEarning::query();
        $payoutsQuery = PayoutTransaction::query()->where('status', 'completed');
        $refundsQuery = RefundTransaction::query()->where('status', 'completed');

        foreach ([$topupsQuery, $paymentsQuery, $earningsQuery, $payoutsQuery, $refundsQuery] as $q) {
            $applyDateFilter($q);
            if ($modeFilter && in_array($modeFilter, ['demo', 'sandbox', 'production'], true)) {
                $q->where('mode', $modeFilter);
            }
        }

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'mode' => $modeFilter ?: 'all',
            'summary' => [
                'topup_count' => (clone $topupsQuery)->count(),
                'topup_total_idr' => (int) $topupsQuery->sum('amount'),
                'wallet_payment_count' => (clone $paymentsQuery)->where('payment_method', 'wallet')->count(),
                'wallet_payment_total_idr' => (int) (clone $paymentsQuery)->where('payment_method', 'wallet')->sum('total_amount'),
                'earnings_count' => (clone $earningsQuery)->count(),
                'earnings_gross_idr' => (int) (clone $earningsQuery)->sum('gross_amount'),
                'earnings_commission_idr' => (int) (clone $earningsQuery)->sum('commission_amount'),
                'earnings_net_idr' => (int) $earningsQuery->sum('net_amount'),
                'payout_count' => (clone $payoutsQuery)->count(),
                'payout_total_idr' => (int) $payoutsQuery->sum('net_amount'),
                'refund_count' => (clone $refundsQuery)->count(),
                'refund_total_idr' => (int) $refundsQuery->sum('amount'),
            ],
        ];
    }
}
