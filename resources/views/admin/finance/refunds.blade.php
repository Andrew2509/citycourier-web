@extends('layouts.admin')

@section('title', 'Manajemen Refund Pelanggan')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Pengajuan Refund Pelanggan</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="form-select w-auto">
                <option value="all">Semua Status</option>
                @foreach (['requested', 'approved', 'completed', 'rejected'] as $st)
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
                    <th class="text-right">Nominal</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Aksi Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($refunds as $r)
                    <tr>
                        <td class="cell-mono">{{ $r->reference }}</td>
                        <td class="cell-primary">{{ $r->user?->name ?? '#'.$r->user_id }}</td>
                        <td>{{ $r->shipment?->shipment_number ?? ($r->shipment_id ? '#'.$r->shipment_id : '-') }}</td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                        <td class="max-w-xs">{{ $r->reason }}</td>
                        <td>
                            @php
                                $stBadge = match ($r->status) {
                                    'completed' => 'badge-green',
                                    'approved' => 'badge-info',
                                    'rejected' => 'badge-red',
                                    default => 'badge-amber',
                                };
                            @endphp
                            <span class="badge {{ $stBadge }}"><span class="badge-dot"></span>{{ strtoupper($r->status) }}</span>
                        </td>
                        <td>
                            @if (in_array($r->status, ['requested', 'approved'], true))
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <form method="POST" action="{{ route('admin.finance.refunds.approve', $r) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">
                                            Approve &amp; Credit Wallet
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.finance.refunds.reject', $r) }}" class="flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="review_notes" required placeholder="Alasan tolak..."
                                               class="form-input !h-8 w-28 !text-[11px]">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="cell-muted">{{ $r->review_notes ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($refunds->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">assignment_return</span>
                <div class="empty-title">Belum ada pengajuan refund</div>
                <div class="empty-text">Pengajuan refund pelanggan akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $refunds->links() }}</div>
</div>
@endsection
