@extends('layouts.admin')

@section('title', 'Monitor Order Payments')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Transaksi Pembayaran Order &amp; Wallet</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="form-select w-auto">
                <option value="all">Semua Status</option>
                @foreach (['paid', 'pending', 'failed', 'refunded'] as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Pelanggan</th>
                    <th>Shipment</th>
                    <th>Metode</th>
                    <th>Mode</th>
                    <th class="text-right">Total (IDR)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="cell-mono">{{ $p->reference }}</td>
                        <td class="cell-primary">{{ $p->user?->name ?? '#'.$p->user_id }}</td>
                        <td>{{ $p->shipment?->shipment_number ?? ($p->shipment_id ? '#'.$p->shipment_id : '-') }}</td>
                        <td class="uppercase">{{ $p->payment_method }} ({{ $p->channel_code }})</td>
                        <td class="uppercase">{{ $p->mode }}</td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($p->total_amount, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $stBadge = match ($p->status) {
                                    'paid' => 'badge-green',
                                    'failed' => 'badge-red',
                                    'refunded' => 'badge-indigo',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($p->status) }}</span>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($payments->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">receipt_long</span>
                <div class="empty-title">Belum ada pembayaran tercatat</div>
                <div class="empty-text">Transaksi pembayaran order &amp; wallet akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</div>
@endsection
