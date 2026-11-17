<?php

namespace App\Modules\FinancialReports\Services;

use App\Modules\Earnings\Models\CourierEarning;
use App\Modules\Ledger\Models\WalletLedgerEntry;
use App\Modules\Payment\Models\PaymentTransaction;
use App\Modules\Payout\Models\PayoutTransaction;
use App\Modules\Refund\Models\RefundTransaction;
use App\Modules\TopUp\Models\TopupTransaction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportService
{
    public function streamCsv(string $type, ?string $fromDate = null, ?string $toDate = null): StreamedResponse
    {
        $filename = 'citycourier_finance_'.$type.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($type, $fromDate, $toDate) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            switch ($type) {
                case 'topups':
                    fputcsv($handle, ['ID', 'Reference', 'User ID', 'Provider', 'Mode', 'Simulated', 'Channel', 'Amount (IDR)', 'Status', 'Paid At', 'Created At']);
                    $q = TopupTransaction::query()->orderByDesc('id');
                    if ($fromDate) {
                        $q->whereDate('created_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('created_at', '<=', $toDate);
                    }
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->reference, $row->user_id, $row->provider, $row->mode,
                            $row->is_simulated ? 'YES' : 'NO', $row->channel_code, $row->amount,
                            $row->status, $row->paid_at?->toIso8601String(), $row->created_at?->toIso8601String(),
                        ]);
                    }
                    break;

                case 'payouts':
                    fputcsv($handle, ['ID', 'Reference', 'Owner Type', 'User ID', 'Type', 'Provider', 'Mode', 'Channel', 'Destination', 'Amount (IDR)', 'Fee', 'Net (IDR)', 'Status', 'Provider Ref', 'Completed At']);
                    $q = PayoutTransaction::query()->orderByDesc('id');
                    if ($fromDate) {
                        $q->whereDate('created_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('created_at', '<=', $toDate);
                    }
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->reference, $row->owner_type, $row->user_id, $row->payout_type,
                            $row->provider, $row->mode, $row->channel_code, $row->account_number_masked,
                            $row->amount, $row->fee, $row->net_amount, $row->status,
                            $row->provider_reference, $row->completed_at?->toIso8601String(),
                        ]);
                    }
                    break;

                case 'earnings':
                    fputcsv($handle, ['ID', 'Reference', 'Courier ID', 'Order ID', 'Gross (IDR)', 'Commission %', 'Commission (IDR)', 'Net (IDR)', 'Status', 'Available At', 'Paid At']);
                    $q = CourierEarning::query()->orderByDesc('id');
                    if ($fromDate) {
                        $q->whereDate('created_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('created_at', '<=', $toDate);
                    }
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->reference, $row->courier_id, $row->order_id,
                            $row->gross_amount, $row->commission_rate, $row->commission_amount,
                            $row->net_amount, $row->status, $row->available_at?->toIso8601String(),
                            $row->paid_at?->toIso8601String(),
                        ]);
                    }
                    break;

                case 'refunds':
                    fputcsv($handle, ['ID', 'Reference', 'User ID', 'Shipment ID', 'Method', 'Amount (IDR)', 'Status', 'Reason', 'Processed At']);
                    $q = RefundTransaction::query()->orderByDesc('id');
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->reference, $row->user_id, $row->shipment_id,
                            $row->refund_method, $row->amount, $row->status, $row->reason,
                            $row->processed_at?->toIso8601String(),
                        ]);
                    }
                    break;

                case 'payments':
                    fputcsv($handle, ['ID', 'Reference', 'User ID', 'Shipment ID', 'Method', 'Provider', 'Mode', 'Amount (IDR)', 'Status', 'Paid At']);
                    $q = PaymentTransaction::query()->orderByDesc('id');
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->reference, $row->user_id, $row->shipment_id,
                            $row->payment_method, $row->provider, $row->mode, $row->total_amount,
                            $row->status, $row->paid_at?->toIso8601String(),
                        ]);
                    }
                    break;

                default: // ledger
                    fputcsv($handle, ['ID', 'Journal ID', 'Reference', 'Wallet ID', 'Account Code', 'Entry Type', 'Amount (IDR)', 'Tx Type', 'Mode', 'Description', 'Created At']);
                    $q = WalletLedgerEntry::query()->orderByDesc('id');
                    if ($fromDate) {
                        $q->whereDate('created_at', '>=', $fromDate);
                    }
                    if ($toDate) {
                        $q->whereDate('created_at', '<=', $toDate);
                    }
                    foreach ($q->cursor() as $row) {
                        fputcsv($handle, [
                            $row->id, $row->journal_id, $row->journal_reference, $row->wallet_id,
                            $row->account_code, $row->entry_type, $row->amount, $row->transaction_type,
                            $row->mode, $row->description, $row->created_at?->toIso8601String(),
                        ]);
                    }
                    break;
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
