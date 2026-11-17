@extends('layouts.admin')

@section('title', 'Manajemen Refund Pelanggan')

@section('content')
@include('admin.finance._nav')

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-900">Pengajuan Refund Pelanggan</h2>
        <form method="GET" class="flex items-center gap-2">
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="all">Semua Status</option>
                @foreach (['requested', 'approved', 'completed', 'rejected'] as $st)
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
                    <th class="py-2 pr-3 text-right">Nominal</th>
                    <th class="py-2 pr-3">Alasan</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Aksi Admin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($refunds as $r)
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">{{ $r->reference }}</td>
                        <td class="py-2.5 pr-3">{{ $r->user?->name ?? '#'.$r->user_id }}</td>
                        <td class="py-2.5 pr-3">{{ $r->shipment?->shipment_number ?? ($r->shipment_id ? '#'.$r->shipment_id : '-') }}</td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($r->amount, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 max-w-xs">{{ $r->reason }}</td>
                        <td class="py-2.5 pr-3 uppercase font-semibold">{{ $r->status }}</td>
                        <td class="py-2.5">
                            @if (in_array($r->status, ['requested', 'approved'], true))
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <form method="POST" action="{{ route('admin.finance.refunds.approve', $r) }}">
                                        @csrf
                                        <button type="submit" class="rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white">
                                            Approve &amp; Credit Wallet
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.finance.refunds.reject', $r) }}" class="flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="review_notes" required placeholder="Alasan tolak..."
                                               class="w-28 rounded border border-slate-300 px-1.5 py-1 text-[11px]">
                                        <button type="submit" class="rounded bg-red-600 px-2 py-1 text-[11px] font-semibold text-white">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-slate-400">{{ $r->review_notes ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">Belum ada pengajuan refund.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $refunds->links() }}</div>
</div>
@endsection
