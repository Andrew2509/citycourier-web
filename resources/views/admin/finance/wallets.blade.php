@extends('layouts.admin')

@section('title', 'Manajemen Wallet')

@section('content')
@include('admin.finance._nav', ['mode' => $mode])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="wallet_type" class="form-select w-auto">
                <option value="all">Semua Tipe Wallet</option>
                <option value="customer" @selected($type === 'customer')>Customer</option>
                <option value="courier" @selected($type === 'courier')>Courier</option>
            </select>
            <select name="status" class="form-select w-auto">
                <option value="all">Semua Status</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="frozen" @selected($status === 'frozen')>Frozen</option>
                <option value="suspended" @selected($status === 'suspended')>Suspended</option>
            </select>
            <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700">Filter</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipe</th>
                    <th>Pemilik</th>
                    <th class="text-right">Available</th>
                    <th class="text-right">Pending</th>
                    <th class="text-right">Reserved</th>
                    <th>Status</th>
                    <th>Aksi Kontrol</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($wallets as $wallet)
                    @php
                        $ownerName = $wallet->user?->name ?? $wallet->courier?->user?->name ?? ('Courier #'.$wallet->courier_id);
                        $bal = $wallet->balanceRecord;
                        $avail = (int) ($bal?->available_balance ?? $wallet->available_balance);
                        $pend = (int) ($bal?->pending_balance ?? $wallet->pending_balance);
                        $resv = (int) ($bal?->reserved_balance ?? ($wallet->reserved_balance ?? 0));
                    @endphp
                    <tr>
                        <td class="cell-mono">#{{ $wallet->id }}</td>
                        <td class="cell-primary uppercase">{{ $wallet->wallet_type ?? 'courier' }}</td>
                        <td>
                            <div class="font-medium text-ink-900">{{ $ownerName }}</div>
                            <div class="text-[11px] text-slate-500">{{ $wallet->user?->email ?? $wallet->courier?->phone }}</div>
                        </td>
                        <td class="text-right font-semibold tabular-nums">Rp {{ number_format($avail, 0, ',', '.') }}</td>
                        <td class="text-right text-amber-700 tabular-nums">Rp {{ number_format($pend, 0, ',', '.') }}</td>
                        <td class="text-right text-slate-600 tabular-nums">Rp {{ number_format($resv, 0, ',', '.') }}</td>
                        <td>
                            @if ($wallet->isFrozen())
                                <span class="badge badge-red">FROZEN</span>
                                <div class="text-[10px] text-red-500 mt-0.5">{{ $wallet->freeze_reason }}</div>
                            @else
                                <span class="badge badge-green">{{ strtoupper($wallet->status) }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($wallet->isFrozen())
                                <form method="POST" action="{{ route('admin.finance.wallets.unfreeze', $wallet) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        Aktifkan
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.finance.wallets.freeze', $wallet) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="text" name="freeze_reason" required placeholder="Alasan freeze..."
                                           class="form-input !h-8 w-36 !text-[11px]">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        Freeze
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
        </table>
        @if ($wallets->isEmpty())
            <div class="empty-state">
                <span class="cc-icon">account_balance_wallet</span>
                <div class="empty-title">Belum ada data wallet</div>
                <div class="empty-text">Wallet pelanggan &amp; kurir akan muncul di sini.</div>
            </div>
        @endif
    </div>
    <div class="mt-4">{{ $wallets->links() }}</div>
</div>
@endsection
