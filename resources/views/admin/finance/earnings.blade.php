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
                <select name="status" class="form-select w-auto">
                    <option value="all">Semua Status</option>
                    @foreach (['pending', 'available', 'reserved', 'paid'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
            </form>
            <form method="POST" action="{{ route('admin.finance.earnings.release') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">
                    Release Mature Pending Earnings
                </button>
            </form>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Kurir</th>
                    <th>Order</th>
                    <th class="text-right">Gross</th>
                    <th class="text-right">Komisi</th>
                    <th class="text-right">Net Kurir</th>
                    <th>Status</th>
                    <th>Eligible At</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($earnings as $e)
                    <tr>
                        <td class="cell-mono">{{ $e->reference }}</td>
                        <td class="cell-primary">{{ $e->courier?->user?->name ?? 'Courier #'.$e->courier_id }}</td>
                        <td>#{{ $e->order?->order_number ?? $e->order_id }}</td>
                        <td class="text-right tabular-nums">Rp {{ number_format($e->gross_amount, 0, ',', '.') }}</td>
                        <td class="text-right text-slate-500 tabular-nums">Rp {{ number_format($e->commission_amount, 0, ',', '.') }} ({{ $e->commission_rate }}%)</td>
                        <td class="text-right font-semibold text-emerald-700 tabular-nums">Rp {{ number_format($e->net_amount, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $stBadge = match ($e->status) {
                                    'paid' => 'badge-green',
                                    'available' => 'badge-info',
                                    'reserved' => 'badge-indigo',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($e->status) }}</span>
                        </td>
                        <td class="cell-muted">{{ $e->available_at?->format('d/m H:i') }}</td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($earnings->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">savings</span>
                <div class="empty-title">Belum ada data earning kurir</div>
                <div class="empty-text">Komisi kurir akan muncul otomatis dari pesanan selesai.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $earnings->links() }}</div>
</div>
@endsection
