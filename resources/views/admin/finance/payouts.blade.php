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
                <select name="status" class="form-select w-auto">
                    <option value="all">Semua Status</option>
                    @foreach (['pending', 'processing', 'completed', 'failed', 'reversed'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ strtoupper($st) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
            </form>
            <form method="POST" action="{{ route('admin.finance.payouts.run-auto') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">
                    Jalankan Auto-Payout Sekarang
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
                    <th>Tujuan</th>
                    <th>Provider / Mode</th>
                    <th class="text-right">Net Payout</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payouts as $p)
                    <tr>
                        <td class="cell-mono">{{ $p->reference }}</td>
                        <td class="cell-primary">{{ $p->courier?->user?->name ?? $p->user?->name ?? '#'.$p->user_id }}</td>
                        <td>{{ $p->channel_code }} &bull; {{ $p->account_number_masked }}</td>
                        <td class="uppercase">{{ $p->provider }} ({{ $p->mode }})</td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($p->net_amount, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $stBadge = match ($p->status) {
                                    'completed' => 'badge-green',
                                    'failed' => 'badge-red',
                                    'reversed' => 'badge-indigo',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($p->status) }}</span>
                            @if ($p->failure_reason)
                                <div class="text-[10px] text-red-500 mt-0.5">{{ $p->failure_reason }}</div>
                            @endif
                        </td>
                        <td>
                            @if (in_array($p->status, ['pending', 'processing'], true))
                                <form method="POST" action="{{ route('admin.finance.payouts.verify', $p) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-secondary">
                                        Verify Status
                                    </button>
                                </form>
                            @else
                                <span class="cell-muted">{{ $p->completed_at?->format('d/m H:i') ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($payouts->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">send_money</span>
                <div class="empty-title">Belum ada transaksi payout kurir</div>
                <div class="empty-text">Payout otomatis kurir akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $payouts->links() }}</div>
</div>
@endsection
