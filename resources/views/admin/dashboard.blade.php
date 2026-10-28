@extends('layouts.admin')

@section('title', 'Dashboard Operasional')

@section('content')
<div class="space-y-6">

    <!-- Page Header Context -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="font-display text-2xl font-bold text-slate-900 tracking-tight">Dashboard Operasional</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Sistem Aktif
                </span>
            </div>
            <p class="text-slate-500 text-sm mt-1">
                Ringkasan logistik instan, performa armada kurir, dan pesanan real-time City Courier.
            </p>
        </div>

        <!-- Action Shortcuts -->
        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('admin.shipments.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px] text-slate-500">local_shipping</span>
                <span>Live Pengiriman</span>
            </a>
            @canPerm('couriers.view')
            <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                <span>Verifikasi Kurir</span>
                @if(($stats['unverified_couriers'] ?? 0) > 0)
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white text-brand-700 font-bold ml-0.5">
                        {{ $stats['unverified_couriers'] }}
                    </span>
                @endif
            </a>
            @endcanPerm
        </div>
    </div>

    <!-- 4 High-Impact KPI Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- KPI 1: Total Pesanan -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono">Total Pesanan</span>
                    <h3 class="font-display text-2xl font-extrabold text-slate-900 mt-1">
                        {{ number_format($stats['total_orders'] ?? 0) }}
                    </h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-brand-600 flex items-center justify-center shrink-0 border border-orange-100">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="flex items-center gap-1.5 text-amber-600 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    {{ $stats['pending_orders'] ?? 0 }} Pending
                </span>
                <span class="flex items-center gap-1.5 text-emerald-600 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    {{ $stats['completed_orders'] ?? 0 }} Selesai
                </span>
            </div>
        </div>

        <!-- KPI 2: Total Pendapatan -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono">Total Omset Selesai</span>
                    <h3 class="font-display text-2xl font-extrabold text-slate-900 mt-1">
                        Rp {{ number_format($stats['total_revenue'] ?? 0, 0, ',', '.') }}
                    </h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Dari {{ $stats['completed_orders'] ?? 0 }} pengiriman terkirim</span>
                <span class="font-semibold text-emerald-600">Sukses</span>
            </div>
        </div>

        <!-- KPI 3: Armada Kurir Aktif -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono">Kurir Online</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <h3 class="font-display text-2xl font-extrabold text-slate-900">
                            {{ $stats['active_couriers'] ?? 0 }}
                        </h3>
                        <span class="text-xs text-slate-400 font-medium">/ {{ $stats['verified_couriers'] ?? 0 }} terverifikasi</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                    <span class="material-symbols-outlined text-[22px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5 text-slate-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Armada Siap Terima Order
                </span>
                <a href="{{ route('admin.couriers') }}" class="text-brand-600 hover:text-brand-700 font-semibold">Kelola &rarr;</a>
            </div>
        </div>

        <!-- KPI 4: Pengiriman Berjalan -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:border-slate-300 transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 font-mono">Dalam Pengiriman</span>
                    <h3 class="font-display text-2xl font-extrabold text-brand-600 mt-1">
                        {{ $stats['delivering_orders'] ?? 0 }}
                    </h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center shrink-0 border border-brand-100">
                    <span class="material-symbols-outlined text-[22px]">near_me</span>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Rute pickup &amp; antar langsung</span>
                <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}" class="text-brand-600 hover:text-brand-700 font-semibold">Pantau &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Main Grid: Orders Table (8 cols) & Active Couriers (4 cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left Column: Pesanan Terbaru (8 cols) -->
        <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
            <!-- Table Header -->
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-base font-bold text-slate-900">Pesanan Terkini</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Daftar transaksi pengiriman terbaru dan status penugasan kurir.</p>
                </div>
                <a href="{{ route('admin.orders') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors self-start sm:self-auto">
                    <span>Lihat Semua Pesanan</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>

            <!-- Table Body -->
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 font-mono">
                            <th class="py-3 px-4">No. Resi</th>
                            <th class="py-3 px-4">Pelanggan</th>
                            <th class="py-3 px-4">Kurir</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Tarif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <!-- Resi -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <a href="{{ route('admin.orders.detail', $order->id) }}" class="font-mono text-xs font-bold text-brand-600 hover:underline">
                                        {{ $order->tracking_number ?? $order->order_number }}
                                    </a>
                                </div>
                                <span class="text-[11px] text-slate-400 block mt-0.5 font-mono">
                                    {{ $order->created_at ? $order->created_at->format('d M, H:i') : '-' }}
                                </span>
                            </td>

                            <!-- Pelanggan -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($order->customer_name ?? 'C', 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-slate-900 block text-xs">{{ $order->customer_name ?? 'Pelanggan' }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $order->customer_phone ?? '-' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Kurir -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if($order->courier && $order->courier->user)
                                <div class="flex items-center gap-1.5 text-xs text-slate-700">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">two_wheeler</span>
                                    <span class="font-medium">{{ $order->courier->user->name }}</span>
                                </div>
                                @else
                                <span class="text-xs text-slate-400 italic">Belum ditugaskan</span>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @php
                                    $statusConfig = [
                                        'pending'    => ['label' => 'Pending', 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                                        'assigned'   => ['label' => 'Ditugaskan', 'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                                        'picking_up' => ['label' => 'Penjemputan', 'bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                                        'delivering' => ['label' => 'Diantar', 'bg' => 'bg-orange-50 text-brand-700 border-orange-200', 'dot' => 'bg-brand-500'],
                                        'delivered'  => ['label' => 'Selesai', 'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                                        'cancelled'  => ['label' => 'Batal', 'bg' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500'],
                                    ];
                                    $cfg = $statusConfig[$order->status] ?? ['label' => ucfirst($order->status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'];
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $cfg['bg'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }}"></span>
                                    {{ $cfg['label'] }}
                                </span>
                            </td>

                            <!-- Tarif -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-900 text-xs">
                                    Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">inbox</span>
                                <p class="text-xs">Belum ada pesanan terbaru.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="p-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span>Menampilkan <strong>{{ count($recentOrders) }}</strong> transaksi terbaru</span>
                <a href="{{ route('admin.orders') }}" class="text-brand-600 font-semibold hover:underline">Lihat Semua Data &rarr;</a>
            </div>
        </div>

        <!-- Right Column: Kurir Lapangan Online (4 cols) -->
        <div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <!-- Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-base font-bold text-slate-900">Armada Kurir Aktif</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kurir siap menerima pesanan instan.</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60 font-mono">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ $stats['active_couriers'] ?? 0 }} Online
                    </span>
                </div>

                <!-- Kurir List -->
                <div class="p-4 space-y-3">
                    @forelse ($activeCouriers as $courier)
                    <div class="p-3.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-slate-50 transition-colors flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="relative shrink-0">
                                <div class="w-10 h-10 rounded-xl bg-brand-600 text-white font-bold text-sm flex items-center justify-center shadow-sm">
                                    {{ strtoupper(substr($courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                            </div>
                            <div class="truncate">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-slate-900 text-xs truncate block">
                                        {{ $courier->user->name ?? 'Kurir' }}
                                    </span>
                                    @if($courier->is_verified)
                                    <span class="material-symbols-outlined text-[14px] text-brand-600" title="Terverifikasi">verified</span>
                                    @endif
                                </div>
                                <span class="text-[11px] text-slate-400 font-mono block">
                                    {{ $courier->phone ?? $courier->user->phone ?? '-' }}
                                </span>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-slate-200 text-slate-700 font-mono">
                                {{ $courier->vehicle_type ?? 'Motor' }}
                            </span>
                            <span class="text-[11px] text-emerald-600 font-semibold block mt-0.5">Aktif</span>
                        </div>
                    </div>
                    @empty
                    <div class="py-8 text-center text-slate-400">
                        <span class="material-symbols-outlined text-2xl mb-1 text-slate-300">person_off</span>
                        <p class="text-xs">Tidak ada kurir yang sedang online saat ini.</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Footer Action -->
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                <a href="{{ route('admin.couriers') }}" class="w-full py-2.5 px-3 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-semibold text-xs border border-slate-200 shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    <span>Kelola Seluruh Armada Kurir</span>
                </a>
            </div>
        </div>

    </div>

</div>
@endsection
