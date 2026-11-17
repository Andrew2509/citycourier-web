<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Wallet;
use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Earnings\Services\CourierEarningService;
use App\Modules\FinancialReports\Services\AdminFinanceDashboardService;
use App\Modules\FinancialReports\Services\FinancialReportExportService;
use App\Modules\Ledger\Services\LedgerReconciliationService;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payment\Models\PaymentWebhook;
use App\Modules\Payment\Providers\ProviderResolver;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Payout\Services\PayoutService;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\Refund\Services\RefundService;
use App\Modules\TopUp\Models\TopupTransaction;
use App\Modules\TopUp\Services\TopupService;
use App\Modules\Wallet\Models\PayoutDestination;
use App\Modules\Wallet\Models\WalletAuditLog;
use App\Modules\Wallet\Services\WalletFreezeService;
use App\Modules\Wallet\Services\WalletLedgerManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(
        private readonly AdminFinanceDashboardService $dashboardService,
        private readonly FinancialReportExportService $exportService,
        private readonly WalletFreezeService $freezeService,
        private readonly WalletLedgerManager $walletManager,
        private readonly TopupService $topupService,
        private readonly CourierEarningService $earningService,
        private readonly PayoutService $payoutService,
        private readonly RefundService $refundService,
        private readonly LedgerReconciliationService $reconciliationService,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function index(): View
    {
        $metrics = $this->dashboardService->getDashboardMetrics();

        return view('admin.finance.index', compact('metrics'));
    }

    public function wallets(Request $request): View
    {
        $type = $request->string('wallet_type')->toString();
        $status = $request->string('status')->toString();

        $query = Wallet::with(['user', 'courier.user', 'balanceRecord'])->orderByDesc('id');
        if ($type !== '' && $type !== 'all') {
            $query->where('wallet_type', $type);
        }
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $wallets = $query->paginate(20)->withQueryString();
        $mode = $this->providerResolver->currentMode();

        return view('admin.finance.wallets', compact('wallets', 'type', 'status', 'mode'));
    }

    public function freezeWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'freeze_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $admin = Auth::user();
        $this->freezeService->freeze(
            $wallet,
            $admin,
            $validated['freeze_reason'],
            $request->ip(),
            $request->userAgent()
        );

        return back()->with('success', "Wallet #{$wallet->id} berhasil dibekukan.");
    }

    public function unfreezeWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $admin = Auth::user();
        $this->freezeService->unfreeze(
            $wallet,
            $admin,
            $request->string('notes')->toString() ?: 'Dibuka oleh admin',
            $request->ip(),
            $request->userAgent()
        );

        return back()->with('success', "Wallet #{$wallet->id} berhasil diaktifkan kembali.");
    }

    public function topups(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = TopupTransaction::with(['user', 'wallet'])->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $topups = $query->paginate(20)->withQueryString();
        $mode = $this->providerResolver->currentMode();

        return view('admin.finance.topups', compact('topups', 'status', 'mode'));
    }

    public function simulateTopupPaid(TopupTransaction $topup): RedirectResponse
    {
        try {
            $this->topupService->simulatePaid($topup);

            return back()->with('success', "Top-up {$topup->reference} berhasil disimulasikan lunas.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function payments(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = PaymentTransaction::with(['user', 'shipment', 'order'])->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $payments = $query->paginate(20)->withQueryString();

        return view('admin.finance.payments', compact('payments', 'status'));
    }

    public function withdrawals(Request $request): View
    {
        $withdrawals = PayoutTransaction::with(['user', 'destination'])
            ->where('owner_type', 'customer')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $mode = $this->providerResolver->currentMode();
        $withdrawalEnabled = $this->providerResolver->isCustomerWithdrawalEnabled();

        return view('admin.finance.withdrawals', compact('withdrawals', 'mode', 'withdrawalEnabled'));
    }

    public function earnings(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = CourierEarning::with(['courier.user', 'order'])->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $earnings = $query->paginate(20)->withQueryString();
        $commissionRate = $this->earningService->commissionRatePercent();
        $waitingMinutes = $this->earningService->settlementWaitingMinutes();

        return view('admin.finance.earnings', compact('earnings', 'status', 'commissionRate', 'waitingMinutes'));
    }

    public function releaseMatureEarnings(): RedirectResponse
    {
        $count = $this->earningService->releaseMatureEarnings();

        return back()->with('success', "{$count} pendapatan kurir yang matang berhasil dilepas ke saldo tersedia.");
    }

    public function payouts(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = PayoutTransaction::with(['user', 'courier.user', 'destination'])
            ->where('owner_type', 'courier')
            ->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $payouts = $query->paginate(20)->withQueryString();
        $minPayout = $this->payoutService->minCourierPayoutIdr();
        $autoEnabled = $this->payoutService->isCourierAutoPayoutEnabled();

        return view('admin.finance.payouts', compact('payouts', 'status', 'minPayout', 'autoEnabled'));
    }

    public function triggerAutoPayouts(): RedirectResponse
    {
        $res = $this->payoutService->runScheduledCourierAutoPayouts();

        return back()->with('success', "Scheduler selesai: {$res['released_earnings']} earnings dirilis, {$res['scheduled_payouts']} auto-payout dijadwalkan.");
    }

    public function verifyPayout(PayoutTransaction $payout): RedirectResponse
    {
        $updated = $this->payoutService->verifyPayoutStatusWithProvider($payout->id);

        return back()->with('success', "Status payout {$updated->reference} diperiksa: {$updated->status}.");
    }

    public function refunds(Request $request): View
    {
        $status = $request->string('status')->toString();
        $query = RefundTransaction::with(['user', 'shipment', 'reviewer'])->orderByDesc('id');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $refunds = $query->paginate(20)->withQueryString();

        return view('admin.finance.refunds', compact('refunds', 'status'));
    }

    public function approveRefund(Request $request, RefundTransaction $refund): RedirectResponse
    {
        try {
            $this->refundService->approveAndProcess(
                $refund,
                Auth::user(),
                $request->string('review_notes')->toString() ?: 'Disetujui admin & dikreditkan ke City Wallet'
            );

            return back()->with('success', "Refund {$refund->reference} disetujui dan dikreditkan ke wallet pelanggan.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejectRefund(Request $request, RefundTransaction $refund): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->refundService->rejectRefund($refund, Auth::user(), $validated['review_notes']);

            return back()->with('success', "Refund {$refund->reference} ditolak.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destinations(): View
    {
        $destinations = PayoutDestination::with(['user', 'courier'])->orderByDesc('id')->paginate(25);

        return view('admin.finance.destinations', compact('destinations'));
    }

    public function settings(): View
    {
        $config = [
            'mode' => $this->providerResolver->currentMode(),
            'active_payment_provider' => $this->providerResolver->activePaymentProviderCode(),
            'active_payout_provider' => $this->providerResolver->activePayoutProviderCode(),
            'commission_rate_percent' => $this->earningService->commissionRatePercent(),
            'settlement_waiting_minutes' => $this->earningService->settlementWaitingMinutes(),
            'min_payout_idr' => $this->payoutService->minCourierPayoutIdr(),
            'auto_payout_enabled' => $this->payoutService->isCourierAutoPayoutEnabled(),
            'customer_withdrawal_enabled' => $this->providerResolver->isCustomerWithdrawalEnabled(),
            'regulatory_approved' => (bool) config('payment_wallet.customer_withdrawal_regulatory_approved', false),
            'xendit_configured' => trim((string) config('payment_wallet.xendit.secret_key', '')) !== '',
            'doku_configured' => trim((string) config('payment_wallet.doku.client_id', '')) !== '',
        ];

        return view('admin.finance.settings', compact('config'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_wallet_mode' => ['required', 'in:demo,sandbox,production'],
            'payment_active_provider' => ['required', 'in:mock,xendit,doku'],
            'payout_active_provider' => ['required', 'in:mock,xendit'],
            'courier_commission_rate_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'courier_settlement_waiting_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'courier_min_payout_idr' => ['required', 'integer', 'min:5000', 'max:10000000'],
            'courier_auto_payout_enabled' => ['nullable', 'boolean'],
            'customer_withdrawal_enabled_in_sandbox' => ['nullable', 'boolean'],
        ]);

        $oldValues = [
            'payment_wallet_mode' => $this->providerResolver->currentMode(),
            'payment_active_provider' => $this->providerResolver->activePaymentProviderCode(),
            'payout_active_provider' => $this->providerResolver->activePayoutProviderCode(),
            'courier_commission_rate_percent' => $this->earningService->commissionRatePercent(),
            'courier_min_payout_idr' => $this->payoutService->minCourierPayoutIdr(),
        ];

        Setting::set('payment_wallet_mode', $validated['payment_wallet_mode'], 'finance');
        Setting::set('payment_active_provider', $validated['payment_active_provider'], 'finance');
        Setting::set('payout_active_provider', $validated['payout_active_provider'], 'finance');
        Setting::set('courier_commission_rate_percent', (string) $validated['courier_commission_rate_percent'], 'finance');
        Setting::set('courier_settlement_waiting_minutes', (string) $validated['courier_settlement_waiting_minutes'], 'finance');
        Setting::set('courier_min_payout_idr', (string) $validated['courier_min_payout_idr'], 'finance');
        Setting::set('courier_auto_payout_enabled', $request->boolean('courier_auto_payout_enabled') ? '1' : '0', 'finance');
        Setting::set('customer_withdrawal_enabled_in_sandbox', $request->boolean('customer_withdrawal_enabled_in_sandbox') ? '1' : '0', 'finance');

        $this->walletManager->writeAuditLog(
            Auth::id(),
            'admin',
            null,
            'finance_settings_updated',
            null,
            null,
            $oldValues,
            $validated,
            'Admin updated payment & wallet settings',
            $request->ip(),
            $request->userAgent()
        );

        return back()->with('success', 'Pengaturan Payment, Komisi & Auto-Payout berhasil disimpan.');
    }

    public function reconciliation(): View
    {
        $report = $this->reconciliationService->runReconciliation();
        $recentWebhooks = PaymentWebhook::orderByDesc('id')->limit(15)->get();

        return view('admin.finance.reconciliation', compact('report', 'recentWebhooks'));
    }

    public function runReconciliationNow(Request $request): RedirectResponse
    {
        $report = $this->reconciliationService->runReconciliation();

        $this->walletManager->writeAuditLog(
            Auth::id(),
            'admin',
            null,
            'manual_reconciliation_run',
            null,
            null,
            null,
            [
                'is_clean' => $report['is_clean'],
                'imbalance_amount' => $report['imbalance_amount'],
                'mismatched_wallets_count' => $report['mismatched_wallets_count'],
            ],
            'Manual reconciliation executed from Admin dashboard',
            $request->ip(),
            $request->userAgent()
        );

        return back()->with(
            $report['is_clean'] ? 'success' : 'error',
            $report['is_clean']
                ? 'Rekonsiliasi double-entry SEIMBANG (0 selisih pada '.$report['wallets_checked'].' wallet).'
                : 'Ditemukan ketidaksesuaian saldo pada rekonsiliasi!'
        );
    }

    public function reports(Request $request): View
    {
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;
        $modeFilter = $request->string('mode')->toString() ?: null;

        $report = $this->dashboardService->buildPeriodReport($from, $to, $modeFilter);

        return view('admin.finance.reports', compact('report'));
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $type = $request->string('type')->toString() ?: 'ledger';
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        return $this->exportService->streamCsv($type, $from, $to);
    }

    public function auditLogs(): View
    {
        $logs = WalletAuditLog::with(['actor', 'wallet'])->orderByDesc('id')->paginate(30);

        return view('admin.finance.audit-logs', compact('logs'));
    }
}
