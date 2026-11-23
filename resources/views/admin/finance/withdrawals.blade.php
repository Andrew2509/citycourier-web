@extends('layouts.admin')

@section('title', 'Monitor Customer Withdrawals')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

@if (! $withdrawalEnabled)
    <div class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Kebijakan Regulasi Aktif:</strong> Penarikan dana wallet pelanggan dinonaktifkan di mode production hingga struktur entitas hukum dan persetujuan penyedia pembayaran terpenuhi.
    </div>
@endif

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <h2 class="mb-4 text-sm font-semibold text-slate-900">Riwayat Penarikan Dana Pelanggan</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Pelanggan</th>
                    <th>Tujuan</th>
                    <th>Mode</th>
                    <th class="text-right">Nominal (IDR)</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($withdrawals as $w)
                    <tr>
                        <td class="cell-mono">{{ $w->reference }}</td>
                        <td class="cell-primary">{{ $w->user?->name ?? '#'.$w->user_id }}</td>
                        <td>{{ $w->channel_code }} &bull; {{ $w->account_number_masked }}</td>
                        <td class="uppercase">{{ $w->mode }}</td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($w->amount, 0, ',', '.') }}</td>
                        <td>
                            @php
                                $stBadge = match ($w->status) {
                                    'completed', 'success', 'paid', 'settled' => 'badge-green',
                                    'failed', 'rejected', 'reversed' => 'badge-red',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($w->status) }}</span>
                            @if ($w->failure_reason)
                                <div class="text-[10px] text-red-500 mt-0.5">{{ $w->failure_reason }}</div>
                            @endif
                        </td>
                        <td>
                            @if (in_array($w->status, ['pending', 'processing'], true))
                                <form method="POST" action="{{ route('admin.finance.payouts.verify', $w) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-secondary">
                                        Verify Status
                                    </button>
                                </form>
                            @else
                                <span class="cell-muted">{{ $w->completed_at?->format('d/m H:i') ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($withdrawals->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">payments</span>
                <div class="empty-title">Belum ada permintaan withdrawal</div>
                <div class="empty-text">Penarikan dana pelanggan akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $withdrawals->links() }}</div>
</div>
@endsection
