@extends('layouts.admin')

@section('title', 'Rekonsiliasi Double-Entry Ledger')

@section('content')
@include('admin.finance._nav')

<div class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Hasil Rekonsiliasi Ledger Double-Entry</h2>
            <p class="text-xs text-slate-500">Terakhir diperiksa: {{ $report['checked_at'] }}</p>
        </div>
        <form method="POST" action="{{ route('admin.finance.reconciliation.run') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">
                Jalankan Rekonsiliasi Sekarang
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-5 text-sm">
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Status Global</div>
            <div class="font-bold {{ $report['is_clean'] ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $report['is_clean'] ? 'CLEAN / SEIMBANG' : 'IMBALANCE DETECTED' }}
            </div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Total Debit Jurnal</div>
            <div class="font-bold">Rp {{ number_format($report['total_ledger_debit'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Total Kredit Jurnal</div>
            <div class="font-bold">Rp {{ number_format($report['total_ledger_credit'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Wallet Mismatch</div>
            <div class="font-bold">{{ $report['mismatched_wallets_count'] }} / {{ $report['wallets_checked'] }}</div>
        </div>
    </div>

    <h3 class="text-xs font-semibold uppercase text-slate-500 mb-2">Neraca Saldo Akun Ledger (Chart of Accounts)</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th">Account Code</th>
                    <th text-right">Total Debit</th>
                    <th text-right">Total Credit</th>
                    <th class="text-right">Net (Credit - Debit)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['account_totals'] as $code => $row)
                    <tr>
                        <td class="cell-mono">{{ $code }}</td>
                        <td text-right">Rp {{ number_format($row['debit'], 0, ',', '.') }}</td>
                        <td text-right">Rp {{ number_format($row['credit'], 0, ',', '.') }}</td>
                        <td text-right font-semibold">Rp {{ number_format($row['net_credit_minus_debit'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                                    @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">Webhook Masuk Terbaru</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th">Provider</th>
                    <th">Event</th>
                    <th">External ID</th>
                    <th">Signature</th>
                    <th">Status</th>
                    <th class="text-right">Waktu</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentWebhooks as $wh)
                    <tr>
                        <td uppercase font-semibold">{{ $wh->provider }}</td>
                        <td">{{ $wh->event_type }}</td>
                        <td font-mono">{{ $wh->external_id ?? '-' }}</td>
                        <td">{{ $wh->signature_valid ? 'VALID' : 'INVALID' }}</td>
                        <td uppercase">{{ $wh->processing_status }}</td>
                        <td text-slate-500">{{ $wh->created_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                                    @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
