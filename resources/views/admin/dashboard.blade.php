@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan data dan aktivitas CityCourier')

@section('content')
<div class="flex flex-col w-full">
    <!-- Top Intro & Quick Stats Bar (Bento style grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Metric Card 1 - Total Pesanan -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 relative overflow-hidden group hover:shadow-lg transition-all shadow-sm">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-primary-container/10 rounded-full blur-xl group-hover:bg-primary-container/20 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs text-outline uppercase tracking-wider">Total Pesanan</span>
                    <h3 class="text-2xl font-semibold text-on-surface mt-1">{{ $totalOrders ?? 128 }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-container flex items-center justify-center text-on-primary-container">
                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">orders</span>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-primary">
                <span class="material-symbols-outlined text-[16px]">trending_up</span>
                <span>+12.4% dari kemarin</span>
            </div>
        </div>

        <!-- Metric Card 2 - Pendapatan -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 relative overflow-hidden group hover:shadow-lg transition-all shadow-sm">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-tertiary-container/10 rounded-full blur-xl group-hover:bg-tertiary-container/20 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs text-outline uppercase tracking-wider">Pendapatan</span>
                    <h3 class="text-2xl font-semibold text-on-surface mt-1">Rp {{ number_format($revenue ?? 1250000, 0, ',', '.') }}</h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-tertiary-container flex items-center justify-center text-on-tertiary-container">
                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">payments</span>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-primary">
                <span class="material-symbols-outlined text-[16px]">trending_up</span>
                <span>+8.1% target harian</span>
            </div>
        </div>

        <!-- Metric Card 3 - Kurir Aktif -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 relative overflow-hidden group hover:shadow-lg transition-all shadow-sm">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-secondary-container/20 rounded-full blur-xl group-hover:bg-secondary-container/40 transition-all"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs text-outline uppercase tracking-wider">Kurir Aktif</span>
                    <h3 class="text-2xl font-semibold text-on-surface mt-1">{{ $activeCouriers ?? 24 }} <span class="text-sm font-normal text-outline">/ {{ $totalCouriers ?? 42 }} Total</span></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">group</span>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-on-secondary-container">
                <span class="w-2 h-2 rounded-full bg-primary-container animate-pulse"></span>
                <span>{{ $pendingVerification ?? 3 }} Menunggu Verifikasi</span>
            </div>
        </div>

        <!-- Metric Card 4 - Status Pengiriman -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 relative overflow-hidden group hover:shadow-lg transition-all shadow-sm">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-primary-container/10 rounded-full blur-xl"></div>
            <div class="flex justify-between items-start mb-4">
                <div>
                    <span class="text-xs text-outline uppercase tracking-wider">Status Pengiriman</span>
                    <h3 class="text-2xl font-semibold text-on-surface mt-1">{{ $delivering ?? 37 }} <span class="text-sm font-normal text-outline">Diantar</span></h3>
                </div>
                <div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center text-primary-container">
                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm text-outline">
                <span>Pending: <strong class="text-on-surface">{{ $pendingOrders ?? 8 }}</strong></span>
                <span>Selesai: <strong class="text-on-surface">{{ $completedOrders ?? 59 }}</strong></span>
            </div>
        </div>
    </div>

    <!-- Main Section: Charts and Active Couriers Split -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">
        <!-- Interactive Charts (Span 2) -->
        <div class="lg:col-span-2 bg-surface-container-lowest border border-surface-variant rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
                <div>
                    <h4 class="text-xl font-semibold text-on-surface">Analisis Tren & Pendapatan</h4>
                    <p class="text-sm text-outline">Performa pengiriman dan omset real-time</p>
                </div>
                <div class="flex items-center bg-surface-container p-1 rounded-xl gap-1">
                    <button class="px-3 py-1.5 rounded-lg text-sm bg-primary-container text-on-primary-container font-medium transition-all">Hari ini</button>
                    <button class="px-3 py-1.5 rounded-lg text-sm text-on-surface-variant hover:text-on-surface transition-all">7 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-sm text-on-surface-variant hover:text-on-surface transition-all">30 Hari</button>
                    <button class="px-3 py-1.5 rounded-lg text-sm text-on-surface-variant hover:text-on-surface transition-all">Bulan ini</button>
                </div>
            </div>
            <!-- Chart Visualization Mock using CSS Grid / Bars -->
            <div class="h-64 w-full flex items-end justify-between gap-3 pt-6 px-4 pb-2 relative">
                <!-- Background grid lines -->
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-20">
                    <div class="w-full h-[1px] bg-surface-variant"></div>
                    <div class="w-full h-[1px] bg-surface-variant"></div>
                    <div class="w-full h-[1px] bg-surface-variant"></div>
                    <div class="w-full h-[1px] bg-surface-variant"></div>
                </div>
                <!-- Bars -->
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-32 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">12 Pesanan</span>
                        <div class="w-full bg-primary-container h-1/2 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Sen</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-44 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">18 Pesanan</span>
                        <div class="w-full bg-primary-container h-3/4 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Sel</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-36 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">15 Pesanan</span>
                        <div class="w-full bg-primary-container h-2/3 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Rab</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-52 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">24 Pesanan</span>
                        <div class="w-full bg-primary-container h-5/6 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Kam</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-40 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">17 Pesanan</span>
                        <div class="w-full bg-primary-container h-3/5 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Jum</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-60 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">32 Pesanan</span>
                        <div class="w-full bg-primary-container h-full rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Sab</span>
                </div>
                <div class="flex-1 flex flex-col items-center gap-2 group relative">
                    <div class="w-full bg-surface-container rounded-t-lg h-48 group-hover:bg-primary-container/20 transition-all relative flex items-end justify-center pb-2">
                        <span class="absolute -top-8 bg-surface-container-highest text-on-surface text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap shadow-sm">20 Pesanan</span>
                        <div class="w-full bg-primary-container h-4/5 rounded-t-lg"></div>
                    </div>
                    <span class="text-xs text-outline">Min</span>
                </div>
            </div>
            <div class="flex items-center justify-between pt-4 mt-2 border-t border-surface-variant">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-primary-container"></span>
                        <span class="text-sm text-on-surface-variant">Jumlah Pesanan</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-tertiary-container"></span>
                        <span class="text-sm text-on-surface-variant">Pendapatan (x100rb)</span>
                    </div>
                </div>
                <span class="text-xs text-outline">Update Terakhir: Baru saja</span>
            </div>
        </div>

        <!-- Active Couriers Status List -->
        <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-xl font-semibold text-on-surface">Status Kurir</h4>
                    <span class="text-xs bg-surface-container px-2 py-1 rounded-full text-primary-container font-medium">{{ $activeCouriers ?? 24 }} Online</span>
                </div>
                <p class="text-sm text-outline mb-5">Pemantauan armada kurir aktif secara langsung.</p>
                <!-- Courier list items -->
                <div class="space-y-4">
                    @foreach($couriers ?? [] as $courier)
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-all border border-surface-variant/50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface font-semibold overflow-hidden">
                                    {{ strtoupper(substr($courier->name ?? 'A', 0, 1)) }}
                                </div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-surface-container-lowest" style="background: {{ ($courier->is_online ?? false) ? '#22c55e' : '#907065' }}"></span>
                            </div>
                            <div>
                                <h5 class="text-sm font-medium text-on-surface">{{ $courier->name ?? 'N/A' }}</h5>
                                <span class="text-xs text-outline">{{ $courier->location ?? 'N/A' }} • {{ $courier->deliveries ?? 0 }} Pengiriman</span>
                            </div>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full font-medium" style="background: {{ ($courier->is_online ?? false) ? 'rgba(34, 197, 94, 0.1)' : 'bg-surface-container' }}; color: {{ ($courier->is_online ?? false) ? '#16a34a' : '#907065' }}">
                            {{ ($courier->is_online ?? false) ? 'Aktif' : 'Offline' }}
                        </span>
                    </div>
                    @endforeach

                    @if(!isset($couriers) || empty($couriers))
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-all border border-surface-variant/50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface font-semibold">P</div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-green-500 border-2 border-surface-container-lowest"></span>
                            </div>
                            <div>
                                <h5 class="text-sm font-medium text-on-surface">Princenton</h5>
                                <span class="text-xs text-outline">Kec. Lowokwaru • 3 Pengiriman</span>
                            </div>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-green-500/10 text-green-600 font-medium">Aktif</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-all border border-surface-variant/50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface font-semibold">B</div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-green-500 border-2 border-surface-container-lowest"></span>
                            </div>
                            <div>
                                <h5 class="text-sm font-medium text-on-surface">Budi</h5>
                                <span class="text-xs text-outline">Kec. Blimbing • 5 Pengiriman</span>
                            </div>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-green-500/10 text-green-600 font-medium">Aktif</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-all border border-surface-variant/50">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface font-semibold">A</div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-outline border-2 border-surface-container-lowest"></span>
                            </div>
                            <div>
                                <h5 class="text-sm font-medium text-on-surface">Andi</h5>
                                <span class="text-xs text-outline">Kec. Klojen • Selesai tugas</span>
                            </div>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-surface-container text-outline">Offline</span>
                    </div>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.couriers') }}" class="w-full mt-5 py-2.5 rounded-xl bg-surface-container text-on-surface hover:bg-surface-container-high transition-all text-sm font-medium flex items-center justify-center gap-2 border border-surface-variant">
                <span>Lihat Semua Kurir ({{ $totalCouriers ?? 42 }})</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </div>

    <!-- Recent Orders Table Section -->
    <div class="bg-surface-container-lowest border border-surface-variant rounded-xl p-5 shadow-sm">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
            <div>
                <h4 class="text-xl font-semibold text-on-surface">Pesanan Terbaru</h4>
                <p class="text-sm text-outline">Daftar transaksi dan pengiriman ekspres yang masuk ke sistem</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-outline text-[18px]">filter_list</span>
                    <select class="bg-surface-container border border-surface-variant text-on-surface text-sm pl-9 pr-8 py-2 rounded-xl focus:outline-none focus:ring-1 focus:ring-primary-container appearance-none cursor-pointer">
                        <option>Semua Status</option>
                        <option>Sedang Diantar</option>
                        <option>Pending</option>
                        <option>Selesai</option>
                    </select>
                </div>
                <button class="bg-primary-container text-on-primary-container px-4 py-2 rounded-xl text-sm font-medium hover:opacity-90 transition-all flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    <span>Buat Pesanan</span>
                </button>
            </div>
        </div>
        <!-- Table Container -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-surface-variant text-xs text-outline uppercase tracking-wider">
                        <th class="py-3 px-4">ID Pesanan</th>
                        <th class="py-3 px-4">Pelanggan</th>
                        <th class="py-3 px-4">Kurir Ditugaskan</th>
                        <th class="py-3 px-4">Tujuan</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-variant/50 text-sm text-on-surface">
                    @foreach($orders ?? [] as $order)
                    <tr class="hover:bg-surface-container-low transition-all">
                        <td class="py-3.5 px-4 font-mono text-primary-container font-semibold">#{{ $order->tracking_number ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4 font-medium">{{ $order->customer_name ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4">{{ $order->courier_name ?? 'Belum ditugaskan' }}</td>
                        <td class="py-3.5 px-4 text-outline truncate max-w-xs">{{ $order->destination_address ?? 'N/A' }}</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-primary-container/10 text-primary-container font-medium">{{ $order->status_label ?? 'Pending' }}</span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.orders.detail', $order->id) ?? '#' }}" class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                @if(($order->status ?? '') === 'pending')
                                <button class="p-1.5 rounded-lg bg-primary-container text-on-primary-container hover:opacity-90 transition-all shadow-sm" title="Tugaskan Kurir">
                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                </button>
                                @else
                                <a href="#" class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Lacak">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    @if(!isset($orders) || empty($orders))
                    <tr class="hover:bg-surface-container-low transition-all">
                        <td class="py-3.5 px-4 font-mono text-primary-container font-semibold">#CC-9821</td>
                        <td class="py-3.5 px-4 font-medium">Siti Rahma</td>
                        <td class="py-3.5 px-4">Princenton</td>
                        <td class="py-3.5 px-4 text-outline truncate max-w-xs">Jl. Soekarno Hatta No. 12, Malang</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-primary-container/10 text-primary-container font-medium">Sedang Diantar</span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Lacak">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-surface-container-low transition-all">
                        <td class="py-3.5 px-4 font-mono text-primary-container font-semibold">#CC-9820</td>
                        <td class="py-3.5 px-4 font-medium">Ahmad Fauzi</td>
                        <td class="py-3.5 px-4">Budi</td>
                        <td class="py-3.5 px-4 text-outline truncate max-w-xs">Jl. Ijen Boulevard 45, Malang</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-green-500/10 text-green-600 font-medium">Selesai</span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Nota">
                                    <span class="material-symbols-outlined text-[18px]">receipt</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-surface-container-low transition-all">
                        <td class="py-3.5 px-4 font-mono text-primary-container font-semibold">#CC-9819</td>
                        <td class="py-3.5 px-4 font-medium">Dewi Lestari</td>
                        <td class="py-3.5 px-4 text-outline italic">Belum ditugaskan</td>
                        <td class="py-3.5 px-4 text-outline truncate max-w-xs">Jl. Borobudur No. 8, Malang</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-amber-500/10 text-amber-600 font-medium">Pending</span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="p-1.5 rounded-lg bg-primary-container text-on-primary-container hover:opacity-90 transition-all shadow-sm" title="Tugaskan Kurir">
                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr class="hover:bg-surface-container-low transition-all">
                        <td class="py-3.5 px-4 font-mono text-primary-container font-semibold">#CC-9818</td>
                        <td class="py-3.5 px-4 font-medium">Reza Pratama</td>
                        <td class="py-3.5 px-4">Princenton</td>
                        <td class="py-3.5 px-4 text-outline truncate max-w-xs">Jl. Dieng Atas No. 99, Malang</td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs bg-primary-container/10 text-primary-container font-medium">Sedang Diantar</span>
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                                <button class="p-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface-variant hover:text-on-surface transition-all border border-surface-variant/40" title="Lacak">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <!-- Table Footer / Pagination -->
        <div class="flex items-center justify-between pt-4 mt-4 border-t border-surface-variant text-sm text-outline">
            <span>Menampilkan {{ count($orders ?? []) > 0 ? '1-' . count($orders ?? []) : '1-4' }} dari {{ $totalOrders ?? 128 }} pesanan</span>
            <div class="flex items-center gap-2">
                <button class="px-3 py-1 rounded-lg bg-surface-container border border-surface-variant text-on-surface disabled:opacity-40" disabled>Sebelumnya</button>
                <button class="px-3 py-1 rounded-lg bg-primary-container text-on-primary-container font-medium shadow-sm">1</button>
                <button class="px-3 py-1 rounded-lg bg-surface-container border border-surface-variant text-on-surface hover:bg-surface-container-high">2</button>
                <button class="px-3 py-1 rounded-lg bg-surface-container border border-surface-variant text-on-surface hover:bg-surface-container-high">3</button>
                <button class="px-3 py-1 rounded-lg bg-surface-container border border-surface-variant text-on-surface hover:bg-surface-container-high">Selanjutnya</button>
            </div>
        </div>
    </div>
</div>
@endsection
