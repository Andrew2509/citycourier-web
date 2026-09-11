@extends('layouts.admin')

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
$withdrawalTotalText = 'Menampilkan ' . ($withdrawals->total() > 0 ? $withdrawals->firstItem() . ' - ' . $withdrawals->lastItem() . ' dari ' : '0 dari ') . $withdrawals->total() . ' permintaan penarikan';
@endphp

@section('content')
<div class="flex flex-col w-full gap-space-xl">

    <!-- Flash -->
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-space-lg py-space-sm flex items-center gap-space-sm font-label-md text-label-md">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    <!-- Breadcrumb -->
    <div class="flex flex-col gap-space-2xs">
        <div class="flex items-center gap-space-xs text-secondary font-label-md">
            <span>City-Work Operasional</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-on-surface font-semibold">Keuangan & Setoran Kurir</span>
        </div>
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-space-lg">
            <div class="flex flex-col gap-space-2xs">
                <div class="flex items-center gap-space-md">
                    <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                        <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight leading-tight">Keuangan & Setoran Kurir</h1>
                        <p class="font-body-sm text-body-sm text-secondary">Rekapitulasi penghasilan, potongan komisi, dan penarikan dana mitra kurir</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-space-sm">
                <button onclick="openCommissionModal()" type="button"
                    class="h-10 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">adjust</span>
                    Atur Bagi Hasil
                </button>
                <a href="{{ route('admin.ci-work.finance.export') }}"
                    class="h-10 px-space-md rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">download</span>
                    Unduh Laporan Keuangan
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-space-md">
        <div class="rounded-xl bg-surface-container-lowest shadow-sm p-space-lg flex flex-col gap-space-md">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-primary-container text-primary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">trending_up</span>
                </div>
                <span class="inline-flex items-center gap-space-2xs px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-bold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    {{ $stats['delivered_today'] }} Pengiriman
                </span>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm text-secondary">Total Omzet Kotor</span>
                <span class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ fin($stats['omzet']) }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Status Sukses <span class="text-emerald-600 font-bold">100%</span></span>
            </div>
        </div>

        <div class="rounded-xl bg-surface-container-lowest shadow-sm p-space-lg flex flex-col gap-space-md">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                </div>
                <span class="inline-flex items-center gap-space-2xs px-2 py-1 rounded-full bg-primary-container text-primary font-label-sm text-label-sm font-bold">
                    {{ (int) (100 - $stats['commission_rate']) }}% Porsi
                </span>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm text-secondary">Pendapatan Bersih Mitra</span>
                <span class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ fin($stats['net']) }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Porsi hak bagi hasil mitra</span>
            </div>
        </div>

        <div class="rounded-xl bg-surface-container-lowest shadow-sm p-space-lg flex flex-col gap-space-md">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-surface-container text-secondary flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">percent</span>
                </div>
                <span class="inline-flex items-center gap-space-2xs px-2 py-1 rounded-full bg-surface-container text-secondary font-label-sm text-label-sm font-bold">
                    {{ (int) $stats['commission_rate'] }}% Margin
                </span>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm text-secondary">Potongan Komisi ({{ (int) $stats['commission_rate'] }}%)</span>
                <span class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ fin($stats['platform']) }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Fee operasional pembayaran</span>
            </div>
        </div>

        <div class="rounded-xl bg-surface-container-lowest shadow-sm p-space-lg flex flex-col gap-space-md">
            <div class="flex items-center justify-between">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">savings</span>
                </div>
                <span class="inline-flex items-center gap-space-2xs px-2 py-1 rounded-full bg-blue-50 text-blue-700 font-label-sm text-label-sm font-bold">
                    {{ $stats['pending_wd_count'] }} Antrean
                </span>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm text-secondary">Menunggu Penarikan</span>
                <span class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ fin($stats['pending_wd_amount']) }} <span class="text-label-sm text-secondary font-normal">Pending</span></span>
                <span class="font-label-sm text-label-sm text-secondary">Semua permintaan dikelola cepat</span>
            </div>
        </div>
    </div>

    <!-- SOP Callout -->
    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-space-lg flex flex-col md:flex-row md:items-center gap-space-md">
        <div class="flex items-center gap-space-md flex-1">
            <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">notifications_active</span>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <p class="font-headline-sm text-headline-sm text-on-surface font-semibold">Jadwal Setoran & Bagi Hasil Kurir</p>
                <p class="font-body-sm text-body-sm text-secondary">
                    Kebijakan bagi hasil memotong <span class="font-semibold text-on-surface">{{ (int) $stats['commission_rate'] }}%</span> dari omzet sebagai
                    biaya operasional platform. Setoran COD wajib disetorkan ke <span class="font-semibold text-on-surface">Drop Point Bratang</span> maksimal <span class="font-semibold text-on-surface">1x24 jam</span> setelah pengiriman selesai.
                </p>
            </div>
        </div>
        <button onclick="openSopModal()" type="button"
            class="h-9 px-space-md rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors shrink-0">
            <span class="material-symbols-outlined text-[18px]">description</span>
            Detail SOP Keuangan
        </button>
    </div>

    <!-- Section 1: Rekapitulasi -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-space-md px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">groups</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Data Rekapitulasi Keuangan Kurir</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Ringkasan omzet, komisi, dan saldo dompet per kurir aktif</span>
                </div>
            </div>

            <div class="flex items-center gap-space-sm">
                <div class="relative">
                    <span class="material-symbols-outlined text-[18px] text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
                    <input type="text" oninput="filterRecaps()"
                        class="h-9 pl-9 pr-3 rounded-lg border border-outline-variant bg-surface-container-lowest font-body-sm text-body-sm text-on-surface placeholder:text-secondary outline-none focus:border-primary w-52"
                        placeholder="Cari kurir..." id="recapSearch">
                </div>
                <div class="relative">
                    <button onclick="toggleFilterMenu()" type="button"
                        class="h-9 px-space-md rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                        <span class="material-symbols-outlined text-[18px]">tune</span>
                        Filter
                    </button>
                    <div id="filterMenu" class="absolute right-0 top-11 z-20 hidden bg-surface-container-lowest border border-outline-variant rounded-xl shadow-lg p-space-sm min-w-44">
                        <p class="font-label-sm text-label-sm text-secondary px-space-sm py-space-2xs">Status Kurir</p>
                        <label class="flex items-center gap-space-sm px-space-sm py-space-2xs hover:bg-surface-container-high rounded-lg cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="semua" checked onchange="filterRecaps()" class="accent-primary">
                            <span class="font-body-sm text-body-sm text-on-surface">Semua Status</span>
                        </label>
                        <label class="flex items-center gap-space-sm px-space-sm py-space-2xs hover:bg-surface-container-high rounded-lg cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="aktif" onchange="filterRecaps()" class="accent-primary">
                            <span class="font-body-sm text-body-sm text-on-surface">Kurir Aktif</span>
                        </label>
                        <label class="flex items-center gap-space-sm px-space-sm py-space-2xs hover:bg-surface-container-high rounded-lg cursor-pointer">
                            <input type="radio" name="courierStatusFilter" value="nonaktif" onchange="filterRecaps()" class="accent-primary">
                            <span class="font-body-sm text-body-sm text-on-surface">Kurir Nonaktif</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Tugas Selesai</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Total Omzet</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Potongan ({{ (int) $stats['commission_rate'] }}%)</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Pendapatan Bersih</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Saldo Dompet</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($recaps as $courier)
                    <tr class="hover:bg-surface transition-colors rec-row"
                        data-active="{{ $courier->is_active ? '1' : '0' }}"
                        data-search="{{ strtolower(($courier->user->name ?? '') . ' ' . ($courier->user->email ?? '') . ' ' . ($courier->user->phone ?? '') . ' ' . ($courier->phone ?? '')) }}">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="relative shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm">
                                        {{ strtoupper(substr($courier->user->name ?? 'K', 0, 1)) }}
                                    </div>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-surface-container-lowest {{ $courier->is_active ? 'bg-emerald-500' : 'bg-secondary' }}"></span>
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-space-xs">
                                        <p class="font-medium text-on-surface leading-none">{{ $courier->user->name ?? '-' }}</p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-primary-container text-primary font-label-sm text-[10px] font-bold tracking-wide">
                                            {{ $courier->is_active ? 'DRIVER AKTIF' : 'KURIR' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-secondary mt-1 leading-none">{{ $courier->user->phone ?? $courier->phone }} &bull; {{ $courier->user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 font-medium text-on-surface whitespace-nowrap">{{ $courier->today_count }} Kiriman</td>
                        <td class="py-4 px-4 font-bold text-on-surface whitespace-nowrap">{{ fin($courier->today_omzet) }}</td>
                        <td class="py-4 px-4 font-medium text-error whitespace-nowrap">-{{ fin($courier->commission) }}</td>
                        <td class="py-4 px-4 font-bold text-emerald-600 whitespace-nowrap">{{ fin($courier->net_earnings) }}</td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <p class="font-bold text-on-surface">{{ fin($courier->balance) }}</p>
                            @if($courier->balance > 0)
                                <span class="inline-flex items-center gap-space-2xs text-emerald-600 font-label-sm text-[11px] font-semibold">
                                    <span class="material-symbols-outlined text-[12px]">verified</span> Siap Ditarik
                                </span>
                            @elseif($courier->pending_balance > 0)
                                <span class="inline-flex items-center gap-space-2xs text-amber-600 font-label-sm text-[11px] font-semibold">
                                    <span class="material-symbols-outlined text-[12px]">schedule</span> Pending
                                </span>
                            @else
                                <span class="inline-flex items-center gap-space-2xs text-secondary font-label-sm text-[11px]">
                                    <span class="material-symbols-outlined text-[12px]">remove_circle_outline</span> Kosong
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <div class="inline-flex items-center justify-end gap-space-2xs">
                                <button onclick="openReconcile({{ $courier->id }})" type="button"
                                    class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-primary-container/30 transition-colors" title="Riwayat Transaksi">
                                    <span class="material-symbols-outlined text-[18px]">history</span>
                                </button>
                                <button onclick="openTripModal({{ $courier->id }})" type="button"
                                    class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-primary-container/30 transition-colors" title="Rincian Trip">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </button>
                                <button onclick="openAdjustModal(this)" type="button" data-name="{{ $courier->user->name ?? 'Kurir' }}" data-route="{{ route('admin.ci-work.finance.reconcile', $courier->id) }}"
                                    class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-primary-container/30 transition-colors" title="Penyesuaian Saldo">
                                    <span class="material-symbols-outlined text-[18px]">tune</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">groups</span>
                                <span class="font-body-sm text-body-sm">Belum ada data kurir</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between px-space-xl py-3.5 border-t border-surface-container-high">
            <span class="font-label-sm text-label-sm text-secondary">
                Menampilkan {{ $recaps->total() > 0 ? ($recaps->firstItem() === $recaps->lastItem() ? $recaps->firstItem() : $recaps->firstItem() . ' - ' . $recaps->lastItem()) : 0 }} dari {{ $recaps->total() }} mitra kurir aktif
            </span>
            {{ $recaps->links() }}
        </div>
    </div>

    <!-- Section 2: Penarikan Dana -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-space-md px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 font-bold">
                    <span class="material-symbols-outlined text-[20px]">savings</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Permintaan Penarikan Dana</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Kelola permintaan penarikan dari mitra kurir</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <a href="{{ url()->current() }}"
                    class="h-8 px-space-md rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center gap-space-2xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[16px]">refresh</span>
                    Refresh Data
                </a>
                <button onclick="openGuideModal()" type="button"
                    class="h-8 px-space-md rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center gap-space-2xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[16px]">help</span>
                    Panduan Persetujuan
                </button>
            </div>
        </div>

        <!-- Segmented Tabs -->
        <div class="flex items-center gap-space-xs px-space-xl pt-space-md border-b border-surface-container-high">
            <button onclick="setWdTab('pending', this)" data-wd-tab="pending" type="button"
                class="wd-tab px-space-md py-2 rounded-lg font-label-md text-label-md font-semibold inline-flex items-center gap-space-2xs border-2 border-primary bg-primary-container text-primary">
                <span class="material-symbols-outlined text-[16px]">pending_actions</span>
                Menunggu Persetujuan
                <span class="px-1.5 rounded-full bg-primary text-on-primary text-[11px] font-bold" data-wd-count="pending">{{ $withdrawalCounts['pending'] }}</span>
            </button>
            <button onclick="setWdTab('completed', this)" data-wd-tab="completed" type="button"
                class="wd-tab px-space-md py-2 rounded-lg font-label-md text-label-md font-semibold inline-flex items-center gap-space-2xs border-2 border-transparent text-secondary hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                Berhasil Ditransfer
                <span class="px-1.5 rounded-full bg-surface-container text-secondary text-[11px] font-bold" data-wd-count="completed">{{ $withdrawalCounts['completed'] }}</span>
            </button>
            <button onclick="setWdTab('rejected', this)" data-wd-tab="rejected" type="button"
                class="wd-tab px-space-md py-2 rounded-lg font-label-md text-label-md font-semibold inline-flex items-center gap-space-2xs border-2 border-transparent text-secondary hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[16px]">cancel</span>
                Ditolak
                <span class="px-1.5 rounded-full bg-surface-container text-secondary text-[11px] font-bold" data-wd-count="rejected">{{ $withdrawalCounts['rejected'] }}</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Jumlah</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Metode</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @foreach($withdrawals as $wd)
                    <tr class="hover:bg-surface transition-colors wd-row" data-status="{{ in_array($wd->status, ['approved', 'completed']) ? 'completed' : $wd->status }}">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($wd->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-on-surface">{{ $wd->courier->user->name ?? '-' }}</p>
                                    <p class="text-xs text-secondary">{{ \Carbon\Carbon::parse($wd->created_at)->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 font-bold text-on-surface whitespace-nowrap">{{ fin($wd->amount) }}</td>
                        <td class="py-4 px-4">
                            <div>
                                <p class="font-medium text-on-surface">{{ $wd->bank_name }}</p>
                                <p class="text-xs text-secondary">{{ $wd->account_number }} - {{ $wd->account_name }}</p>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            @if($wd->status === 'pending')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-amber-50 text-amber-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Menunggu
                                </span>
                            @elseif($wd->status === 'approved')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                    Disetujui
                                </span>
                            @elseif($wd->status === 'completed')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-blue-50 text-blue-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    Selesai
                                </span>
                            @elseif($wd->status === 'rejected')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-red-50 text-red-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                                    Ditolak
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            @if($wd->status === 'pending')
                                <div class="inline-flex items-center justify-end gap-space-xs">
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Setujui penarikan {{ fin($wd->amount) }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="h-8 px-space-sm rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 inline-flex items-center gap-space-2xs font-label-sm text-label-sm font-bold transition-colors" title="Setujui">
                                            <span class="material-symbols-outlined text-[16px]">check</span>
                                            Setujui
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('Tolak penarikan {{ fin($wd->amount) }} untuk {{ $wd->courier->user->name ?? 'kurir' }}?')">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button type="submit" class="h-8 px-space-sm rounded-lg bg-red-50 text-red-700 hover:bg-red-100 inline-flex items-center gap-space-2xs font-label-sm text-label-sm font-bold transition-colors" title="Tolak">
                                            <span class="material-symbols-outlined text-[16px]">close</span>
                                            Tolak
                                        </button>
                                    </form>
                                </div>
                            @elseif($wd->status === 'approved')
                                <form action="{{ route('admin.ci-work.finance.withdrawal.update', $wd->id) }}" method="POST" class="inline-block"
                                    onsubmit="return confirm('Tandai penarikan {{ fin($wd->amount) }} telah selesai ditransfer?')">
                                    @csrf
                                    <input type="hidden" name="status" value="completed">
                                    <button type="submit" class="h-8 px-space-sm rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center gap-space-2xs font-label-sm text-label-sm font-semibold transition-colors" title="Tandai Selesai">
                                        <span class="material-symbols-outlined text-[16px]">task_alt</span>
                                        Tandai Selesai
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-secondary">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    @if($withdrawalCounts['pending'] === 0)
                    <tr class="wd-row" data-status="pending" data-empty="1">
                        <td colspan="5" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">pending_actions</span>
                                <span class="font-body-sm text-body-sm">Belum ada permintaan penarikan menunggu persetujuan</span>
                            </div>
                        </td>
                    </tr>
                    @endif

                    @if($withdrawalCounts['completed'] === 0)
                    <tr class="wd-row" data-status="completed" data-empty="1">
                        <td colspan="5" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">savings</span>
                                <span class="font-body-sm text-body-sm">Belum ada penarikan yang berhasil ditransfer</span>
                            </div>
                        </td>
                    </tr>
                    @endif

                    @if($withdrawalCounts['rejected'] === 0)
                    <tr class="wd-row" data-status="rejected" data-empty="1">
                        <td colspan="5" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">cancel</span>
                                <span class="font-body-sm text-body-sm">Belum ada penarikan yang ditolak</span>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $withdrawals->withQueryString()->links() }}
        </div>

        <!-- Telemetry Footer -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-space-md px-space-xl py-space-md border-t border-surface-container-high bg-surface-container-low/40">
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">online_prediction</span>
                </div>
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md text-on-surface font-semibold">Gateway Pencairan Online</span>
                    <span class="font-label-sm text-label-sm text-secondary">Layanan pembayaran terintegrasi</span>
                </div>
            </div>
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">currency_exchange</span>
                </div>
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md text-on-surface font-semibold">Batas Minimum Penarikan</span>
                    <span class="font-label-sm text-label-sm text-secondary">{{ fin($minWithdraw) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">percent</span>
                </div>
                <div class="flex flex-col">
                    <span class="font-label-md text-label-md text-on-surface font-semibold">Biaya Admin Bank</span>
                    <span class="font-label-sm text-label-sm text-secondary">{{ $adminFee > 0 ? fin($adminFee) : 'Gratis (Ditanggung)' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Rincian Transaksi Kurir ─────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="reconcileModal" onclick="if(event.target === this) closeReconcile()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-space-md px-space-lg py-space-md border-b border-surface-container-high">
            <div class="w-10 h-10 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                <span id="rec-avatar">K</span>
            </div>
            <div class="flex flex-col flex-1">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface" id="rec-name">Kurir</h4>
                <span class="font-label-sm text-label-sm text-secondary">Rincian Transaksi Kurir</span>
            </div>
            <button onclick="closeReconcile()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-space-lg py-space-sm">
            <p class="font-label-md text-label-md text-secondary font-semibold mb-space-2xs">Tugas Hari Ini</p>
            <div class="divide-y divide-surface-container-high/60" id="rec-orders"></div>
            <div class="mt-space-md rounded-lg bg-surface-container/50 p-space-md flex flex-col gap-space-2xs">
                <div class="flex items-center justify-between font-body-sm text-body-sm">
                    <span class="text-secondary">Total Omzet</span>
                    <span class="font-semibold text-on-surface" id="rec-omzet">Rp 0</span>
                </div>
                <div class="flex items-center justify-between font-body-sm text-body-sm">
                    <span class="text-secondary">Komisi Platform <span id="rec-fee-rate"></span></span>
                    <span class="font-semibold text-error" id="rec-fee">-Rp 0</span>
                </div>
                <div class="pt-space-xs border-t border-dashed border-outline-variant flex items-center justify-between font-label-md text-label-md">
                    <span class="text-secondary font-semibold">Hak Bersih Mitra</span>
                    <span class="font-bold text-emerald-600" id="rec-net">Rp 0</span>
                </div>
            </div>
        </div>
        <div class="px-space-lg py-space-md border-t border-surface-container-high flex justify-end">
            <button onclick="closeReconcile()" type="button" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- ── Modal: Rincian Trip ────────────────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="tripModal" onclick="if(event.target === this) closeTripModal()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-space-md px-space-lg py-space-md border-b border-surface-container-high">
            <div class="w-10 h-10 rounded-lg bg-primary-container text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">map</span>
            </div>
            <div class="flex flex-col flex-1">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface">Rincian Pengiriman</h4>
                <span class="font-label-sm text-label-sm text-secondary" id="trip-subtitle">Trip selesai kurir hari ini</span>
            </div>
            <button onclick="closeTripModal()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-space-lg py-space-sm divide-y divide-surface-container-high/60" id="trip-list"></div>
        <div class="px-space-lg py-space-md border-t border-surface-container-high flex justify-end">
            <button onclick="closeTripModal()" type="button" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                Tutup
            </button>
        </div>
    </div>
</div>

<!-- ── Modal: Penyesuaian Saldo ───────────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="adjustModal" onclick="if(event.target === this) closeAdjustModal()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-sm overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between px-space-lg py-space-md border-b border-surface-container-high">
            <div class="flex flex-col">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface">Penyesuaian Saldo</h4>
                <span class="font-label-sm text-label-sm text-secondary" id="adjustCourierName">Kurir</span>
            </div>
            <button onclick="closeAdjustModal()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" action="" id="adjustForm" class="flex flex-col gap-space-md px-space-lg py-space-md">
            @csrf
            <div class="flex flex-col gap-space-2xs">
                <label class="font-label-sm text-label-sm text-secondary font-semibold">Jenis Penyesuaian</label>
                <div class="flex gap-space-sm">
                    <label class="flex-1 flex items-center justify-center gap-space-xs h-10 rounded-lg border-2 border-primary bg-primary-container text-primary font-label-md text-label-md font-semibold cursor-pointer">
                        <input type="radio" name="type" value="tambah" checked class="accent-primary">
                        Tambah Saldo
                    </label>
                    <label class="flex-1 flex items-center justify-center gap-space-xs h-10 rounded-lg border-2 border-outline-variant text-secondary hover:bg-surface-container-high font-label-md text-label-md font-semibold cursor-pointer">
                        <input type="radio" name="type" value="debit" class="accent-primary">
                        Debit
                    </label>
                </div>
            </div>
            <div class="flex flex-col gap-space-2xs">
                <label for="adjustAmount" class="font-label-sm text-label-sm text-secondary font-semibold">Nominal (Rp)</label>
                <input type="number" name="amount" id="adjustAmount" required min="1"
                    class="h-10 px-3 rounded-lg border border-outline-variant bg-surface-container-lowest font-body-sm text-body-sm text-on-surface outline-none focus:border-primary"
                    placeholder="Contoh: 10000">
            </div>
            <div class="flex flex-col gap-space-2xs">
                <label for="adjustNote" class="font-label-sm text-label-sm text-secondary font-semibold">Catatan</label>
                <input type="text" name="note" id="adjustNote" maxlength="255"
                    class="h-10 px-3 rounded-lg border border-outline-variant bg-surface-container-lowest font-body-sm text-body-sm text-on-surface outline-none focus:border-primary"
                    placeholder="Opsional — alasan penyesuaian">
            </div>
            <div class="flex justify-end gap-space-sm pt-space-sm border-t border-surface-container-high">
                <button type="button" onclick="closeAdjustModal()" class="h-9 px-space-md rounded-lg text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-semibold transition-colors">Batal</button>
                <button type="submit" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Atur Bagi Hasil ─────────────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="commissionModal" onclick="if(event.target === this) closeCommissionModal()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-sm overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-space-md px-space-lg py-space-md border-b border-surface-container-high">
            <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[20px]">adjust</span>
            </div>
            <div class="flex flex-col flex-1">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface">Atur Bagi Hasil</h4>
                <span class="font-label-sm text-label-sm text-secondary">Persentase komisi platform per pengiriman</span>
            </div>
            <button onclick="closeCommissionModal()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.ci-work.finance.commission') }}" class="flex flex-col gap-space-md px-space-lg py-space-md">
            @csrf
            <div class="flex flex-col gap-space-2xs">
                <label for="commissionRate" class="font-label-sm text-label-sm text-secondary font-semibold">Persentase Komisi (%)</label>
                <div class="relative">
                    <input type="number" name="commission_rate" id="commissionRate" required min="0" max="50" step="0.5" value="{{ (int) $stats['commission_rate'] }}"
                        oninput="document.getElementById('commissionPreview').textContent = 'Porsi mitra: ' + (100 - Number(this.value || 0)) + '%'"
                        class="h-10 px-3 pr-10 rounded-lg border border-outline-variant bg-surface-container-lowest font-body-sm text-body-sm text-on-surface outline-none focus:border-primary w-full">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-secondary font-body-sm text-body-sm">%</span>
                </div>
                <p class="font-label-sm text-label-sm text-secondary" id="commissionPreview">Porsi mitra: {{ (int) (100 - $stats['commission_rate']) }}%</p>
            </div>
            <div class="flex justify-end gap-space-sm pt-space-sm border-t border-surface-container-high">
                <button type="button" onclick="closeCommissionModal()" class="h-9 px-space-md rounded-lg text-on-surface hover:bg-surface-container-high font-label-md text-label-md font-semibold transition-colors">Batal</button>
                <button type="submit" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Detail SOP Keuangan ─────────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="sopModal" onclick="if(event.target === this) closeSopModal()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-space-md px-space-lg py-space-md border-b border-surface-container-high">
            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700">
                <span class="material-symbols-outlined text-[20px]">description</span>
            </div>
            <div class="flex flex-col flex-1">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface">Detail SOP Keuangan</h4>
                <span class="font-label-sm text-label-sm text-secondary">Standar Operasional Prosedur setoran & bagi hasil</span>
            </div>
            <button onclick="closeSopModal()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-space-lg py-space-md flex flex-col gap-space-md">
            <div class="flex items-start gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-primary-container text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">percent</span></div>
                <div class="flex flex-col gap-space-2xs">
                    <p class="font-label-md text-label-md text-on-surface font-semibold">1. Kebijakan Bagi Hasil</p>
                    <p class="font-body-sm text-body-sm text-secondary">Platform memotong komisi <span class="font-semibold text-on-surface">{{ (int) $stats['commission_rate'] }}%</span> dari total omzet per tugas. Sisa {{ (int) (100 - $stats['commission_rate']) }}% menjadi hak bersih mitra kurir.</p>
                </div>
            </div>
            <div class="flex items-start gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-primary-container text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">payments</span></div>
                <div class="flex flex-col gap-space-2xs">
                    <p class="font-label-md text-label-md text-on-surface font-semibold">2. Setoran COD</p>
                    <p class="font-body-sm text-body-sm text-secondary">Setoran tunai COD wajib disetorkan ke <span class="font-semibold text-on-surface">Drop Point Bratang</span> maksimal <span class="font-semibold text-on-surface">1x24 jam</span> setelah pengiriman selesai.</p>
                </div>
            </div>
            <div class="flex items-start gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-primary-container text-primary flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-[18px]">account_balance_wallet</span></div>
                <div class="flex flex-col gap-space-2xs">
                    <p class="font-label-md text-label-md text-on-surface font-semibold">3. Penarikan Dana</p>
                    <p class="font-body-sm text-body-sm text-secondary">Saldo dompet dapat ditarik dengan batas minimum {{ fin($minWithdraw) }}. Biaya admin bank {{ $adminFee > 0 ? fin($adminFee) . ' per transaksi' : 'ditanggung platform' }}.</p>
                </div>
            </div>
        </div>
        <div class="px-space-lg py-space-md border-t border-surface-container-high flex justify-end">
            <button onclick="closeSopModal()" type="button" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">Tutup</button>
        </div>
    </div>
</div>

<!-- ── Modal: Panduan Persetujuan ─────────────────────────────── -->
<div class="fixed inset-0 z-50 bg-inverse-surface/40 backdrop-blur-sm hidden items-center justify-center p-space-md" id="guideModal" onclick="if(event.target === this) closeGuideModal()">
    <div class="bg-surface-container-lowest rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center gap-space-md px-space-lg py-space-md border-b border-surface-container-high">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                <span class="material-symbols-outlined text-[20px]">help</span>
            </div>
            <div class="flex flex-col flex-1">
                <h4 class="font-headline-sm text-headline-sm font-bold text-on-surface">Panduan Persetujuan Penarikan</h4>
                <span class="font-label-sm text-label-sm text-secondary">Langkah pemrosesan penarikan dana kurir</span>
            </div>
            <button onclick="closeGuideModal()" type="button" class="p-1.5 rounded-lg text-secondary hover:bg-surface-container-high transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-space-lg py-space-md flex flex-col gap-space-md">
            <div class="flex items-start gap-space-sm">
                <span class="font-label-md font-bold text-primary">1.</span>
                <p class="font-body-sm text-body-sm text-secondary">Pastikan saldo dompet kurir mencukupi nominal penarikan.</p>
            </div>
            <div class="flex items-start gap-space-sm">
                <span class="font-label-md font-bold text-primary">2.</span>
                <p class="font-body-sm text-body-sm text-secondary">Verifikasi data rekening tujuan sesuai dengan yang terdaftar di profil kurir.</p>
            </div>
            <div class="flex items-start gap-space-sm">
                <span class="font-label-md font-bold text-primary">3.</span>
                <p class="font-body-sm text-body-sm text-secondary">Klik <span class="font-semibold text-emerald-600">Setujui</span> untuk melanjutkan pencairan atau <span class="font-semibold text-red-600">Tolak</span> bila data tidak sesuai.</p>
            </div>
            <div class="flex items-start gap-space-sm">
                <span class="font-label-md font-bold text-primary">4.</span>
                <p class="font-body-sm text-body-sm text-secondary">Setelah transfer diproses bank, tandai penarikan sebagai <span class="font-semibold text-on-surface">Selesai</span>.</p>
            </div>
        </div>
        <div class="px-space-lg py-space-md border-t border-surface-container-high flex justify-end">
            <button onclick="closeGuideModal()" type="button" class="h-9 px-space-md rounded-lg bg-primary-container text-on-primary hover:bg-primary inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                Mengerti
            </button>
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

    // ── Rekap table search + filter ──────────────────────────────
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

    // ── Withdrawal tabs ──────────────────────────────────────────
    function setWdTab(status, btn) {
        document.querySelectorAll('.wd-tab').forEach(t => {
            const active = t === btn;
            t.classList.toggle('border-primary', active);
            t.classList.toggle('bg-primary-container', active);
            t.classList.toggle('text-primary', active);
            t.classList.toggle('border-transparent', !active);
            t.classList.toggle('text-secondary', !active);
            t.classList.toggle('hover:bg-surface-container-high', !active);
            t.querySelector('[data-wd-count]')?.classList.toggle('bg-primary', active);
            t.querySelector('[data-wd-count]')?.classList.toggle('text-on-primary', active);
            t.querySelector('[data-wd-count]')?.classList.toggle('bg-surface-container', !active);
            t.querySelector('[data-wd-count]')?.classList.toggle('text-secondary', !active);
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

    // ── Reconcile modal ──────────────────────────────────────────
    function openReconcile(id) {
        const d = window.__finance[id];
        if (!d) return;
        document.getElementById('rec-avatar').textContent = (d.name || 'K').charAt(0).toUpperCase();
        document.getElementById('rec-name').textContent = d.name;
        document.getElementById('rec-fee-rate').textContent = '(' + window.__commissionRate + '%)';
        const list = document.getElementById('rec-orders');
        if (d.orders.length === 0) {
            list.innerHTML = '<p class="py-3 text-center text-secondary text-sm">Belum ada tugas selesai hari ini</p>';
        } else {
            list.innerHTML = d.orders.map(o => `
                <div class="flex items-center justify-between py-3">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-9 h-9 rounded-lg bg-surface-container flex items-center justify-center text-secondary shrink-0">
                            <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                        </div>
                        <div class="flex flex-col">
                            <p class="font-medium text-on-surface text-sm">Trip ${o.tracking}</p>
                            <p class="text-xs text-secondary">${o.route}</p>
                        </div>
                    </div>
                    <span class="font-bold text-sm text-on-surface whitespace-nowrap">${rupiah(o.amount)}</span>
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

    // ── Trip modal ───────────────────────────────────────────────
    function openTripModal(id) {
        const d = window.__finance[id];
        if (!d) return;
        document.getElementById('trip-subtitle').textContent = d.name + ' — ' + d.todayCount + ' kiriman selesai';
        const list = document.getElementById('trip-list');
        if (d.orders.length === 0) {
            list.innerHTML = '<p class="py-6 text-center text-secondary text-sm">Belum ada pengiriman selesai hari ini</p>';
        } else {
            list.innerHTML = d.orders.map(o => `
                <div class="py-3 flex gap-space-sm">
                    <div class="w-9 h-9 rounded-lg bg-primary-container text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                    </div>
                    <div class="flex flex-col flex-1 gap-space-2xs">
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-on-surface text-sm">${o.tracking}</p>
                            <span class="font-bold text-emerald-600 text-sm">${rupiah(o.amount)}</span>
                        </div>
                        <p class="text-xs text-secondary">${o.time}</p>
                        <p class="text-xs text-secondary">${o.route}</p>
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

    // ── Adjust modal ─────────────────────────────────────────────
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

    // ── Commission modal ─────────────────────────────────────────
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

    // ── SOP & Guide modals ───────────────────────────────────────
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