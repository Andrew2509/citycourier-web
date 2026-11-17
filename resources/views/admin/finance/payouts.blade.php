@extends('layouts.admin')

@section('title', 'Monitor Courier Payouts')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Payout Otomatis Kurir</h2>
            <p class="text-xs text-slate-500">
                Status Scheduler: <strong>{{ $autoEnabled ? 'AKTIF' : 'NONAKTIF' }}</strong>
                &bull; Minimum Payout: <strong>Rp {{ number_format($minPayout, 0, ',', '.') }}</strong>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <option value="all">Semua Status</option>
                    @foreach (['pending', 'processing', 'completed', 'failed', 'reversed'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">Filter</button>
            </form>
            <form method="POST" action="{{ route('admin.finance.payouts.run-auto') }}">
                @csrf
                <button type="submit" class="rounded-lg bg-orange-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-orange-700">
                    Jalankan Auto-Payout Sekarang
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
                    <th class="py-2 pr-3">Tujuan</th>
                    <th class="py-2 pr-3">Provider / Mode</th>
                    <th class="py-2 pr-3 text-right">Net Payout</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payouts as $p)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $p->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $p->courier?->user?->name ?? $p->user?->name ?? '#'.$p->user_id }}</td>
                        <td class="py-2.5 pr-3">{{ $p->channel_code }} &bull; {{ $p->account_number_masked }}</td>
                        <td class="py-2.5 pr-3 uppercase">{{ $p->provider }} ({{ $p->mode }})</td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($p->net_amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3">
                            <span class="uppercase font-semibold {{ $p->status === 'completed' ? 'text-emerald-700' : ($p->status === 'failed' ? 'text-red-600' : 'text-amber-700') }}">
                                {{ $p->status }}
                            </span>
                            @if ($p->failure_reason)
                                <div class="text-[10px] text-red-500">{{ $p->failure_reason }}</div>
                            @endif
                        </td>
                        <td class="py-2.5">
                            @if (in_array($p->status, ['pending', 'processing'], true))
                                <form method="POST" action="{{ route('admin.finance.payouts.verify', $p) }}">
                                    @csrf
                                    <button type="submit" class="rounded bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white">
                                        Verify Status
                                    </button>
                                </form>
                            @else
                                <span class="text-slate-400">{{ $p->completed_at?->format('d/m H:i') ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada transaksi payout kurir.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payouts->links() }}</div>
</div>
@endsection
