@extends('layouts.admin')

@section('title', 'Keuangan & Setoran')
@section('page-title', 'Keuangan & Setoran')
@section('page-subtitle', 'Ringkasan penghasilan kurir dan manajemen penarikan dana')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-xl">payments</span>
                </div>
                Keuangan & Setoran
            </h1>
            <p class="text-sm text-slate-500 mt-1">Ringkasan penghasilan kurir dan penarikan dana</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">account_balance_wallet</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Total Omzet</p>
                    <p class="text-xl font-bold text-slate-800">Rp {{ number_format($earnings->sum('total_earnings') ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-success/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-success text-2xl">savings</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Pendapatan Bersih</p>
                    <p class="text-xl font-bold text-success">Rp {{ number_format(($earnings->sum('total_earnings') ?? 0) * 0.9, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-warning text-2xl">pending_actions</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Menunggu Penarikan</p>
                    <p class="text-xl font-bold text-warning">{{ $withdrawals->where('status', 'pending')->count() }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-2xl">check_circle</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Selesai</p>
                    <p class="text-xl font-bold text-info">{{ $withdrawals->where('status', 'completed')->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Earnings Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">wallet</span>
                Data Keuangan Kurir
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Kurir</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Tugas Selesai</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Total Omzet</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Potongan (10%)</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Pendapatan Bersih</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($earnings as $earning)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-warning to-amber-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ strtoupper(substr($earning->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-800">{{ $earning->user->name ?? '-' }}</p>
                                        <p class="text-xs text-slate-400">{{ $earning->phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10 text-primary font-semibold text-sm">
                                    {{ $earning->completed_orders }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-800">
                                Rp {{ number_format($earning->total_earnings ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-right text-danger font-medium">
                                -Rp {{ number_format(($earning->total_earnings ?? 0) * 0.1, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-success">
                                Rp {{ number_format(($earning->total_earnings ?? 0) * 0.9, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors text-xs font-medium">
                                    <span class="material-symbols-outlined text-sm">history</span>
                                    Riwayat
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-slate-400 text-3xl">account_balance_wallet</span>
                                    </div>
                                    <p class="text-sm text-slate-500">Belum ada data keuangan</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($earnings->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $earnings->links() }}
            </div>
        @endif
    </div>

    {{-- Withdrawal Requests Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-warning">request_quote</span>
                Permintaan Penarikan Dana
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Waktu</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Kurir</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Jumlah</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Rekening</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($withdrawals as $withdrawal)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $withdrawal->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-semibold text-xs shadow-sm">
                                        {{ strtoupper(substr($withdrawal->courier->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <span class="text-sm font-medium text-slate-800">{{ $withdrawal->courier->user->name ?? '-' }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right font-bold text-slate-800">
                                Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs">
                                    <p class="font-semibold text-slate-700">{{ $withdrawal->bank_name }}</p>
                                    <p class="text-slate-500">{{ $withdrawal->account_number }}</p>
                                    <p class="text-slate-400">a.n {{ $withdrawal->account_name }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        'approved' => 'bg-blue-100 text-blue-700',
                                        'completed' => 'bg-green-100 text-green-700',
                                        'rejected' => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColors[$withdrawal->status] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ ucfirst($withdrawal->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if($withdrawal->status === 'pending')
                                    <div class="flex items-center justify-center gap-2">
                                        <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-success text-white hover:bg-success/90 transition-colors text-xs font-medium shadow-sm" title="Setujui">
                                                <span class="material-symbols-outlined text-sm">check</span>
                                                Setujui
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-100 text-red-600 hover:bg-red-200 transition-colors text-xs font-medium" title="Tolak">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        </form>
                                    </div>
                                @elseif($withdrawal->status === 'approved')
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-white hover:bg-primary/90 transition-colors text-xs font-medium shadow-sm">
                                            <span class="material-symbols-outlined text-sm">paid</span>
                                            Selesaikan
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">
                                        {{ $withdrawal->processed_at ? $withdrawal->processed_at->format('d/m/Y') : '-' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-slate-400 text-3xl">request_quote</span>
                                    </div>
                                    <p class="text-sm text-slate-500">Belum ada permintaan penarikan</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($withdrawals->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $withdrawals->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
