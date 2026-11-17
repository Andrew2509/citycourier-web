@extends('layouts.admin')

@section('title', 'Manajemen Wallet')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="wallet_type" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="all">Semua Tipe Wallet</option>
                <option value="customer" @selected($type === 'customer')>Customer</option>
                <option value="courier" @selected($type === 'courier')>Courier</option>
            </select>
            <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                <option value="all">Semua Status</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="frozen" @selected($status === 'frozen')>Frozen</option>
                <option value="suspended" @selected($status === 'suspended')>Suspended</option>
            </select>
            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">Filter</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-200 text-slate-500">
                <tr>
                    <th class="py-2 pr-3">ID</th>
                    <th class="py-2 pr-3">Tipe</th>
                    <th class="py-2 pr-3">Pemilik</th>
                    <th class="py-2 pr-3 text-right">Available</th>
                    <th class="py-2 pr-3 text-right">Pending</th>
                    <th class="py-2 pr-3 text-right">Reserved</th>
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2">Aksi Kontrol</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($wallets as $wallet)
                    @php
                        $ownerName = $wallet->user?->name ?? $wallet->courier?->user?->name ?? ('Courier #'.$wallet->courier_id);
                        $bal = $wallet->balanceRecord;
                        $avail = (int) ($bal?->available_balance ?? $wallet->available_balance);
                        $pend = (int) ($bal?->pending_balance ?? $wallet->pending_balance);
                        $resv = (int) ($bal?->reserved_balance ?? ($wallet->reserved_balance ?? 0));
                    @endphp
                    <tr>
                        <td class="py-2.5 pr-3 font-mono">#{{ $wallet->id }}</td>
                        <td class="py-2.5 pr-3 uppercase font-semibold">{{ $wallet->wallet_type ?? 'courier' }}</td>
                        <td class="py-2.5 pr-3">
                            <div class="font-medium text-slate-900">{{ $ownerName }}</div>
                            <div class="text-[11px] text-slate-500">{{ $wallet->user?->email ?? $wallet->courier?->phone }}</div>
                        </td>
                        <td class="py-2.5 pr-3 text-right font-semibold">Rp {{ number_format($avail, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 text-right text-amber-700">Rp {{ number_format($pend, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3 text-right text-slate-600">Rp {{ number_format($resv, 0, ',', '.') }}</td>
                        <td class="py-2.5 pr-3">
                            @if ($wallet->isFrozen())
                                <span class="rounded bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">FROZEN</span>
                                <div class="text-[10px] text-red-500 mt-0.5">{{ $wallet->freeze_reason }}</div>
                            @else
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ strtoupper($wallet->status) }}</span>
                            @endif
                        </td>
                        <td class="py-2.5">
                            @if ($wallet->isFrozen())
                                <form method="POST" action="{{ route('admin.finance.wallets.unfreeze', $wallet) }}">
                                    @csrf
                                    <button type="submit" class="rounded bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">
                                        Aktifkan
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.finance.wallets.freeze', $wallet) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="text" name="freeze_reason" required placeholder="Alasan freeze..."
                                           class="w-36 rounded border border-slate-300 px-2 py-1 text-[11px]">
                                    <button type="submit" class="rounded bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-red-700">
                                        Freeze
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-400">Belum ada data wallet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $wallets->links() }}</div>
</div>
@endsection
