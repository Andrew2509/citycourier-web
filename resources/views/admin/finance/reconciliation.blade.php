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
            <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-xs font-semibold text-white hover:bg-orange-700">
                Jalankan Rekonsiliasi Sekarang
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 mb-5 text-sm">
        <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Status Global</div>
            <div class="font-bold {{ $report['is_clean'] ? 'text-emerald-700' : 'text-red-600' }}">
                {{ $report['is_clean'] ? 'CLEAN / SEIMBANG' : 'IMBALANCE DETECTED' }}
            </div>
        </div>
        <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Total Debit Jurnal</div>
            <div class="font-bold">Rp {{ number_format($report['total_ledger_debit'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Total Kredit Jurnal</div>
            <div class="font-bold">Rp {{ number_format($report['total_ledger_credit'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-slate-50 p-3">
            <div class="text-xs text-slate-500">Wallet Mismatch</div>
            <div class="font-bold">{{ $report['mismatched_wallets_count'] }} / {{ $report['wallets_checked'] }}</div>
        </div>
    </div>

    <h3 class="text-xs font-semibold uppercase text-slate-500 mb-2">Neraca Saldo Akun Ledger (Chart of Accounts)</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Account Code</th>
                    <th class="py-2 pr-3 text-right">Total Debit</th>
                    <th class="py-2 pr-3 text-right">Total Credit</th>
                    <th class="py-2 text-right">Net (Credit - Debit)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($report['account_totals'] as $code => $row)
                    <tr>
                        <td class="py-2 pr-3 font-mono font-medium">{{ $code }}</td>
                        <td class="py-2 pr-3 text-right">Rp {{ number_format($row['debit'], 0, ',', '.') }}</td>
                        <td class="py-2 pr-3 text-right">Rp {{ number_format($row['credit'], 0, ',', '.') }}</td>
                        <td class="py-2 text-right font-semibold">Rp {{ number_format($row['net_credit_minus_debit'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">Belum ada saldo akun ledger.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-3 text-sm font-semibold text-slate-900">Webhook Masuk Terbaru</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Provider</th>
                    <th class="py-2 pr-3">Event</th>
                    <th class="py-2 pr-3">External ID</th>
                    <th class="py-2 pr-3">Signature</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentWebhooks as $wh)
                    <tr>
                        <td class="py-2 pr-3 uppercase font-semibold">{{ $wh->provider }}</td>
                        <td class="py-2 pr-3">{{ $wh->event_type }}</td>
                        <td class="py-2 pr-3 font-mono">{{ $wh->external_id ?? '-' }}</td>
                        <td class="py-2 pr-3">{{ $wh->signature_valid ? 'VALID' : 'INVALID' }}</td>
                        <td class="py-2 pr-3 uppercase">{{ $wh->processing_status }}</td>
                        <td class="py-2 text-slate-500">{{ $wh->created_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Belum ada webhook diterima.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
