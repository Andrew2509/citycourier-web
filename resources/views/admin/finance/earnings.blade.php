@extends('layouts.admin')

@section('title', 'Monitor Courier Earnings')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Pendapatan Kurir Otomatis</h2>
            <p class="text-xs text-slate-500">Komisi aktif: {{ $commissionRate }}% &bull; Masa tunggu settlement: {{ $waitingMinutes }} menit</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="all">Semua Status</option>
                    @foreach (['pending', 'available', 'reserved', 'paid'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">Filter</button>
            </form>
            <form method="POST" action="{{ route('admin.finance.earnings.release') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-orange-700">
                    Release Mature Pending Earnings
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">Reference</th>
                    <th class="py-2 pr-3">Kurir</th>
                    <th class="py-2 pr-3">Order</th>
                    <th class="py-2 pr-3 text-right">Gross</th>
                    <th class="py-2 pr-3 text-right">Komisi</th>
                    <th class="py-2 pr-3 text-right">Net Kurir</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Eligible At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($earnings as $e)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $e->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $e->courier?->user?->name ?? 'Courier #'.$e->courier_id }}</td>
                        <td class="py-2.5 pr-3">#{{ $e->order?->order_number ?? $e->order_id }}</td>
                        <td class="py-2.5 pr-3 text-right">Rp {{ number_format($e->gross_amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 text-right text-slate-500">Rp {{ number_format($e->commission_amount, 0, ',', '.') }} ({{ $e->commission_rate }}%)</td>
                        <td class="py-2.5 pr-3 text-right font-semibold text-emerald-700">Rp {{ number_format($e->net_amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 uppercase font-semibold">{{ $e->status }}</td>
                        <td class="py-2.5 text-slate-500">{{ $e->available_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-400">Belum ada data earning kurir.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $earnings->links() }}</div>
</div>
@endsection
