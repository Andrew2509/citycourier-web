@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md">
        <div>
            <div class="flex items-center gap-space-xs mb-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-bold">City-Work Dispatch Cockpit</span>
                <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                <span class="font-label-sm text-label-sm text-secondary">Zona Operasional: Surabaya Pusat &amp; Timur</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Ci-Work Dashboard Kerja</h1>
            <p class="font-body-md text-body-md text-secondary mt-space-2xs">Monitoring operasional kurir real-time dan dispatch tugas harian berbasis GPS</p>
        </div>
        <div class="flex items-center gap-space-sm self-start lg:self-auto">
            <button onclick="window.location.reload()" class="flex items-center gap-space-xs px-space-md py-space-xs bg-surface-container-lowest text-on-surface hover:bg-surface-container-high rounded-xl shadow-sm transition-all text-body-sm font-body-sm">
                <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
                <span class="font-medium">Segarkan Data</span>
            </button>
        </div>
    </div>

    <!-- KPI Stat Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
        <!-- Card 1: Kurir Online -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Online</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['online_couriers'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Armada Aktif</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $stats['online_couriers'] }} Siap Kerja
                </span>
            </div>
        </div>
        <!-- Card 2: Tugas Berjalan -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Tugas Berjalan</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-display-lg text-display-lg text-primary font-bold font-data-mono">{{ $stats['active_tasks'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Pengiriman</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-amber-50 text-amber-800 font-label-sm text-label-sm font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Sedang Diantar
                </span>
            </div>
        </div>
        <!-- Card 3: Selesai Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Selesai Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['completed_today'] }}</span>
                        <span class="font-label-md text-label-md text-secondary">Paket Drop-off</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-surface-container-high flex items-center justify-center text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-label-sm font-semibold">
                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                    100% On-Time
                </span>
            </div>
        </div>
        <!-- Card 4: Omzet Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Omzet Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span class="font-headline-xl text-headline-xl text-on-surface font-bold font-data-mono">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="font-label-sm text-label-sm text-secondary font-medium">Bersih: <strong class="text-on-surface font-data-mono">Rp {{ number_format($stats['total_earnings_today'] * 0.9, 0, ',', '.') }}</strong></span>
            </div>
        </div>
    </div>

    <!-- 12-Column Operational Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl">
        <!-- Left Column: Active Tasks (8 cols) -->
        <div class="lg:col-span-8 flex flex-col gap-space-xl">
            <!-- Live Task Card -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm pb-space-md">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                            <span class="material-symbols-outlined text-[18px]">radiology</span>
                        </div>
                        <div>
                            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tugas Aktif Terkini</h2>
                            <span class="font-label-sm text-label-sm text-secondary">{{ $stats['active_tasks'] }} Penugasan live terhubung GPS</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-xs">
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-md bg-surface-container-low text-secondary font-data-mono text-[12px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span> Live Tracking
                        </span>
                        <a class="inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:text-surface-tint font-semibold pl-space-xs" href="{{ route('admin.ci-work.tasks') }}">
                            Lihat Semua
                            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                        </a>
                    </div>
                </div>

                @forelse($recentTasks as $task)
                <div class="bg-surface-container-low rounded-xl p-space-md hover:bg-surface-container transition-colors flex flex-col gap-space-md {{ !$loop->last ? 'mb-space-md' : '' }}">
                    <div class="flex flex-wrap items-center justify-between gap-space-sm">
                        <div class="flex items-center gap-space-sm">
                            <div class="px-space-xs py-space-2xs bg-surface-container-highest rounded font-data-mono font-bold text-on-surface text-body-sm">
                                #{{ $task->shipment->tracking_number ?? '-' }}
                            </div>
                            @php
                                $taskStatusStyles = [
                                    'assigned' => 'bg-amber-100 text-amber-900',
                                    'picking_up' => 'bg-blue-50 text-blue-700',
                                    'delivering' => 'bg-amber-50 text-amber-800',
                                    'delivered' => 'bg-emerald-50 text-emerald-700',
                                ];
                                $taskStatusLabel = [
                                    'assigned' => 'Ditugaskan',
                                    'picking_up' => 'Sedang Jemput',
                                    'delivering' => 'Dalam Pengantaran',
                                    'delivered' => 'Selesai',
                                ];
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full {{ $taskStatusStyles[$task->status] ?? 'bg-surface-container text-secondary' }} font-label-sm text-label-sm font-bold">
                                <span class="w-2 h-2 rounded-full {{ $task->status === 'delivering' ? 'bg-amber-600 animate-pulse' : 'bg-current' }}"></span>
                                {{ $taskStatusLabel[$task->status] ?? $task->status }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md items-center py-space-xs">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                                {{ strtoupper(substr($task->courier->user->name ?? 'K', 0, 1)) }}
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold leading-tight">{{ $task->courier->user->name ?? '-' }}</span>
                                <span class="font-label-sm text-label-sm text-secondary">{{ $task->courier->vehicle_type ?? 'Motor' }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col justify-center gap-space-xs bg-surface-container-lowest p-space-sm rounded-lg shadow-sm">
                            <div class="flex items-start gap-space-xs">
                                <span class="material-symbols-outlined text-[18px] text-secondary mt-0.5">store</span>
                                <div class="min-w-0 flex-1">
                                    <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Penjemputan</span>
                                    <p class="font-body-sm text-body-sm text-on-surface font-medium truncate">{{ Str::limit($task->shipment->sender_address ?? '-', 40) }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-space-xs">
                                <span class="material-symbols-outlined text-[18px] text-primary mt-0.5">pin_drop</span>
                                <div class="min-w-0 flex-1">
                                    <span class="font-label-sm text-label-sm text-primary uppercase font-bold">Tujuan Antar</span>
                                    <p class="font-body-sm text-body-sm text-on-surface font-semibold truncate">{{ Str::limit($task->shipment->receiver_address ?? '-', 40) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="bg-surface-container-low rounded-xl p-space-xl flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-sm">
                        <span class="material-symbols-outlined text-[24px]">check_circle</span>
                    </div>
                    <p class="font-body-md text-body-md text-on-surface font-semibold">Semua antrean telah dialokasikan</p>
                    <p class="font-body-sm text-body-sm text-secondary">Tidak ada tugas aktif saat ini.</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Right Column: Quick Links (4 cols) -->
        <div class="lg:col-span-4 flex flex-col gap-space-xl">
            <!-- Tautan Cepat -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex items-center gap-space-sm pb-space-md mb-space-xs">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                        <span class="material-symbols-outlined text-[18px]">link</span>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tautan Cepat & Aksi</h2>
                </div>
                <div class="flex flex-col gap-space-sm">
                    <a href="{{ route('admin.ci-work.attendance') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">badge</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Cek Presensi Kurir</span>
                                <span class="font-label-sm text-label-sm text-secondary">Absensi harian</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.ci-work.tasks') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">assignment</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Manajemen Tugas</span>
                                <span class="font-label-sm text-label-sm text-secondary">Dispatch & routing</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.ci-work.finance') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">payments</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Keuangan & Setoran</span>
                                <span class="font-label-sm text-label-sm text-secondary">Rekap penghasilan</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.couriers') }}" class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Daftar Kurir</span>
                                <span class="font-label-sm text-label-sm text-secondary">Armada & verifikasi</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-[18px] text-secondary group-hover:text-primary transition-colors">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
