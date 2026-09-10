@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Page Header Context & Global Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col">
            <div class="flex items-center gap-space-xs">
                <span class="font-headline-xl text-headline-xl text-on-surface">Dashboard Utama</span>
                <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-primary-fixed text-on-primary-fixed-variant font-bold">Live Ops</span>
            </div>
            <p class="font-body-md text-body-md text-secondary mt-space-2xs">
                Ringkasan performa operasional logistik, status kurir lapangan, dan transaksi real-time.
            </p>
        </div>
        <!-- Quick Action / Date Filter Control -->
        <div class="flex items-center gap-space-sm self-start md:self-auto flex-wrap">
            <div class="inline-flex p-space-2xs bg-surface-container-low rounded-lg shadow-sm">
                <button class="px-space-md py-space-xs font-label-md text-label-md rounded-lg bg-surface-container-lowest text-primary font-bold shadow-sm transition-all" type="button">Hari Ini</button>
                <button class="px-space-md py-space-xs font-label-md text-label-md rounded-lg text-secondary hover:text-on-surface transition-all" type="button">7 Hari</button>
                <button class="px-space-md py-space-xs font-label-md text-label-md rounded-lg text-secondary hover:text-on-surface transition-all" type="button">30 Hari</button>
                <button class="px-space-md py-space-xs font-label-md text-label-md rounded-lg text-secondary hover:text-on-surface transition-all flex items-center gap-space-2xs" type="button">
                    <span>Kustom</span>
                    <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                </button>
            </div>
            <button class="h-9 px-space-md py-space-xs bg-surface-container-lowest hover:bg-surface-container-high text-on-surface rounded-lg font-label-md text-label-md shadow-sm transition-all flex items-center gap-space-xs" type="button">
                <span class="material-symbols-outlined text-[18px] text-secondary">file_download</span>
                <span>Export Laporan</span>
            </button>
        </div>
    </div>

    <!-- Metric / Stat Cards Grid (4 Units) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <!-- Stat 1: Total Pesanan -->
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Total Pesanan</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ number_format($stats['total_orders']) }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                    <span class="material-symbols-outlined text-[22px]">inventory_2</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-sm flex items-center justify-between">
                <span class="inline-flex items-center gap-space-2xs font-label-sm text-label-sm text-emerald-600 font-bold">
                    <span class="material-symbols-outlined text-[16px]">trending_up</span>
                    <span>+12.4%</span>
                </span>
                <span class="font-label-sm text-label-sm text-secondary">vs kemarin</span>
            </div>
        </div>

        <!-- Stat 2: Pendapatan -->
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Total Pendapatan</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-sm flex items-center justify-between">
                <span class="inline-flex items-center gap-space-2xs font-label-sm text-label-sm text-emerald-600 font-bold">
                    <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                    <span>+8.1%</span>
                </span>
                <span class="font-label-sm text-label-sm text-secondary">dari target harian</span>
            </div>
        </div>

        <!-- Stat 3: Kurir Aktif -->
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Kurir Aktif Lapangan</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $stats['active_couriers'] }}</span>
                        <span class="font-body-md text-body-md text-secondary">/ {{ $stats['verified_couriers'] }} terdaftar</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed-variant shrink-0">
                    <span class="material-symbols-outlined text-[22px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-sm flex items-center gap-space-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-label-sm text-label-sm text-on-surface font-semibold">{{ $stats['active_couriers'] }} Online</span>
                <span class="text-secondary font-label-sm text-label-sm">•</span>
                <span class="font-label-sm text-label-sm text-secondary">{{ $stats['unverified_couriers'] }} Menunggu Verifikasi</span>
            </div>
        </div>

        <!-- Stat 4: Status Pengiriman -->
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Status Pengiriman</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-display-lg text-display-lg text-primary font-bold">{{ $stats['delivering_orders'] }}</span>
                        <span class="font-body-md text-body-md text-secondary">Diantar</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">local_shipping</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-sm flex items-center justify-between text-secondary">
                <div class="flex items-center gap-space-xs">
                    <span class="font-label-sm text-label-sm">Pending:</span>
                    <span class="font-label-sm text-label-sm font-bold text-on-surface">{{ $stats['pending_orders'] }}</span>
                </div>
                <div class="flex items-center gap-space-xs">
                    <span class="font-label-sm text-label-sm">Selesai:</span>
                    <span class="font-label-sm text-label-sm font-bold text-emerald-600">{{ $stats['completed_orders'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Split Visual Section: Chart & Fleet Status -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg">
        <!-- Left Column: Trend & Analytics (8 cols) -->
        <div class="lg:col-span-8 bg-surface-container-lowest rounded-xl p-space-xl shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm pb-space-md">
                    <div class="flex flex-col">
                        <h3 class="font-headline-lg text-headline-lg text-on-surface">Analisis Tren & Pendapatan</h3>
                        <p class="font-body-sm text-body-sm text-secondary">Performa fluktuasi order dan omset pengiriman real-time per hari</p>
                    </div>
                    <div class="inline-flex p-space-2xs bg-surface-container-low rounded-lg self-start sm:self-auto">
                        <button class="px-space-sm py-space-2xs text-label-sm font-label-sm bg-primary-container text-on-primary font-bold rounded-lg shadow-sm">Hari ini</button>
                        <button class="px-space-sm py-space-2xs text-label-sm font-label-sm text-secondary hover:text-on-surface font-semibold rounded-lg">7 Hari</button>
                        <button class="px-space-sm py-space-2xs text-label-sm font-label-sm text-secondary hover:text-on-surface font-semibold rounded-lg">30 Hari</button>
                        <button class="px-space-sm py-space-2xs text-label-sm font-label-sm text-secondary hover:text-on-surface font-semibold rounded-lg">Bulan ini</button>
                    </div>
                </div>
                <!-- SVG Interactive Chart Visual -->
                <div class="w-full mt-space-lg">
                    <div class="h-56 w-full relative flex items-end">
                        <div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-40">
                            <div class="w-full h-px bg-surface-container-highest"></div>
                            <div class="w-full h-px bg-surface-container-highest"></div>
                            <div class="w-full h-px bg-surface-container-highest"></div>
                            <div class="w-full h-px bg-surface-container-highest"></div>
                        </div>
                        <svg class="w-full h-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 700 200">
                            <defs>
                                <linearGradient id="orderGradient" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#f97316" stop-opacity="0.35"></stop>
                                    <stop offset="100%" stop-color="#f97316" stop-opacity="0.0"></stop>
                                </linearGradient>
                            </defs>
                            <path d="M 0 170 L 100 150 L 200 130 L 300 80 L 400 110 L 500 60 L 600 90 L 700 30 L 700 200 L 0 200 Z" fill="url(#orderGradient)"></path>
                            <path d="M 0 170 L 100 150 L 200 130 L 300 80 L 400 110 L 500 60 L 600 90 L 700 30" fill="none" stroke="#f97316" stroke-linecap="round" stroke-linejoin="round" stroke-width="3"></path>
                            <circle cx="0" cy="170" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="100" cy="150" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="200" cy="130" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="300" cy="80" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="400" cy="110" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="500" cy="60" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="600" cy="90" fill="#ffffff" r="4" stroke="#f97316" stroke-width="2.5"></circle>
                            <circle cx="700" cy="30" fill="#f97316" r="6" stroke="#ffffff" stroke-width="2.5"></circle>
                        </svg>
                    </div>
                    <div class="grid grid-cols-7 text-center pt-space-sm text-secondary font-label-md text-label-md">
                        <span>Sen</span>
                        <span>Sel</span>
                        <span>Rab</span>
                        <span>Kam</span>
                        <span>Jum</span>
                        <span>Sab</span>
                        <span class="text-primary font-bold">Min (Hari ini)</span>
                    </div>
                </div>
            </div>
            <!-- Chart Footer Legend & Meta -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm pt-space-lg mt-space-md">
                <div class="flex items-center gap-space-lg">
                    <div class="flex items-center gap-space-xs">
                        <span class="w-3 h-3 rounded-full bg-primary-container"></span>
                        <span class="font-label-sm text-label-sm text-on-surface font-semibold">Volume Pesanan</span>
                    </div>
                    <div class="flex items-center gap-space-xs">
                        <span class="w-3 h-3 rounded-full bg-surface-container-highest"></span>
                        <span class="font-label-sm text-label-sm text-secondary font-medium">Omset Estimasi (Rp)</span>
                    </div>
                </div>
                <div class="flex items-center gap-space-xs text-secondary font-label-sm text-label-sm">
                    <span class="material-symbols-outlined text-[16px] text-emerald-600">sync</span>
                    <span>Update otomatis: <span class="font-data-mono text-on-surface font-semibold">Baru saja</span></span>
                </div>
            </div>
        </div>

        <!-- Right Column: Courier Fleet Monitoring (4 cols) -->
        <div class="lg:col-span-4 bg-surface-container-lowest rounded-xl p-space-xl shadow-sm flex flex-col justify-between">
            <div class="flex flex-col">
                <div class="flex items-center justify-between pb-space-sm">
                    <div class="flex flex-col">
                        <h3 class="font-headline-lg text-headline-lg text-on-surface">Status Kurir</h3>
                        <p class="font-body-sm text-body-sm text-secondary">Pemantauan armada kurir aktif.</p>
                    </div>
                    <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-emerald-50 text-emerald-700 font-bold flex items-center gap-space-2xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-ping"></span>
                        {{ $stats['active_couriers'] }} Online
                    </span>
                </div>

                @forelse ($activeCouriers as $courier)
                <!-- Courier Profile Card -->
                <div class="{{ $loop->first ? 'mt-space-md' : 'mt-space-sm' }} p-space-md bg-surface-container-low rounded-xl flex flex-col gap-space-md hover:bg-surface-container transition-all">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-space-sm">
                            <div class="relative">
                                <div class="w-11 h-11 rounded-full bg-primary-container text-on-primary font-bold font-headline-sm flex items-center justify-center shadow-sm">
                                    {{ strtoupper(substr($courier->user->name, 0, 1)) }}
                                </div>
                                @if ($courier->is_active)
                                <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                                @endif
                            </div>
                            <div class="flex flex-col">
                                <div class="flex items-center gap-space-xs">
                                    <span class="font-headline-sm text-headline-sm text-on-surface">{{ $courier->user->name }}</span>
                                    @if ($courier->is_verified)
                                    <span class="material-symbols-outlined text-[16px] text-primary" title="Verified Kurir">verified</span>
                                    @endif
                                </div>
                                <span class="font-data-mono text-body-sm text-secondary">{{ $courier->user->phone ?? '-' }}</span>
                            </div>
                        </div>
                        @if ($courier->is_active)
                        <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-emerald-100 text-emerald-800 font-bold">Aktif</span>
                        @else
                        <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-gray-100 text-gray-600 font-bold">Offline</span>
                        @endif
                    </div>
                    <!-- Driver Quick Metrics Breakdown -->
                    <div class="grid grid-cols-3 gap-space-xs bg-surface-container-lowest p-space-sm rounded-lg text-center">
                        <div class="flex flex-col">
                            <span class="font-label-sm text-label-sm text-secondary">Trip Hari Ini</span>
                            <span class="font-headline-sm text-headline-sm text-on-surface font-bold mt-space-2xs">{{ $courier->today_orders ?? 0 }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-label-sm text-label-sm text-secondary">Selesai</span>
                            <span class="font-headline-sm text-headline-sm text-emerald-600 font-bold mt-space-2xs">{{ $courier->completed_today ?? 0 }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-label-sm text-label-sm text-secondary">Rating</span>
                            <div class="flex items-center justify-center gap-space-2xs mt-space-2xs">
                                <span class="material-symbols-outlined text-[14px] text-amber-500" style="font-variation-settings: 'FILL' 1;">star</span>
                                <span class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $courier->rating ?? '4.9' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <!-- Empty State -->
                <div class="mt-space-md p-space-md bg-surface-container-low/60 rounded-xl flex items-center justify-center text-secondary">
                    <div class="flex flex-col items-center gap-space-xs">
                        <span class="material-symbols-outlined text-[24px]">person_off</span>
                        <span class="font-body-sm text-body-sm">Belum ada kurir aktif</span>
                    </div>
                </div>
                @endforelse

                <!-- Idle Courier State Preview -->
                <div class="mt-space-sm p-space-sm rounded-lg bg-surface-container-low/60 flex items-center justify-between text-secondary">
                    <div class="flex items-center gap-space-sm">
                        <span class="material-symbols-outlined text-[18px]">person_off</span>
                        <span class="font-body-sm text-body-sm">Armada cadangan offline</span>
                    </div>
                    <span class="font-label-sm text-label-sm font-semibold text-secondary">{{ $stats['unverified_couriers'] }} Standby</span>
                </div>
            </div>

            <!-- Action Navigation -->
            <div class="mt-space-lg pt-space-sm">
                <a href="{{ route('admin.couriers') }}" class="w-full py-space-sm px-space-md bg-surface-container-low hover:bg-surface-container-high text-on-surface font-label-md text-label-md rounded-lg flex items-center justify-center gap-space-xs transition-all shadow-sm">
                    <span>Lihat Semua Kurir</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Bottom Data Table: Pesanan Terbaru -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-xl flex flex-col gap-space-md">
        <!-- Table Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
            <div class="flex flex-col">
                <h3 class="font-headline-lg text-headline-lg text-on-surface">Pesanan Terbaru</h3>
                <p class="font-body-sm text-body-sm text-secondary">Daftar transaksi pengiriman terkini dan progress rute lapangan.</p>
            </div>
            <div class="flex items-center gap-space-sm flex-wrap">
                <!-- Status Filter Select -->
                <div class="relative inline-block">
                    <select class="appearance-none h-9 pl-space-md pr-8 bg-surface text-body-sm text-on-surface rounded-lg cursor-pointer focus:outline-none focus:bg-surface-container-lowest shadow-sm font-label-md" id="statusFilter">
                        <option value="all">Semua Status</option>
                        <option value="Ditugaskan">Ditugaskan</option>
                        <option value="Selesai">Selesai</option>
                        <option value="Pending">Pending</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-2 top-2 text-secondary pointer-events-none text-[18px]">expand_more</span>
                </div>
                <!-- Create Order Button -->
                <a href="{{ route('admin.orders') }}" class="h-9 px-space-md bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-all flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    <span>Lihat Semua Pesanan</span>
                </a>
            </div>
        </div>

        <!-- Responsive Table Wrapper -->
        <div class="overflow-x-auto w-full mt-space-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-space-sm px-space-md rounded-l-lg font-bold">ID Pesanan</th>
                        <th class="py-space-sm px-space-md font-bold">Pelanggan</th>
                        <th class="py-space-sm px-space-md font-bold">Kurir</th>
                        <th class="py-space-sm px-space-md font-bold">Status</th>
                        <th class="py-space-sm px-space-md text-right rounded-r-lg font-bold">Harga</th>
                    </tr>
                </thead>
                <tbody class="font-body-md text-body-md divide-y divide-surface-container-high/60">
                    @forelse ($recentOrders as $order)
                    <tr class="hover:bg-surface transition-all group">
                        <td class="py-space-md px-space-md whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <span class="font-data-mono font-bold text-primary">{{ $order->tracking_number }}</span>
                                <button class="p-space-2xs text-secondary hover:text-primary transition-colors" onclick="navigator.clipboard.writeText('{{ $order->tracking_number }}')" title="Salin ID" type="button">
                                    <span class="material-symbols-outlined text-[14px]">content_copy</span>
                                </button>
                            </div>
                            <span class="font-label-sm text-label-sm text-secondary font-data-mono">{{ $order->created_at ? $order->created_at->format('d M Y, H:i') : '-' }}</span>
                        </td>
                        <td class="py-space-md px-space-md whitespace-nowrap">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-8 h-8 rounded-full bg-secondary-container text-on-secondary-container font-bold flex items-center justify-center text-label-md">
                                    {{ strtoupper(substr($order->customer_name, 0, 1)) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-body-md text-body-md font-semibold text-on-surface">{{ $order->customer_name }}</span>
                                    <span class="font-label-sm text-label-sm text-secondary font-data-mono">{{ $order->customer_phone ?? '-' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="font-body-md text-body-md font-medium text-on-surface">{{ $order->courier->user->name ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md whitespace-nowrap">
                            @php
                                $statusStyles = [
                                    'pending'    => 'bg-surface-container text-secondary',
                                    'assigned'   => 'bg-secondary-container text-on-secondary-fixed-variant',
                                    'picking_up' => 'bg-blue-50 text-blue-700',
                                    'delivering' => 'bg-amber-50 text-amber-700',
                                    'delivered'  => 'bg-emerald-50 text-emerald-700',
                                    'cancelled'  => 'bg-red-50 text-red-700',
                                ];
                                $statusLabels = [
                                    'pending'    => 'Pending',
                                    'assigned'   => 'Ditugaskan',
                                    'picking_up' => 'Diambil',
                                    'delivering' => 'Diantar',
                                    'delivered'  => 'Selesai',
                                    'cancelled'  => 'Dibatalkan',
                                ];
                                $style = $statusStyles[$order->status] ?? 'bg-surface-container text-secondary';
                                $label = $statusLabels[$order->status] ?? $order->status;
                            @endphp
                            <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full font-label-sm text-label-sm font-bold {{ $style }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ in_array($order->status, ['delivered']) ? 'bg-emerald-600' : ($order->status === 'assigned' ? 'bg-blue-500' : 'bg-current') }}"></span>
                                {{ $label }}
                            </span>
                        </td>
                        <td class="py-space-md px-space-md text-right whitespace-nowrap">
                            <span class="font-data-mono font-bold text-emerald-600 text-sm">Rp {{ number_format($order->price, 0, ',', '.') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-space-2xl px-space-md text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">inbox</span>
                                <span class="font-body-sm text-body-sm">Belum ada pesanan terbaru</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Table Pagination Footer -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-space-sm pt-space-md text-secondary font-label-md text-label-md">
            <span>Menampilkan <strong>1 - {{ count($recentOrders) }}</strong> dari <strong>{{ $stats['total_orders'] }}</strong> pesanan</span>
            <div class="flex items-center gap-space-xs">
                <button class="px-space-sm py-space-xs bg-surface-container-low rounded-lg text-secondary cursor-not-allowed opacity-60 flex items-center" disabled>
                    <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                    <span>Sebelumnya</span>
                </button>
                <button class="w-8 h-8 rounded-lg bg-primary-container text-on-primary font-bold">1</button>
                <a href="{{ route('admin.orders') }}" class="px-space-sm py-space-xs bg-surface-container-low hover:bg-surface-container-high text-on-surface rounded-lg flex items-center transition-all">
                    <span>Berikutnya</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
