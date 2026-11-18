@extends('layouts.admin')

@section('title', 'Laporan Keuangan & Ekspor CSV')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm mb-6">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Dari Tanggal</label>
                <input type="date" name="from" value="{{ $report['from'] }}" class="form-input w-auto">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sampai Tanggal</label>
                <input type="date" name="to" value="{{ $report['to'] }}" class="form-input w-auto">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Mode</label>
                <select name="mode" class="form-select w-auto">
                    <option value="all" @selected($report['mode'] === 'all')>Semua Mode</option>
                    <option value="demo" @selected($report['mode'] === 'demo')>Demo</option>
                    <option value="sandbox" @selected($report['mode'] === 'sandbox')>Sandbox</option>
                    <option value="production" @selected($report['mode'] === 'production')>Production</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-700">Terapkan Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5 mb-6 text-sm">
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Top-Up Lunas ({{ $report['summary']['topup_count'] }})</div>
            <div class="mt-1 font-bold">Rp {{ number_format($report['summary']['topup_total_idr'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Order Wallet ({{ $report['summary']['wallet_payment_count'] }})</div>
            <div class="mt-1 font-bold">Rp {{ number_format($report['summary']['wallet_payment_total_idr'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Komisi Platform ({{ $report['summary']['earnings_count'] }})</div>
            <div class="mt-1 font-bold text-emerald-700">Rp {{ number_format($report['summary']['earnings_commission_idr'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Payout Sukses ({{ $report['summary']['payout_count'] }})</div>
            <div class="mt-1 font-bold">Rp {{ number_format($report['summary']['payout_total_idr'], 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg bg-cream-50 border border-slate-100 p-3">
            <div class="text-xs text-slate-500">Refund Selesai ({{ $report['summary']['refund_count'] }})</div>
            <div class="mt-1 font-bold text-rose-600">Rp {{ number_format($report['summary']['refund_total_idr'], 0, ',', '.') }}</div>
        </div>
    </div>

    <h3 class="text-xs font-semibold uppercase text-slate-500 mb-2">Unduh Laporan CSV</h3>
    <div class="flex flex-wrap gap-2">
        @foreach (['ledger' => 'Double-Entry Ledger', 'topups' => 'Top-Ups', 'payments' => 'Order Payments', 'earnings' => 'Courier Earnings', 'payouts' => 'Payouts', 'refunds' => 'Refunds'] as $type => $label)
            <a href="{{ route('admin.finance.reports.export', ['type' => $type, 'from' => $report['from'], 'to' => $report['to']]) }}"
               class="btn btn-secondary btn-sm">
                <span class="cc-icon !text-[15px]">download</span>
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
@endsection
