@extends('layouts.admin')

@section('title', 'Monitor Order Payments')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Transaksi Pembayaran Order &amp; Wallet</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="all">Semua Status</option>
                @foreach (['paid', 'pending', 'failed', 'refunded'] as $st)
                    <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">Filter</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Reference</th>
                    <th class="py-2 pr-3">Pelanggan</th>
                    <th class="py-2 pr-3">Shipment</th>
                    <th class="py-2 pr-3">Metode</th>
                    <th class="py-2 pr-3">Mode</th>
                    <th class="py-2 pr-3 text-right">Total (IDR)</th>
                    <th class="py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $p)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $p->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $p->user?->name ?? '#'.$p->user_id }}</td>
                        <td class="py-2.5 pr-3">{{ $p->shipment?->shipment_number ?? ($p->shipment_id ? '#'.$p->shipment_id : '-') }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $p->payment_method }} ({{ $p->channel_code }})</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $p->mode }}</td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($p->total_amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 uppercase font-semibold">{{ $p->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada pembayaran v1 tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</div>
@endsection
