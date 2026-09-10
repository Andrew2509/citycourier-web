@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <span>City-Work Operasional</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface font-semibold">Keuangan & Setoran Kurir</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Keuangan & Setoran Kurir</h1>
            <p class="font-body-md text-body-md text-secondary">Rekapitulasi penghasilan dan penarikan dana kurir</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex border-b border-surface-container-high">
            <button onclick="switchTab('earnings')" id="tab-earnings" class="flex items-center gap-space-xs px-space-lg py-3.5 text-sm font-semibold border-b-2 border-primary text-primary transition-colors">
                <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                Ringkasan Penghasilan
            </button>
            <button onclick="switchTab('withdrawals')" id="tab-withdrawals" class="flex items-center gap-space-xs px-space-lg py-3.5 text-sm font-semibold border-b-2 border-transparent text-secondary hover:text-on-surface transition-colors">
                <span class="material-symbols-outlined text-[18px]">savings</span>
                Penarikan Dana
            </button>
        </div>
    </div>

    <!-- Earnings Panel -->
    <div id="panel-earnings" class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Penghasilan Kurir</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Total penghasilan dari pesanan yang telah selesai</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Total Pesanan Selesai</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Total Penghasilan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($earnings as $earning)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($earning->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-on-surface">{{ $earning->user->name ?? '-' }}</p>
                                    <p class="text-xs text-secondary">{{ $earning->user->email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 font-medium text-on-surface">{{ $earning->completed_orders }}</td>
                        <td class="py-4 px-4 font-bold text-primary">Rp {{ number_format($earning->total_earnings, 0, ',', '.') }}</td>
                        <td class="py-4 px-4">
                            @if($earning->total_earnings > 0)
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-surface-container text-secondary font-label-sm text-xs font-bold">
                                    Belum Ada
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">paid</span>
                                <span class="font-body-sm text-body-sm">Belum ada data penghasilan</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $earnings->links() }}
        </div>
    </div>

    <!-- Withdrawals Panel -->
    <div id="panel-withdrawals" class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">savings</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Penarikan Dana Kurir</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Permohonan penarikan dana yang perlu diproses</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Jumlah</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Rekening</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($withdrawals as $withdrawal)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($withdrawal->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-on-surface">{{ $withdrawal->courier->user->name ?? '-' }}</p>
                                    <p class="text-xs text-secondary">{{ \Carbon\Carbon::parse($withdrawal->created_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 font-bold text-on-surface">Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}</td>
                        <td class="py-4 px-4">
                            <div>
                                <p class="font-medium text-on-surface">{{ $withdrawal->bank_name }}</p>
                                <p class="text-xs text-secondary">{{ $withdrawal->account_number }} - {{ $withdrawal->account_name }}</p>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            @if($withdrawal->status === 'pending')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-amber-50 text-amber-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Menunggu
                                </span>
                            @elseif($withdrawal->status === 'approved')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                    Disetujui
                                </span>
                            @elseif($withdrawal->status === 'rejected')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-red-50 text-red-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Ditolak
                                </span>
                            @elseif($withdrawal->status === 'completed')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-blue-50 text-blue-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    Selesai
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            @if($withdrawal->status === 'pending')
                                <div class="inline-flex items-center justify-end gap-space-xs">
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Setujui">
                                            <span class="material-symbols-outlined text-[18px]">check</span>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors" title="Tolak">
                                            <span class="material-symbols-outlined text-[18px]">close</span>
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-xs text-secondary">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">savings</span>
                                <span class="font-body-sm text-body-sm">Belum ada permohonan penarikan dana</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $withdrawals->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    function switchTab(tab) {
        const earningsTab = document.getElementById('tab-earnings');
        const withdrawalsTab = document.getElementById('tab-withdrawals');
        const earningsPanel = document.getElementById('panel-earnings');
        const withdrawalsPanel = document.getElementById('panel-withdrawals');

        if (tab === 'earnings') {
            earningsTab.classList.add('border-primary', 'text-primary');
            earningsTab.classList.remove('border-transparent', 'text-secondary');
            withdrawalsTab.classList.remove('border-primary', 'text-primary');
            withdrawalsTab.classList.add('border-transparent', 'text-secondary');
            earningsPanel.classList.remove('hidden');
            withdrawalsPanel.classList.add('hidden');
        } else {
            withdrawalsTab.classList.add('border-primary', 'text-primary');
            withdrawalsTab.classList.remove('border-transparent', 'text-secondary');
            earningsTab.classList.remove('border-primary', 'text-primary');
            earningsTab.classList.add('border-transparent', 'text-secondary');
            withdrawalsPanel.classList.remove('hidden');
            earningsPanel.classList.add('hidden');
        }
    }
</script>
@endpush
@endsection
