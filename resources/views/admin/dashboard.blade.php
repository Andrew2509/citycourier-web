@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Dashboard</h1>
                <span class="badge badge-green">
                    <span class="badge-dot"></span>
                    Sistem Aktif
                </span>
            </div>
            <p class="page-subtitle">
                Ringkasan operasional City Courier &mdash; {{ now()->locale('id')->translatedFormat('l, d F Y') }}
            </p>
        </div>

        <div class="page-actions">
            <a href="{{ route('admin.shipments.index') }}" class="btn btn-secondary">
                <span class="material-symbols-outlined text-slate-500">local_shipping</span>
                <span>Daftar Pengiriman</span>
            </a>
            @canPerm('couriers.view')
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="btn btn-primary">
                <span class="material-symbols-outlined">how_to_reg</span>
                <span>Verifikasi Kurir</span>
                @if(($stats['unverified_couriers'] ?? 0) > 0)
                    <span class="rounded bg-white/20 px-1.5 py-0.5 text-[11px] font-bold text-white ml-0.5">
                        {{ $stats['unverified_couriers'] }}
                    </span>
                @endif
            </a>
            @endcanPerm
        </div>
    </div>

    {{-- 4 Stat Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {{-- Card 1: Total Pengiriman --}}
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="stat-label">Total Pengiriman</div>
                    <div class="stat-value">
                        <span data-cc-count="{{ $stats['total_orders'] ?? 0 }}">{{ number_format($stats['total_orders'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="stat-icon bg-brand-50 text-brand-600">
                    <span class="material-symbols-outlined">inventory_2</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between gap-2 text-xs text-slate-500">
                <span class="flex items-center gap-1.5 font-medium text-amber-700">
                    <span class="badge-dot bg-amber-500"></span>
                    {{ number_format($stats['pending_orders'] ?? 0) }} menunggu
                </span>
                <span class="text-slate-400">Hari ini: {{ number_format($stats['today_orders'] ?? 0) }}</span>
            </div>
        </div>

        {{-- Card 2: Pengiriman Berlangsung --}}
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="stat-label">Sedang Berlangsung</div>
                    <div class="stat-value text-blue-700">
                        <span data-cc-count="{{ $stats['active_shipments'] ?? 0 }}">{{ number_format($stats['active_shipments'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="stat-icon bg-blue-50 text-blue-600">
                    <span class="material-symbols-outlined">local_shipping</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between gap-2 text-xs text-slate-500">
                <span class="font-medium text-slate-600">Dalam perjalanan</span>
                <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}" class="text-brand-700 hover:underline">
                    Lihat peta &rarr;
                </a>
            </div>
        </div>

        {{-- Card 3: Pengiriman Selesai --}}
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="stat-label">Pengiriman Selesai</div>
                    <div class="stat-value text-emerald-700">
                        <span data-cc-count="{{ $stats['completed_orders'] ?? 0 }}">{{ number_format($stats['completed_orders'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="stat-icon bg-emerald-50 text-emerald-600">
                    <span class="material-symbols-outlined">task_alt</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between gap-2 text-xs text-slate-500">
                <span class="text-slate-600">
                    Hari ini: <strong class="text-emerald-700">{{ number_format($stats['today_completed_orders'] ?? 0) }}</strong>
                </span>
                <a href="{{ route('admin.orders', ['status' => 'delivered']) }}" class="text-slate-400 hover:text-brand-700 hover:underline">
                    Lihat semua &rarr;
                </a>
            </div>
        </div>

        {{-- Card 4: Pengiriman Bermasalah --}}
        <div class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="stat-label">Pengiriman Bermasalah</div>
                    <div class="stat-value text-red-700">
                        <span data-cc-count="{{ $stats['cancelled_orders'] ?? 0 }}">{{ number_format($stats['cancelled_orders'] ?? 0) }}</span>
                    </div>
                </div>
                <div class="stat-icon bg-red-50 text-red-600">
                    <span class="material-symbols-outlined">error</span>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 flex items-center justify-between gap-2 text-xs text-slate-500">
                <span class="text-slate-600">
                    Hari ini: <strong class="text-red-700">{{ number_format($stats['today_cancelled_orders'] ?? 0) }}</strong>
                </span>
                @if(($stats['cancelled_orders'] ?? 0) > 0)
                    <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" class="text-slate-400 hover:text-brand-700 hover:underline">
                        Tinjau &rarr;
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Grafik Aktivitas & Pendapatan --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Grafik aktivitas 7 hari (data pesanan nyata) --}}
        <div class="lg:col-span-2 card">
            <div class="card-header flex-wrap gap-2">
                <div>
                    <div class="card-title">Aktivitas 7 Hari Terakhir</div>
                    <div class="card-subtitle">Pesanan dibuat vs selesai per hari</div>
                </div>
                <div class="flex items-center gap-3 text-[11.5px] text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-brand-500"></span> Dibuat</span>
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span> Selesai</span>
                </div>
            </div>

            @php
                $weekCreated = array_sum(array_column($dailyActivity, 'orders'));
                $weekCompleted = array_sum(array_column($dailyActivity, 'completed'));
            @endphp

            <div class="card-body">
                <div class="chart-grid relative flex h-44 items-stretch gap-2 sm:gap-3">
                    @foreach($dailyActivity as $day)
                        @php
                            $hOrders = ($day['orders'] > 0) ? max(6, (int) round($day['orders'] / $activityMaximum * 100)) : 0;
                            $hDone   = ($day['completed'] > 0) ? max(6, (int) round($day['completed'] / $activityMaximum * 100)) : 0;
                        @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center">
                            <div class="flex h-full w-full items-end justify-center gap-1"
                                 title="{{ $day['date'] }} &mdash; {{ $day['orders'] }} dibuat, {{ $day['completed'] }} selesai">
                                @if($hOrders > 0)
                                    <div data-cc-bar class="relative w-2.5 rounded-t-sm bg-brand-500 sm:w-3.5" style="height: {{ $hOrders }}%">
                                        <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-slate-600">{{ $day['orders'] }}</span>
                                    </div>
                                @endif
                                @if($hDone > 0)
                                    <div data-cc-bar class="relative w-2.5 rounded-t-sm bg-emerald-500 sm:w-3.5" style="height: {{ $hDone }}%">
                                        <span class="absolute -top-5 left-1/2 -translate-x-1/2 text-[10px] font-semibold text-emerald-700">{{ $day['completed'] }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="mt-2 text-center leading-tight">
                                <div class="text-[10.5px] font-medium {{ $loop->last ? 'text-slate-900' : 'text-slate-500' }}">{{ $day['label'] }}</div>
                                <div class="hidden text-[10px] text-slate-400 sm:block">{{ $day['date'] }}</div>
                            </div>
                        </div>
                    @endforeach

                    @if($weekCreated === 0 && $weekCompleted === 0)
                        <div class="pointer-events-none absolute inset-x-0 top-6 text-center">
                            <div class="text-[13px] font-medium text-slate-400">Belum ada aktivitas pesanan</div>
                            <div class="text-[11.5px] text-slate-400">Grafik akan terisi otomatis dari pesanan nyata.</div>
                        </div>
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    <span>Total dibuat: <strong class="text-slate-800">{{ number_format($weekCreated) }}</strong></span>
                    <span>Total selesai: <strong class="text-emerald-700">{{ number_format($weekCompleted) }}</strong></span>
                    <span>Tingkat penyelesaian: <strong class="text-slate-800">{{ $weekCreated > 0 ? round($weekCompleted / $weekCreated * 100) : 0 }}%</strong></span>
                </div>
            </div>
        </div>

        {{-- Kolom kanan: ringkasan pendapatan + distribusi status --}}
        <div class="flex flex-col gap-5">
        {{-- Ringkasan pendapatan --}}
        <div class="card flex flex-col">
            <div class="card-header">
                <div class="flex items-center gap-2.5">
                    <div class="stat-icon h-9 w-9 bg-slate-100 text-slate-700">
                        <span class="material-symbols-outlined">payments</span>
                    </div>
                    <div>
                        <div class="card-title">Pendapatan</div>
                        <div class="card-subtitle">Dari pesanan selesai</div>
                    </div>
                </div>
            </div>

            <div class="card-body flex flex-1 flex-col">
                <div>
                    <div class="stat-label">Hari Ini</div>
                    <div class="stat-value text-2xl">
                        Rp <span data-cc-count="{{ $stats['today_revenue'] ?? 0 }}">{{ number_format($stats['today_revenue'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="mt-4 space-y-3 border-t border-slate-100 pt-3.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Total pendapatan</span>
                        <span class="font-semibold text-slate-900">Rp <span data-cc-count="{{ $stats['total_revenue'] ?? 0 }}">{{ number_format($stats['total_revenue'] ?? 0, 0, ',', '.') }}</span></span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Pesanan selesai hari ini</span>
                        <span class="font-semibold text-slate-900">{{ number_format($stats['today_completed_orders'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Pesanan hari ini</span>
                        <span class="font-semibold text-slate-900">{{ number_format($stats['today_orders'] ?? 0) }}</span>
                    </div>
                </div>

                @canPerm('finance_reports.view')
                <div class="mt-auto pt-4">
                    <a href="{{ route('admin.finance.reports') }}" class="flex w-full items-center justify-between rounded-md border border-slate-200 px-3 py-2.5 text-xs font-medium text-slate-700 transition-colors hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700">
                        <span class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">summarize</span>
                            Laporan Keuangan
                        </span>
                        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                    </a>
                </div>
                @endcanPerm
            </div>
        </div>

        {{-- Distribusi status pengiriman (donut SVG, data nyata dari $stats) --}}
        <div class="card flex flex-col">
            <div class="card-header">
                <div>
                    <div class="card-title">Status Pengiriman</div>
                    <div class="card-subtitle">Distribusi seluruh pesanan</div>
                </div>
            </div>

            <div class="card-body flex flex-1 flex-col items-center justify-center gap-4 sm:flex-row sm:gap-6">
                @php
                    $dTotal = (int) ($stats['total_orders'] ?? 0);
                    $dSegs = [
                        ['label' => 'Selesai', 'v' => (int) ($stats['completed_orders'] ?? 0), 'color' => '#10b981'],
                        ['label' => 'Berlangsung', 'v' => (int) ($stats['active_shipments'] ?? 0), 'color' => '#3b82f6'],
                        ['label' => 'Bermasalah', 'v' => (int) ($stats['cancelled_orders'] ?? 0), 'color' => '#ef4444'],
                        ['label' => 'Menunggu', 'v' => max(0, $dTotal - (int) ($stats['completed_orders'] ?? 0) - (int) ($stats['active_shipments'] ?? 0) - (int) ($stats['cancelled_orders'] ?? 0)), 'color' => '#cbd5e1'],
                    ];
                    $dR = 40;
                    $dC = 2 * M_PI * $dR;
                    $dAccDeg = 0;
                @endphp

                @if($dTotal <= 0)
                    <div class="flex flex-1 flex-col items-center justify-center gap-1 py-8 text-center">
                        <span class="material-symbols-outlined text-3xl text-slate-300">donut_large</span>
                        <div class="text-[13px] font-medium text-slate-400">Belum ada pesanan</div>
                        <div class="text-[11.5px] text-slate-400">Distribusi status tampil otomatis dari data nyata.</div>
                    </div>
                @else
                <div class="relative shrink-0">
                    <svg viewBox="0 0 100 100" class="h-32 w-32 -rotate-90" role="img" aria-label="Distribusi status pengiriman">
                        <circle cx="50" cy="50" r="{{ $dR }}" fill="none" stroke="#f1f5f9" stroke-width="11" />
                        @foreach($dSegs as $seg)
                            @continue(($seg['v'] ?? 0) <= 0)
                            @php
                                $segLen = $dC * $seg['v'] / $dTotal;
                                $segDeg = $seg['v'] / $dTotal * 360;
                                static $accDeg = 0;
                            @endphp
                            <circle data-cc-donut-seg="{{ $segLen }}" data-cc-donut-c="{{ $dC }}"
                                    cx="50" cy="50" r="{{ $dR }}" fill="none"
                                    stroke="{{ $seg['color'] }}" stroke-width="11"
                                    stroke-dasharray="{{ $segLen }} {{ $dC - $segLen }}" stroke-dashoffset="0"
                                    transform="rotate({{ $dAccDeg }} 50 50)">
                                <title>{{ $seg['label'] }}: {{ $seg['v'] }} pesanan ({{ round($seg['v'] / $dTotal * 100) }}%)</title>
                            </circle>
                            @php $dAccDeg += $segDeg; @endphp
                        @endforeach
                    </svg>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <div class="text-lg font-bold leading-none text-slate-900">
                            <span data-cc-count="{{ $stats['total_orders'] ?? 0 }}">{{ number_format($stats['total_orders'] ?? 0) }}</span>
                        </div>
                        <div class="mt-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">Total</div>
                    </div>
                </div>

                <ul class="w-full space-y-2 text-xs">
                    @foreach($dSegs as $seg)
                        @continue(($seg['v'] ?? 0) <= 0)
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-slate-600">
                                <span class="h-2 w-2 shrink-0 rounded-sm" style="background: {{ $seg['color'] }}"></span>
                                {{ $seg['label'] }}
                            </span>
                            <span class="font-semibold text-slate-900">{{ number_format($seg['v']) }} <span class="font-normal text-slate-400">({{ round($seg['v'] / $dTotal * 100) }}%)</span></span>
                        </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
        </div>
    </div>

    {{-- Pesanan Terbaru & Kurir Aktif --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Tabel Pesanan Terbaru (2 Kolom) --}}
        <div class="lg:col-span-2 card overflow-hidden">
            <div class="card-header">
                <div>
                    <div class="card-title">Pesanan Masuk Terbaru</div>
                    <div class="card-subtitle">Daftar transaksi instan yang baru dibuat pelanggan</div>
                </div>
                <a href="{{ route('admin.orders') }}" class="btn btn-sm btn-secondary">
                    Semua Pesanan
                </a>
            </div>

            @if(isset($recentOrders) && count($recentOrders) > 0)
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor Resi / ID</th>
                            <th>Pengirim &amp; Penerima</th>
                            <th>Status</th>
                            <th>Tarif</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentOrders as $order)
                        @php
                            $status = strtolower($order->status ?? 'pending');
                            $badgeClass = match($status) {
                                'pending'              => 'badge-amber',
                                'assigned', 'matching' => 'badge-blue',
                                'picked_up', 'in_transit' => 'badge-indigo',
                                'completed', 'delivered' => 'badge-green',
                                'cancelled', 'rejected'  => 'badge-red',
                                default                => 'badge-gray',
                            };
                            $statusLabel = match($status) {
                                'pending'    => 'Menunggu Kurir',
                                'assigned'   => 'Kurir Ditugaskan',
                                'matching'   => 'Mencari Kurir',
                                'picked_up'  => 'Barang Diambil',
                                'in_transit' => 'Dalam Perjalanan',
                                'completed'  => 'Selesai',
                                'delivered'  => 'Terkirim',
                                'cancelled'  => 'Dibatalkan',
                                'rejected'   => 'Ditolak',
                                default      => ucfirst($status),
                            };
                            $resi = $order->tracking_number ?? $order->order_number ?? ('ORD#' . $order->id);
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('admin.orders.detail', $order->id) }}"
                                   class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                    {{ $resi }}
                                </a>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    {{ $order->created_at ? $order->created_at->diffForHumans() : '-' }}
                                </div>
                            </td>
                            <td class="max-w-[180px]">
                                <div class="truncate text-xs font-medium text-slate-800">
                                    {{ $order->sender_name ?? $order->customer_name ?? '-' }}
                                </div>
                                <div class="truncate text-[11px] text-slate-400">
                                    &rarr; {{ $order->receiver_name ?? '-' }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap">
                                <span class="badge {{ $badgeClass }}">
                                    <span class="badge-dot"></span>
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-900">
                                Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('admin.orders.detail', $order->id) }}"
                                   class="btn btn-sm btn-secondary">
                                    Detail
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="empty-state py-10">
                <span class="material-symbols-outlined">inventory_2</span>
                <div class="empty-title">Belum ada pesanan terbaru</div>
                <div class="empty-text">Pesanan yang baru masuk akan langsung tampil di sini.</div>
            </div>
            @endif
        </div>

        {{-- Kurir Siaga (1 Kolom) --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Armada Siaga</div>
                    <div class="card-subtitle">
                        {{ number_format($stats['active_couriers'] ?? 0) }} aktif
                        dari {{ number_format($stats['total_couriers'] ?? 0) }} kurir terdaftar
                    </div>
                </div>
                @canPerm('couriers.view')
                <a href="{{ route('admin.couriers') }}" class="btn btn-sm btn-ghost">
                    Semua
                </a>
                @endcanPerm
            </div>
            <div class="card-body p-0">
                @if(isset($activeCouriers) && count($activeCouriers) > 0)
                <div class="divide-y divide-slate-100">
                    @foreach($activeCouriers as $c)
                    <div class="flex items-center justify-between gap-3 p-3.5 transition-colors hover:bg-slate-50">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div class="avatar bg-emerald-50 text-emerald-700">
                                {{ strtoupper(substr($c->name ?? 'K', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-semibold text-slate-900">{{ $c->name }}</div>
                                <div class="truncate font-mono text-[11px] text-slate-400">
                                    {{ $c->vehicle_plate ?? 'Motor' }} &bull; {{ $c->phone ?? '-' }}
                                </div>
                            </div>
                        </div>
                        <span class="badge badge-green shrink-0 text-[11px]">
                            <span class="badge-dot"></span>
                            Siaga
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="empty-state py-8">
                    <span class="material-symbols-outlined">two_wheeler</span>
                    <div class="empty-title">Tidak ada kurir siaga</div>
                    <div class="empty-text">Kurir yang aktif di aplikasi akan terdaftar di sini.</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Status Koneksi DANA (metode pencairan aktif) --}}
    <div class="card">
        <div class="card-header">
            <div class="flex items-center gap-2.5">
                <div class="stat-icon h-9 w-9 bg-sky-50 text-sky-700">
                    <span class="material-symbols-outlined">account_balance_wallet</span>
                </div>
                <div>
                    <div class="card-title">Koneksi DANA (Pencairan Kurir)</div>
                    <div class="card-subtitle">Status mode, akun kurir terhubung, dan uji binding DANA</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge {{ ($danaStatus['mode'] ?? 'mock') === 'production' ? 'badge-green' : (($danaStatus['mode'] ?? 'mock') === 'sandbox' ? 'badge-indigo' : 'badge-amber') }}">
                    {{ strtoupper($danaStatus['mode'] ?? 'mock') }}
                </span>
                <span class="badge {{ ($danaStatus['provider'] ?? 'mock') === 'official' ? 'badge-green' : 'badge-gray' }}">
                    {{ ($danaStatus['provider'] ?? 'mock') === 'official' ? 'Official API' : 'Mock Provider' }}
                </span>
            </div>
        </div>

        <div class="card-body">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Akun DANA Terhubung</div>
                    <div class="mt-1 text-xl font-bold text-slate-900">{{ $danaStatus['connected_count'] ?? 0 }}</div>
                    <div class="text-[11px] text-slate-400">dari {{ $danaStatus['total_couriers'] ?? 0 }} kurir terdaftar</div>
                </div>
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Menunggu Otorisasi</div>
                    <div class="mt-1 text-xl font-bold {{ ($danaStatus['pending_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' }}">
                        {{ $danaStatus['pending_count'] ?? 0 }}
                    </div>
                    <div class="text-[11px] text-slate-400">sesi binding aktif (maks. 10 menit)</div>
                </div>
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Kredensial</div>
                    <div class="mt-1 text-sm font-bold {{ ($danaStatus['credentials_complete'] ?? false) ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ ($danaStatus['credentials_complete'] ?? false) ? 'Lengkap' : 'Belum lengkap' }}
                    </div>
                    <div class="truncate text-[11px] text-slate-400" title="{{ $danaStatus['callback_url'] ?? '' }}">
                        {{ $danaStatus['callback_url'] ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                <button type="button" id="btn-dashboard-test-dana" class="btn btn-sm btn-secondary">
                    <span class="material-symbols-outlined text-[16px]">sync_alt</span>
                    Tes Binding DANA
                </button>
                <a href="{{ route('admin.settings.dana') }}" class="text-xs font-medium text-sky-700 hover:underline">
                    Konfigurasi DANA &rarr;
                </a>
            </div>

            <div id="dashboard-dana-test-result" class="hidden mt-3 rounded-lg p-3 text-xs"></div>

            @if(($danaStatus['latest'] ?? collect())->count() > 0)
            <div class="mt-4 divide-y divide-slate-100 border-t border-slate-100">
                @foreach($danaStatus['latest'] as $conn)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold text-slate-900">
                            {{ $conn->courier?->user?->name ?? $conn->courier?->phone ?? 'Kurir' }}
                        </div>
                        <div class="truncate font-mono text-[11px] text-slate-400">
                            {{ $conn->masked_phone ?? '-' }}
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <span class="badge badge-green text-[11px]"
                              title="{{ $conn->provider_reference ?? '' }}">Terhubung</span>
                        <div class="mt-1 text-[11px] text-slate-400">
                            {{ $conn->linked_at ? $conn->linked_at->diffForHumans() : '-' }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="mt-4 rounded-lg border border-dashed border-slate-200 p-4 text-center">
                <div class="text-xs font-semibold text-slate-700">Belum ada kurir yang menghubungkan akun DANA</div>
                <div class="mt-1 text-[11px] text-slate-400">Nomor DANA yang dihubungkan dari aplikasi kurir akan tampil di sini.</div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
document.getElementById('btn-dashboard-test-dana')?.addEventListener('click', function () {
    const btn = this;
    const box = document.getElementById('dashboard-dana-test-result');
    btn.disabled = true;
    btn.classList.add('opacity-50');

    fetch('{{ route('admin.settings.dana.test') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        box.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700');
        if (data.success) {
            box.classList.add('bg-emerald-50', 'text-emerald-700');
            box.innerHTML = '<strong>' + data.message + '</strong>';
        } else {
            box.classList.add('bg-red-50', 'text-red-700');
            box.innerHTML = '<strong>' + (data.message || 'Koneksi gagal.') + '</strong>';
        }
    })
    .catch(() => {
        box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-700');
        box.classList.add('bg-red-50', 'text-red-700');
        box.innerHTML = '<strong>Koneksi gagal.</strong> Periksa koneksi server.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
    });
});
</script>
@endsection
