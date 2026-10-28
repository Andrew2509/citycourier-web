@extends('layouts.admin')

@section('title', 'Dashboard Operasional')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Dashboard Operasional</h1>
                <span class="badge badge-green">
                    <span class="badge-dot"></span>
                    Sistem Aktif
                </span>
            </div>
            <p class="page-subtitle">
                Ringkasan logistik instan, performa armada kurir, dan pesanan real-time City Courier.
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Pesanan --}}
        <div class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Total Pesanan</div>
                    <div class="stat-value">
                        {{ number_format($stats['total_orders'] ?? 0) }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-brand-50 text-brand-600">
                    <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-1.5 text-amber-700 font-medium">
                    <span class="badge-dot bg-amber-500"></span>
                    {{ $stats['pending_orders'] ?? 0 }} menunggu
                </span>
                <span class="text-slate-400">Hari ini: {{ $stats['today_orders'] ?? 0 }}</span>
            </div>
        </div>

        {{-- Card 2: Pengiriman Berjalan --}}
        <div class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Sedang Dikirim</div>
                    <div class="stat-value text-blue-700">
                        {{ number_format($stats['active_shipments'] ?? 0) }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-600">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="text-slate-600 font-medium">Dalam perjalanan</span>
                <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}" class="text-brand-700 hover:underline">
                    Lihat peta &rarr;
                </a>
            </div>
        </div>

        {{-- Card 3: Kurir Aktif --}}
        <div class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Armada Kurir Aktif</div>
                    <div class="stat-value text-emerald-700">
                        {{ number_format($stats['active_couriers'] ?? 0) }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-600">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Total armada: <strong>{{ $stats['total_couriers'] ?? 0 }}</strong></span>
                @if(($stats['unverified_couriers'] ?? 0) > 0)
                    <span class="text-amber-700 font-semibold">{{ $stats['unverified_couriers'] }} baru</span>
                @else
                    <span class="text-emerald-700">100% terverifikasi</span>
                @endif
            </div>
        </div>

        {{-- Card 4: Pendapatan Hari Ini --}}
        <div class="card p-4">
            <div class="flex items-start justify-between">
                <div>
                    <div class="stat-label">Pendapatan Hari Ini</div>
                    <div class="stat-value text-slate-900">
                        Rp {{ number_format($stats['today_revenue'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>
                <div class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-100 text-slate-700">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Total: Rp {{ number_format(($stats['total_revenue'] ?? 0) / 1000000, 1) }} jt</span>
                <span class="text-emerald-700 font-medium">Bulan berjalan</span>
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

        {{-- Kurir Online & Siaga (1 Kolom) --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Armada Siaga</div>
                    <div class="card-subtitle">Kurir aktif yang siap menerima penugasan</div>
                </div>
                @canPerm('couriers.view')
                <a href="{{ route('admin.couriers') }}" class="btn btn-sm btn-ghost">
                    Semua
                </a>
                @endcanPerm
            </div>
            <div class="card-body p-0">
                @if(isset($onlineCouriers) && count($onlineCouriers) > 0)
                <div class="divide-y divide-slate-100">
                    @foreach($onlineCouriers as $c)
                    <div class="p-3.5 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="avatar bg-emerald-50 text-emerald-700 text-xs font-bold">
                                {{ strtoupper(substr($c->name ?? 'K', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-900 truncate">{{ $c->name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono truncate">
                                    {{ $c->vehicle_plate ?? 'Motor' }} &bull; {{ $c->phone ?? '-' }}
                                </div>
                            </div>
                        </div>
                        <span class="badge badge-green shrink-0 text-[11px]">
                            Online
                        </span>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="empty-state py-8">
                    <span class="material-symbols-outlined">two_wheeler</span>
                    <div class="empty-title">Tidak ada kurir siaga</div>
                    <div class="empty-text">Kurir yang online di aplikasi akan terdaftar di sini.</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
