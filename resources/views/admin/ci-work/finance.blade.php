@extends('layouts.admin')

@section('title', 'Keuangan & Setoran Kurir')

@php
function fin($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); }
$financeData = $recaps->map(function ($c) {
    return [
        'id'             => $c->id,
        'name'           => $c->user->name ?? 'Kurir',
        'phone'          => $c->user->phone ?? $c->phone ?? '-',
        'email'          => $c->user->email ?? '-',
        'active'         => (bool) $c->is_active,
        'todayCount'     => $c->today_count,
        'omzet'          => $c->today_omzet,
        'commission'     => $c->commission,
        'net'            => $c->net_earnings,
        'balance'        => $c->balance,
        'pendingBalance' => $c->pending_balance,
        'orders'         => $c->today_orders->values()->map(fn($o) => [
            'order_number' => $o->order_number,
            'tracking'     => $o->tracking_number,
            'amount'       => (float) $o->price,
            'route'        => ($o->pickup_address ?? '') . ' → ' . ($o->delivery_address ?? ''),
            'time'         => $o->delivered_at ? $o->delivered_at->format('d M Y, H:i') : '',
        ])->all(),
    ];
})->values()->keyBy('id');
@endphp

@section('content')
<div class="space-y-5">
    <!-- Flash -->
    @if(session('success'))
    <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-semibold flex items-center gap-2">
        <span class="material-symbols-outlined text-[17px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Settlement &amp; Dompet
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Bagi Hasil &amp; Kas COD</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Keuangan &amp; Setoran Kurir</h1>
            <p class="text-xs text-slate-500 mt-0.5">Rekapitulasi omzet kurir, potongan komisi platform, dan persetujuan penarikan dana</p>
        </div>

        <div class="flex items-center gap-2 self-start sm:self-auto">
            <button onclick="openCommissionModal()" type="button" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">tune</span>
                <span>Atur Bagi Hasil</span>
            </button>
            <a href="{{ route('admin.ci-work.finance.export') }}" class="btn btn-secondary text-xs h-9 px-3 gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-slate-500">download</span>
                <span>Unduh Laporan</span>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Omzet Hari Ini</span>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">{{ fin($stats['omzet']) }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>{{ $stats['delivered_today'] }} Order Selesai</span>
                <span class="badge badge-success text-[10px]">100% Berhasil</span>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Hak Bersih Mitra</span>
                    <div class="text-xl font-bold font-mono text-emerald-600 mt-1">{{ fin($stats['net']) }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Porsi Mitra</span>
                <span class="badge badge-success text-[10px] font-mono">{{ (int) (100 - $stats['commission_rate']) }}% Porsi</span>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Komisi Platform</span>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">{{ fin($stats['platform']) }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">percent</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Margin Operasional</span>
                <span class="badge badge-neutral text-[10px] font-mono">{{ (int) $stats['commission_rate'] }}% Margin</span>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Antrean Penarikan</span>
                    <div class="text-xl font-bold font-mono text-slate-900 mt-1">{{ fin($stats['pending_wd_amount']) }}</div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">savings</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Permintaan Baru</span>
                <span class="badge badge-primary text-[10px] font-mono">{{ $stats['pending_wd_count'] }} Antrean</span>
            </div>
        </div>
    </div>

    <!-- SOP Notice Strip -->
    <div class="card p-3.5 bg-amber-50/70 border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">verified</span>
            </div>
            <div class="space-y-0.5">
                <p class="font-bold text-amber-950">Kebijakan Settlement &amp; Setoran Tunai COD</p>
                <p class="text-amber-800 leading-normal">
                    Platform memotong komisi <strong class="font-bold text-amber-950">{{ (int) $stats['commission_rate'] }}%</strong> dari total ongkir. Setoran tunai COD wajib diserahkan ke Drop Point Bratang maksimal 1x24 jam setelah pengiriman.
                </p>
            </div>
        </div>
        <button onclick="openSopModal()" type="button" class="btn btn-secondary text-xs h-8 px-3 shrink-0 bg-white border-amber-200 text-amber-900 hover:bg-amber-100/50">
            Detail SOP
        </button>
    </div>

    <!-- Section 1: Rekapitulasi Keuangan Kurir -->
    <div class="card overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">groups</span>
                <div>
                    <h2 class="text-xs font-bold text-slate-900">Rekapitulasi Keuangan Per Kurir</h2>
                    <span class="text-[11px] text-slate-500">Ringkasan omzet, potongan komisi, dan saldo dompet mitra</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <span class="material-symbols-outlined text-[16px] text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2">search</span>
                    <input type="text" id="recapSearch" oninput="filterRecaps()" placeholder="Cari kurir..." class="form-input h-8 pl-8 text-xs w-48 sm:w-60">
                </div>
                <div class="relative">
                    <button onclick="toggleFilterMenu()" type="button" class="btn btn-secondary text-xs h-8 px-2.5 gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-500">tune</span>
                        <span>Filter</span>
                    </button>
                    <div id="filterMenu" class="absolute right-0 top-9 z-20 hidden bg-white border border-slate-200 rounded-lg shadow-xl p-2 min-w-44 text-xs space-y-1">
                        <span class="text-[10px] uppercase font-bold text-slate-400 px-2 block mb-1">Status Kurir</span>
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="semua" checked onchange="filterRecaps()" class="accent-primary-600">
                            <span>Semua Status</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="aktif" onchange="filterRecaps()" class="accent-primary-600">
                            <span>Kurir Aktif</span>
                        </label>
                        <label class="flex items-center gap-2 px-2 py-1 hover:bg-slate-50 rounded cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="nonaktif" onchange="filterRecaps()" class="accent-primary-600">
                            <span>Kurir Nonaktif</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kurir</th>
                        <th>Tugas Selesai</th>
                        <th>Total Omzet</th>
                        <th>Potongan ({{ (int) $stats['commission_rate'] }}%)</th>
                        <th>Pendapatan Bersih</th>
                        <th>Saldo Dompet</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recaps as $courier)
                    <tr class="rec-row"
                        data-active="{{ $courier->is_active ? '1' : '0' }}"
                        data-search="{{ strtolower(($courier->user->name ?? '') . ' ' . ($courier->user->email ?? '') . ' ' . ($courier->user->phone ?? '') . ' ' . ($courier->phone ?? '')) }}">
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="relative shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs">
                                        {{ strtoupper(substr($courier->user->name ?? 'K', 0, 1)) }}
                                    </div>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border border-white {{ $courier->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-900 truncate">{{ $courier->user->name ?? '-' }}</span>
                                        <span class="badge {{ $courier->is_active ? 'badge-success' : 'badge-neutral' }} text-[9px] px-1 py-0">
                                            {{ $courier->is_active ? 'AKTIF' : 'OFFLINE' }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 block font-mono truncate">{{ $courier->user->phone ?? $courier->phone }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="font-medium text-slate-800 text-xs">{{ $courier->today_count }} Paket</span>
                        </td>
                        <td class="font-mono font-bold text-slate-900 text-xs">
                            {{ fin($courier->today_omzet) }}
                        </td>
                        <td class="font-mono font-semibold text-rose-600 text-xs">
                            -{{ fin($courier->commission) }}
                        </td>
                        <td class="font-mono font-bold text-emerald-600 text-xs">
                            {{ fin($courier->net_earnings) }}
                        </td>
                        <td>
                            <div class="space-y-0.5">
                                <span class="font-mono font-bold text-slate-900 text-xs block">{{ fin($courier->balance) }}</span>
                                @if($courier->balance > 0)
                                    <span class="text-[10px] font-semibold text-emerald-600 flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">verified</span> Siap Ditarik
                                    </span>
                                @elseif($courier->pending_balance > 0)
                                    <span class="text-[10px] font-semibold text-amber-600 flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-[12px]">schedule</span> Pending
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-400">Kosong</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <button onclick="openReconcile({{ $courier->id }})" type="button" class="btn btn-secondary text-xs h-7 w-7 p-0" title="Rincian Transaksi">
                                    <span class="material-symbols-outlined text-[15px] text-slate-500">receipt_long</span>
                                </button>
                                <button onclick="openTripModal({{ $courier->id }})" type="button" class="btn btn-secondary text-xs h-7 w-7 p-0" title="Rincian Trip">
                                    <span class="material-symbols-outlined text-[15px] text-slate-500">map</span>
                                </button>
                                <button onclick="openAdjustModal(this)" type="button" data-name="{{ $courier->user->name ?? 'Kurir' }}" data-route="{{ route('admin.ci-work.finance.reconcile', $courier->id) }}"
                                    class="btn btn-secondary text-xs h-7 w-7 p-0" title="Penyesuaian Saldo">
                                    <span class="material-symbols-outlined text-[15px] text-slate-500">tune</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">groups</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada data keuangan kurir</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-500">
            <span>Menampilkan {{ $recaps->total() > 0 ? ($recaps->firstItem() === $recaps->lastItem() ? $recaps->firstItem() : $recaps->firstItem() . ' - ' . $recaps->lastItem()) : 0 }} dari {{ $recaps->total() }} mitra kurir aktif</span>
            <div>{{ $recaps->links() }}</div>
        </div>
    </div>

    <!-- Section 2: Penarikan Dana (Withdrawals) -->
    <div class="card overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">savings</span>
                <div>
                    <h2 class="text-xs font-bold text-slate-900">Permintaan Penarikan Saldo Dompet</h2>
                    <span class="text-[11px] text-slate-500">Kelola dan verifikasi transfer saldo kurir mitra</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ url()->current() }}" class="btn btn-secondary text-xs h-8 px-2.5 gap-1">
                    <span class="material-symbols-outlined text-[15px] text-slate-500">refresh</span>
                    <span>Segarkan</span>
                </a>
                <button onclick="openGuideModal()" type="button" class="btn btn-secondary text-xs h-8 px-2.5 gap-1">
                    <span class="material-symbols-outlined text-[15px] text-slate-500">help</span>
                    <span>Panduan</span>
                </button>
            </div>
        </div>

        <!-- Segmented Status Tabs -->
        <div class="flex items-center gap-1 px-4 border-b border-slate-200 bg-white overflow-x-auto text-xs py-2">
            <button onclick="setWdTab('pending', this)" data-wd-tab="pending" type="button"
                class="wd-tab px-3 py-1.5 rounded-lg font-semibold inline-flex items-center gap-1.5 border border-primary-600 bg-primary-50 text-primary-700">
                <span class="material-symbols-outlined text-[15px]">pending_actions</span>
                <span>Menunggu Persetujuan</span>
                <span class="px-1.5 rounded-full bg-primary-600 text-white text-[10px] font-mono" data-wd-count="pending">{{ $withdrawalCounts['pending'] }}</span>
            </button>
            <button onclick="setWdTab('completed', this)" data-wd-tab="completed" type="button"
                class="wd-tab px-3 py-1.5 rounded-lg font-semibold inline-flex items-center gap-1.5 border border-transparent text-slate-600 hover:bg-slate-50">
                <span class="material-symbols-outlined text-[15px]">check_circle</span>
                <span>Berhasil Ditransfer</span>
                <span class="px-1.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-mono" data-wd-count="completed">{{ $withdrawalCounts['completed'] }}</span>
            </button>
            <button onclick="setWdTab('rejected', this)" data-wd-tab="rejected" type="button"
                class="wd-tab px-3 py-1.5 rounded-lg font-semibold inline-flex items-center gap-1.5 border border-transparent text-slate-600 hover:bg-slate-50">
                <span class="material-symbols-outlined text-[15px]">cancel</span>
                <span>Ditolak</span>
                <span class="px-1.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-mono" data-wd-count="rejected">{{ $withdrawalCounts['rejected'] }}</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kurir</th>
                        <th>Jumlah Penarikan</th>
                        <th>Metode & Akun</th>
                        <th>No. Referensi</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $wd)
                    @php
                        $wdTabStatus = in_array($wd->status, ['approved', 'completed', 'success']) 
                            ? 'completed' 
                            : (in_array($wd->status, ['rejected', 'failed', 'cancelled']) ? 'rejected' : 'pending');
                        $providerName = strtoupper($wd->provider ?? $wd->bank_name ?? 'OVO');
                    @endphp
                    <tr class="wd-row" data-status="{{ $wdTabStatus }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ strtoupper(substr($wd->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900 text-xs">{{ $wd->courier->user->name ?? '-' }}</p>
                                    <p class="text-[11px] text-slate-400 font-mono">{{ \Carbon\Carbon::parse($wd->created_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono font-bold text-slate-900 text-xs whitespace-nowrap">
                            <div>{{ fin($wd->amount) }}</div>
                            @if($wd->fee > 0)
                                <div class="text-[10px] text-slate-400 font-normal">Bersih: {{ fin($wd->net_amount) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-800">
                                    @if($providerName === 'OVO')
                                        <span class="w-2 h-2 rounded-full bg-[#4c3494] inline-block"></span>
                                        <span>OVO</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-sky-500 inline-block"></span>
                                        <span>{{ $providerName }}</span>
                                    @endif
                                </span>
                                <p class="text-[11px] text-slate-600 font-mono">{{ $wd->account_number ?: ($wd->danaConnection?->masked_phone ?? '-') }}</p>
                                <p class="text-[10px] text-slate-400">a.n {{ $wd->account_name ?: ($wd->danaConnection?->account_name ?? 'Kurir') }}</p>
                            </div>
                        </td>
                        <td>
                            <span class="text-[11px] font-mono text-slate-600 block">{{ $wd->provider_reference ?: ($wd->idempotency_key ?: '-') }}</span>
                        </td>
                        <td>
                            @if(in_array($wd->status, ['pending', 'reserved']))
                                <span class="badge badge-warning text-[11px]">Menunggu</span>
                            @elseif($wd->status === 'processing')
                                <span class="badge badge-info text-[11px]">Diproses</span>
                            @elseif($wd->status === 'approved')
                                <span class="badge badge-success text-[11px]">Disetujui</span>
                            @elseif(in_array($wd->status, ['completed', 'success']))
                                <span class="badge badge-primary text-[11px]">Berhasil</span>
                            @elseif(in_array($wd->status, ['rejected', 'failed', 'cancelled']))
                                <span class="badge badge-danger text-[11px]">Gagal</span>
                                @if($wd->failure_reason)
                                    <p class="text-[10px] text-rose-500 mt-0.5 max-w-[180px] truncate" title="{{ $wd->failure_reason }}">{{ $wd->failure_reason }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="text-right">
                            @if(in_array($wd->status, ['pending', 'reserved']))
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Setujui penarikan {{ fin($wd->amount) }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="btn btn-primary text-xs h-7 px-2.5 gap-1">
                                            <span class="material-symbols-outlined text-[14px]">check</span>
                                            <span>Setujui</span>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Tolak penarikan {{ fin($wd->amount) }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1 text-rose-600 hover:bg-rose-50">
                                            <span class="material-symbols-outlined text-[14px]">close</span>
                                            <span>Tolak</span>
                                        </button>
                                    </form>
                                </div>
                            @elseif($wd->status === 'approved' && $providerName !== 'OVO')
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                    onsubmit="return confirm('Tandai penarikan {{ fin($wd->amount) }} telah selesai ditransfer?')">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1" title="Tandai Selesai">
                                        <span class="material-symbols-outlined text-[14px] text-emerald-600">task_alt</span>
                                        <span>Tandai Selesai</span>
                                    </button>
                                </form>
                            @elseif($providerName === 'OVO' && !$wd->isTerminal())
                                <form action="{{ route('admin.ci-work.finance.withdrawal.check', $wd->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary text-xs h-7 px-2.5 gap-1" title="Verifikasi status resmi transaksi via API OVO (topup-status). Hasil OVO-lah yang memfinalkan status.">
                                        <span class="material-symbols-outlined text-[14px] text-[#4c3494]">sync</span>
                                        <span>Cek Status OVO</span>
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    @if($withdrawalCounts['pending'] === 0)
                    <tr class="wd-row" data-status="pending" data-empty="1">
                        <td colspan="5" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">pending_actions</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada permintaan penarikan menunggu persetujuan</p>
                        </td>
                    </tr>
                    @endif

                    @if($withdrawalCounts['completed'] === 0)
                    <tr class="wd-row" data-status="completed" data-empty="1">
                        <td colspan="5" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">savings</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada penarikan yang berhasil ditransfer</p>
                        </td>
                    </tr>
                    @endif

                    @if($withdrawalCounts['rejected'] === 0)
                    <tr class="wd-row" data-status="rejected" data-empty="1">
                        <td colspan="5" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">cancel</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada penarikan yang ditolak</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-100">
            {{ $withdrawals->withQueryString()->links() }}
        </div>

        <!-- Telemetry Footer -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 border-t border-slate-100 bg-slate-50/50 text-xs">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[16px]">online_prediction</span>
                </div>
                <div>
                    <span class="font-semibold text-slate-800 block">Gateway Pencairan</span>
                    <span class="text-[11px] text-slate-500">Transfer bank terintegrasi</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[16px]">currency_exchange</span>
                </div>
                <div>
                    <span class="font-semibold text-slate-800 block">Batas Minimum WD</span>
                    <span class="text-[11px] text-slate-500 font-mono">{{ fin($minWithdraw) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[16px]">percent</span>
                </div>
                <div>
                    <span class="font-semibold text-slate-800 block">Biaya Admin Transfer</span>
                    <span class="text-[11px] text-slate-500">{{ $adminFee > 0 ? fin($adminFee) : 'Gratis (Platform)' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rincian Transaksi Kurir -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="reconcileModal" onclick="if(event.target === this) closeReconcile()">
    <div class="card w-full max-w-lg overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs" id="rec-avatar">K</div>
                <div>
                    <h4 class="text-sm font-bold text-slate-900" id="rec-name">Kurir</h4>
                    <span class="text-[11px] text-slate-400">Rincian Transaksi Hari Ini</span>
                </div>
            </div>
            <button onclick="closeReconcile()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5 max-h-[60vh] overflow-y-auto space-y-3 text-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Daftar Kiriman Selesai</span>
            <div class="divide-y divide-slate-100" id="rec-orders"></div>

            <div class="rounded-lg bg-slate-50 border border-slate-100 p-3 space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Total Omzet:</span>
                    <span class="font-mono font-semibold text-slate-800" id="rec-omzet">Rp 0</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Komisi Platform <span id="rec-fee-rate"></span>:</span>
                    <span class="font-mono font-semibold text-rose-600" id="rec-fee">-Rp 0</span>
                </div>
                <div class="pt-2 border-t border-slate-200 flex items-center justify-between font-bold">
                    <span class="text-slate-800">Hak Bersih Mitra:</span>
                    <span class="font-mono text-emerald-600 text-sm" id="rec-net">Rp 0</span>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeReconcile()" type="button" class="btn btn-secondary text-xs h-8 px-3">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Modal: Rincian Trip -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="tripModal" onclick="if(event.target === this) closeTripModal()">
    <div class="card w-full max-w-lg overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary-600">map</span>
                <div>
                    <h4 class="text-sm font-bold text-slate-900">Rincian Pengiriman</h4>
                    <span class="text-[11px] text-slate-400" id="trip-subtitle">Trip selesai kurir hari ini</span>
                </div>
            </div>
            <button onclick="closeTripModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5 max-h-[60vh] overflow-y-auto divide-y divide-slate-100 text-xs" id="trip-list"></div>
        <div class="flex items-center justify-end px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeTripModal()" type="button" class="btn btn-secondary text-xs h-8 px-3">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- Modal: Penyesuaian Saldo -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="adjustModal" onclick="if(event.target === this) closeAdjustModal()">
    <div class="card w-full max-w-sm overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div>
                <h4 class="text-sm font-bold text-slate-900">Penyesuaian Saldo</h4>
                <span class="text-[11px] text-slate-400" id="adjustCourierName">Kurir</span>
            </div>
            <button onclick="closeAdjustModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form method="POST" action="" id="adjustForm" class="p-5 space-y-3.5 text-xs">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Penyesuaian</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="type" value="tambah" checked class="accent-primary-600">
                        <span>Tambah Saldo</span>
                    </label>
                    <label class="flex items-center justify-center gap-1.5 h-9 rounded-lg border border-slate-200 hover:bg-slate-50 font-medium cursor-pointer">
                        <input type="radio" name="type" value="debit" class="accent-primary-600">
                        <span>Debit / Potong</span>
                    </label>
                </div>
            </div>
            <div>
                <label for="adjustAmount" class="block text-xs font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                <input type="number" name="amount" id="adjustAmount" required min="1" class="form-input text-xs w-full" placeholder="Contoh: 10000">
            </div>
            <div>
                <label for="adjustNote" class="block text-xs font-semibold text-slate-700 mb-1">Catatan</label>
                <input type="text" name="note" id="adjustNote" maxlength="255" class="form-input text-xs w-full" placeholder="Alasan penyesuaian (opsional)">
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeAdjustModal()" class="btn btn-secondary text-xs h-8 px-3">Batal</button>
                <button type="submit" class="btn btn-primary text-xs h-8 px-3.5">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Atur Bagi Hasil -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="commissionModal" onclick="if(event.target === this) closeCommissionModal()">
    <div class="card w-full max-w-sm overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary-600">tune</span>
                <div>
                    <h4 class="text-sm font-bold text-slate-900">Atur Bagi Hasil</h4>
                    <span class="text-[11px] text-slate-400">Persentase komisi operasional</span>
                </div>
            </div>
            <button onclick="closeCommissionModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.ci-work.finance.commission') }}" class="p-5 space-y-3.5 text-xs">
            @csrf
            <div>
                <label for="commissionRate" class="block text-xs font-semibold text-slate-700 mb-1">Persentase Komisi Platform (%)</label>
                <div class="relative">
                    <input type="number" name="commission_rate" id="commissionRate" required min="0" max="50" step="0.5" value="{{ (int) $stats['commission_rate'] }}"
                        oninput="document.getElementById('commissionPreview').textContent = 'Porsi mitra: ' + (100 - Number(this.value || 0)) + '%'"
                        class="form-input text-xs w-full pr-8">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-mono">%</span>
                </div>
                <p class="text-[11px] text-emerald-600 font-medium mt-1" id="commissionPreview">Porsi mitra: {{ (int) (100 - $stats['commission_rate']) }}%</p>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeCommissionModal()" class="btn btn-secondary text-xs h-8 px-3">Batal</button>
                <button type="submit" class="btn btn-primary text-xs h-8 px-3.5">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Detail SOP Keuangan -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="sopModal" onclick="if(event.target === this) closeSopModal()">
    <div class="card w-full max-w-lg overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-amber-600">description</span>
                <h4 class="text-sm font-bold text-slate-900">SOP Keuangan &amp; Settlement</h4>
            </div>
            <button onclick="closeSopModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5 space-y-3 text-xs">
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">1. Kebijakan Bagi Hasil</span>
                <p class="text-slate-500 leading-relaxed">Platform memotong komisi <strong class="text-slate-800">{{ (int) $stats['commission_rate'] }}%</strong> dari total ongkir per kiriman. Sisa {{ (int) (100 - $stats['commission_rate']) }}% otomatis masuk ke saldo dompet mitra.</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">2. Setoran Kas Tunai COD</span>
                <p class="text-slate-500 leading-relaxed">Uang tunai hasil COD wajib diserahkan fisik ke Drop Point Bratang maksimal 1x24 jam setelah status order delivered.</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 space-y-1">
                <span class="font-bold text-slate-800 block">3. Penarikan Dana Mitra</span>
                <p class="text-slate-500 leading-relaxed">Penarikan saldo dompet dapat dilakukan minimal {{ fin($minWithdraw) }}. Biaya admin bank {{ $adminFee > 0 ? fin($adminFee) . ' per transaksi' : 'ditanggung platform' }}.</p>
            </div>
        </div>
        <div class="flex items-center justify-end px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeSopModal()" type="button" class="btn btn-secondary text-xs h-8 px-3">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal: Panduan Persetujuan -->
<div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4" id="guideModal" onclick="if(event.target === this) closeGuideModal()">
    <div class="card w-full max-w-md overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-blue-600">help</span>
                <h4 class="text-sm font-bold text-slate-900">Panduan Persetujuan Penarikan</h4>
            </div>
            <button onclick="closeGuideModal()" type="button" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-5 space-y-2.5 text-xs text-slate-600">
            <div class="flex items-start gap-2">
                <span class="font-bold text-primary-600">1.</span>
                <p>Verifikasi bahwa saldo dompet kurir mencukupi nominal yang diminta.</p>
            </div>
            <div class="flex items-start gap-2">
                <span class="font-bold text-primary-600">2.</span>
                <p>Pastikan nama pemilik rekening tujuan sesuai dengan nama di profil mitra kurir.</p>
            </div>
            <div class="flex items-start gap-2">
                <span class="font-bold text-primary-600">3.</span>
                <p>Klik tombol <strong>Setujui</strong> lalu lakukan transfer via internet banking perusahaan.</p>
            </div>
            <div class="flex items-start gap-2">
                <span class="font-bold text-primary-600">4.</span>
                <p>Setelah dana berhasil ditransfer, klik <strong>Tandai Selesai</strong>.</p>
            </div>
        </div>
        <div class="flex items-center justify-end px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <button onclick="closeGuideModal()" type="button" class="btn btn-primary text-xs h-8 px-3.5">Mengerti</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    window.__finance = @json($financeData);
    window.__commissionRate = {{ (float) $stats['commission_rate'] }};

    function rupiah(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    }

    // Rekap table search + filter
    function toggleFilterMenu() {
        document.getElementById('filterMenu').classList.toggle('hidden');
    }

    function filterRecaps() {
        const q = (document.getElementById('recapSearch').value || '').toLowerCase();
        const status = document.querySelector('input[name="courierStatusFilter"]:checked')?.value || 'semua';
        document.querySelectorAll('.rec-row').forEach(r => {
            const matchesText = r.dataset.search.indexOf(q) !== -1;
            const isActive = r.dataset.active === '1';
            const matchesStatus = status === 'semua' || (status === 'aktif' ? isActive : !isActive);
            r.classList.toggle('hidden', !(matchesText && matchesStatus));
        });
    }

    document.addEventListener('click', (e) => {
        const menu = document.getElementById('filterMenu');
        if (menu && !menu.classList.contains('hidden') && !e.target.closest('#filterMenu') && !e.target.closest('button[onclick*="toggleFilterMenu"]')) {
            menu.classList.add('hidden');
        }
    });

    // Withdrawal tabs
    function setWdTab(status, btn) {
        document.querySelectorAll('.wd-tab').forEach(t => {
            const active = t === btn;
            t.classList.toggle('border-primary-600', active);
            t.classList.toggle('bg-primary-50', active);
            t.classList.toggle('text-primary-700', active);
            t.classList.toggle('border-transparent', !active);
            t.classList.toggle('text-slate-600', !active);
            t.querySelector('[data-wd-count]')?.classList.toggle('bg-primary-600', active);
            t.querySelector('[data-wd-count]')?.classList.toggle('text-white', active);
            t.querySelector('[data-wd-count]')?.classList.toggle('bg-slate-100', !active);
            t.querySelector('[data-wd-count]')?.classList.toggle('text-slate-600', !active);
        });

        document.querySelectorAll('.wd-row').forEach(r => {
            r.classList.toggle('hidden', r.dataset.status !== status);
        });

        const empty = document.querySelector(`.wd-row[data-empty="1"][data-status="${status}"]`);
        const hasData = document.querySelector(`.wd-row:not([data-empty])[data-status="${status}"]:not(.hidden)`);
        if (empty) {
            empty.classList.toggle('hidden', !!hasData);
        }
    }

    // Reconcile modal
    function openReconcile(id) {
        const d = window.__finance[id];
        if (!d) return;
        document.getElementById('rec-avatar').textContent = (d.name || 'K').charAt(0).toUpperCase();
        document.getElementById('rec-name').textContent = d.name;
        document.getElementById('rec-fee-rate').textContent = '(' + window.__commissionRate + '%)';
        const list = document.getElementById('rec-orders');
        if (d.orders.length === 0) {
            list.innerHTML = '<p class="py-4 text-center text-slate-400 text-xs">Belum ada tugas selesai hari ini</p>';
        } else {
            list.innerHTML = d.orders.map(o => `
                <div class="flex items-center justify-between py-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded bg-slate-100 flex items-center justify-center text-slate-600 shrink-0">
                            <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-xs">#${o.tracking}</p>
                            <p class="text-[11px] text-slate-500 truncate max-w-[220px]">${o.route}</p>
                        </div>
                    </div>
                    <span class="font-mono font-bold text-xs text-slate-900">${rupiah(o.amount)}</span>
                </div>
            `).join('');
        }
        document.getElementById('rec-omzet').textContent = rupiah(d.omzet);
        document.getElementById('rec-fee').textContent = '- ' + rupiah(d.commission);
        document.getElementById('rec-net').textContent = rupiah(d.net);
        const m = document.getElementById('reconcileModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeReconcile() {
        const m = document.getElementById('reconcileModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // Trip modal
    function openTripModal(id) {
        const d = window.__finance[id];
        if (!d) return;
        document.getElementById('trip-subtitle').textContent = d.name + ' — ' + d.todayCount + ' kiriman';
        const list = document.getElementById('trip-list');
        if (d.orders.length === 0) {
            list.innerHTML = '<p class="py-6 text-center text-slate-400 text-xs">Belum ada pengiriman selesai hari ini</p>';
        } else {
            list.innerHTML = d.orders.map(o => `
                <div class="py-2.5 flex items-start gap-2">
                    <div class="w-7 h-7 rounded bg-primary-50 text-primary-600 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-slate-900 text-xs">#${o.tracking}</p>
                            <span class="font-mono font-bold text-emerald-600 text-xs">${rupiah(o.amount)}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-mono">${o.time}</p>
                        <p class="text-[11px] text-slate-600 truncate mt-0.5">${o.route}</p>
                    </div>
                </div>
            `).join('');
        }
        const m = document.getElementById('tripModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeTripModal() {
        const m = document.getElementById('tripModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // Adjust modal
    function openAdjustModal(btn) {
        document.getElementById('adjustForm').action = btn.dataset.route;
        document.getElementById('adjustCourierName').textContent = btn.dataset.name;
        document.getElementById('adjustAmount').value = '';
        document.getElementById('adjustNote').value = '';
        const m = document.getElementById('adjustModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeAdjustModal() {
        const m = document.getElementById('adjustModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // Commission modal
    function openCommissionModal() {
        const m = document.getElementById('commissionModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeCommissionModal() {
        const m = document.getElementById('commissionModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // SOP & Guide modals
    function openSopModal() {
        const m = document.getElementById('sopModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeSopModal() {
        const m = document.getElementById('sopModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    function openGuideModal() {
        const m = document.getElementById('guideModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeGuideModal() {
        const m = document.getElementById('guideModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    // ESC to close modals
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            ['reconcileModal', 'tripModal', 'adjustModal', 'commissionModal', 'sopModal', 'guideModal'].forEach(id => {
                const m = document.getElementById(id);
                if (m && !m.classList.contains('hidden')) {
                    m.classList.add('hidden');
                    m.classList.remove('flex');
                }
            });
        }
    });
</script>
@endpush
