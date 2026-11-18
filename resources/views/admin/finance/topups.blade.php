@extends('layouts.admin')

@section('title', 'Monitor Top-Up Customer')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Transaksi Top-Up Pelanggan</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="form-select w-auto">
                <option value="all">Semua Status</option>
                @foreach (['pending', 'settled', 'paid', 'failed', 'expired'] as $st)
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
                    <th>Channel</th>
                    <th>Provider / Mode</th>
                    <th class="text-right">Nominal</th>
                    <th>Status</th>
                    <th>Aksi Sandbox</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topups as $topup)
                    <tr>
                        <td class="cell-mono">{{ $topup->reference }}</td>
                        <td class="cell-primary">{{ $topup->user?->name ?? '#'.$topup->user_id }}</td>
                        <td class="uppercase">{{ $topup->payment_type }} &bull; {{ $topup->channel_code }}</td>
                        <td class="uppercase">{{ $topup->provider }} ({{ $topup->mode }}{{ $topup->is_simulated ? ' SIM' : '' }})</td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($topup->amount, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $stBadge = match ($topup->status) {
                                    'settled', 'paid' => 'badge-green',
                                    'failed', 'expired' => 'badge-red',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($topup->status) }}</span>
                        </td>
                        <td>
                            @if ($topup->status === 'pending' && $mode !== 'production')
                                <form method="POST" action="{{ route('admin.finance.topups.simulate', $topup) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-secondary !text-amber-700">
                                        Simulate Paid
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($topups->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">receipt_long</span>
                <div class="empty-title">Belum ada transaksi top-up</div>
                <div class="empty-text">Transaksi top-up pelanggan akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $topups->links() }}</div>
</div>
@endsection
