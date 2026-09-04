@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <!-- Metric Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Pesanan -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 relative overflow-hidden group hover:shadow-lg hover:shadow-primary/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-primary/5 rounded-full blur-xl group-hover:bg-primary/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Total Pesanan</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($stats['total_orders'] ?? 0) }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">shopping_bag</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-success">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                <span>+12.4% dari kemarin</span>
            </div>
        </div>

        <!-- Pendapatan -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 relative overflow-hidden group hover:shadow-lg hover:shadow-success/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-success/5 rounded-full blur-xl group-hover:bg-success/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Pendapatan</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">Rp {{ number_format($stats['total_revenue'] ?? 0, 0, ',', '.') }}</h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-success/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-success text-xl">payments</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-success">
                <span class="material-symbols-outlined text-[14px]">trending_up</span>
                <span>+8.1% target harian</span>
            </div>
        </div>

        <!-- Kurir Aktif -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 relative overflow-hidden group hover:shadow-lg hover:shadow-info/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-info/5 rounded-full blur-xl group-hover:bg-info/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Kurir Aktif</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['active_couriers'] ?? 0 }} <span class="text-sm font-normal text-slate-400">/ {{ $stats['total_couriers'] ?? 0 }}</span></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-xl">group</span>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
                <span>{{ $stats['unverified_couriers'] ?? 0 }} Menunggu Verifikasi</span>
            </div>
        </div>

        <!-- Status Pengiriman -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200 relative overflow-hidden group hover:shadow-lg hover:shadow-warning/5 transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-warning/5 rounded-full blur-xl group-hover:bg-warning/10 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Status Pengiriman</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1">{{ $stats['delivering_orders'] ?? 0 }} <span class="text-sm font-normal text-slate-400">Diantar</span></h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-warning/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-warning text-xl">local_shipping</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Pending: <strong class="text-slate-700">{{ $stats['pending_orders'] ?? 0 }}</strong></span>
                <span>Selesai: <strong class="text-slate-700">{{ $stats['completed_orders'] ?? 0 }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Charts and Couriers -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Charts -->
        <div class="lg:col-span-2 bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
                <div>
                    <h4 class="text-lg font-bold text-slate-800">Analisis Tren & Pendapatan</h4>
                    <p class="text-sm text-slate-400">Performa pengiriman dan omset real-time</p>
                </div>
                <div class="flex items-center bg-slate-100 p-1 rounded-xl gap-1">
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-white shadow-sm">Hari ini</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">7 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">30 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-500 hover:text-slate-700">Bulan ini</button>
                </div>
            </div>
            <!-- Chart -->
            <div class="h-56 w-full flex items-end justify-between gap-2 pt-4 px-2 pb-2 relative">
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-30">
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                    <div class="w-full h-[1px] bg-slate-200"></div>
                </div>
                @foreach(['Sen' => 60, 'Sel' => 85, 'Rab' => 70, 'Kam' => 95, 'Jum' => 75, 'Sab' => 100, 'Min' => 80] as $day => $height)
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-slate-100 rounded-t-lg h-{{ $height === 100 ? 'full' : ($height >= 80 ? '4/5' : ($height >= 60 ? '3/5' : '1/2')) }} group-hover:bg-primary/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">{{ intval($height * 0.32) }} Pesanan</span>
                        <div class="w-full bg-gradient-to-t from-primary to-primary-light rounded-t-lg h-full"></div>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">{{ $day }}</span>
                </div>
                @endforeach
            </div>
            <div class="flex items-center justify-between pt-4 mt-3 border-t border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-gradient-to-r from-primary to-primary-light"></span>
                        <span class="text-xs text-slate-500">Jumlah Pesanan</span>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400">Update: Baru saja</span>
            </div>
        </div>

        <!-- Status Kurir -->
        <div class="bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-lg font-bold text-slate-800">Status Kurir</h4>
                <span class="text-[11px] bg-success/10 text-success px-2.5 py-1 rounded-full font-semibold">{{ $stats['active_couriers'] ?? 0 }} Online</span>
            </div>
            <p class="text-sm text-slate-400 mb-4">Pemantauan armada kurir aktif.</p>
            <div class="space-y-3">
                @forelse($activeCouriers as $courier)
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-all">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm">
                                {{ strtoupper(substr($courier->user->name ?? 'A', 0, 1)) }}
                            </div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white {{ $courier->is_active ? 'bg-success' : 'bg-slate-400' }}"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-semibold text-slate-700">{{ $courier->user->name ?? 'N/A' }}</h5>
                            <span class="text-[11px] text-slate-400">{{ $courier->city ?? '-' }} • {{ $courier->phone ?? '-' }}</span>
                        </div>
                    </div>
                    <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold {{ $courier->is_active ? 'bg-success/10 text-success' : 'bg-slate-100 text-slate-400' }}">
                        {{ $courier->is_active ? 'Aktif' : 'Offline' }}
                    </span>
                </div>
                @empty
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                        <span class="material-symbols-outlined text-slate-400 text-3xl">group_off</span>
                    </div>
                    <p class="text-sm text-slate-500">Belum ada kurir aktif</p>
                </div>
                @endforelse
            </div>
            <a href="{{ route('admin.couriers') }}" class="w-full mt-4 py-2.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all text-sm font-semibold flex items-center justify-center gap-2">
                <span>Lihat Semua Kurir</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>

    <!-- Pesanan Terbaru -->
    <div class="bg-white rounded-2xl p-5 border border-slate-200">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h4 class="text-lg font-bold text-slate-800">Pesanan Terbaru</h4>
                <p class="text-sm text-slate-400">Daftar transaksi terbaru</p>
            </div>
            <div class="flex items-center gap-3">
                <select class="bg-slate-50 border border-slate-200 text-slate-600 text-sm pl-4 pr-8 py-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 cursor-pointer">
                    <option>Semua Status</option>
                    <option>Sedang Diantar</option>
                    <option>Pending</option>
                    <option>Selesai</option>
                </select>
                <button class="bg-gradient-to-r from-primary to-primary-light text-white px-4 py-2 rounded-xl text-sm font-semibold hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Buat Pesanan
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ID Pesanan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pelanggan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kurir</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tujuan</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-4 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentOrders as $order)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-4 font-mono text-sm font-bold text-primary">#{{ $order->tracking_number ?? $order->order_number ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4 font-medium text-sm text-slate-700">{{ $order->customer_name ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4 text-sm text-slate-600">{{ $order->courier->user->name ?? 'Belum ditugaskan' }}</td>
                        <td class="py-3.5 px-4 text-sm text-slate-400 truncate max-w-[200px]">{{ $order->delivery_address ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4">
                            @php
                                $statusColor = match($order->status ?? 'pending') {
                                    'delivered' => 'bg-success/10 text-success',
                                    'delivering', 'in_transit' => 'bg-primary/10 text-primary',
                                    'pending' => 'bg-warning/10 text-warning',
                                    'cancelled' => 'bg-red-100 text-red-600',
                                    default => 'bg-slate-100 text-slate-500'
                                };
                                $statusLabel = match($order->status ?? 'pending') {
                                    'pending' => 'Pending',
                                    'assigned' => 'Ditugaskan',
                                    'picking_up' => 'Dijemput',
                                    'delivering' => 'Sedang Diantar',
                                    'delivered' => 'Selesai',
                                    'cancelled' => 'Dibatalkan',
                                    default => ucfirst($order->status ?? 'Unknown')
                                };
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $statusColor }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.orders.detail', $order->id) }}" class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                <a href="#" class="p-1.5 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all" title="Lacak">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-slate-400 text-3xl">inbox</span>
                                </div>
                                <p class="text-sm text-slate-500">Belum ada pesanan</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between pt-4 mt-4 border-t border-slate-100">
            <span class="text-sm text-slate-400">Menampilkan {{ $recentOrders->count() }} dari {{ $stats['total_orders'] ?? 0 }} pesanan terbaru</span>
            <a href="{{ route('admin.orders') }}" class="text-sm text-primary font-semibold hover:underline">Lihat Semua →</a>
        </div>
    </div>
</div>
@endsection
