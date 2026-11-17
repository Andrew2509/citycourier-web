@extends('layouts.admin')

@section('title', 'Monitor Top-Up Customer')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Transaksi Top-Up Pelanggan</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="all">Semua Status</option>
                @foreach (['pending', 'settled', 'paid', 'failed', 'expired'] as $st)
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
                    <th class="py-2 pr-3">Channel</th>
                    <th class="py-2 pr-3">Provider / Mode</th>
                    <th class="py-2 pr-3 text-right">Nominal</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Aksi Sandbox</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($topups as $topup)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $topup->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $topup->user?->name ?? '#'.$topup->user_id }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $topup->payment_type }} &bull; {{ $topup->channel_code }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $topup->provider }} ({{ $topup->mode }}{{ $topup->is_simulated ? ' SIM' : '' }})</td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($topup->amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 uppercase font-semibold">{{ $topup->status }}</td>
                        <td class="py-2.5">
                            @if ($topup->status === 'pending' && $mode !== 'production')
                                <form method="POST" action="{{ route('admin.finance.topups.simulate', $topup) }}">
                                    @csrf
                                    <button type="submit" class="rounded bg-amber-600 px-2.5 py-1 text-[11px] font-semibold text-white">
                                        Simulate Paid
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada transaksi top-up.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $topups->links() }}</div>
</div>
@endsection
