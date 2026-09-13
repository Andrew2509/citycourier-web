@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #featuredMapStrip.leaflet-container { pointer-events: none; }
    #featuredMapStrip img.leaflet-tile { filter: saturate(.9) contrast(1.02); }
    .strip-dot, .strip-dot-live {
        width: 16px; height: 16px; border-radius: 999px; background: #f97316; border: 3px solid #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,.45); position: relative;
    }
    .strip-dot::after { content: ''; position: absolute; inset: -6px; border-radius: 999px; background: rgba(249,115,22,.45); }
    .strip-pin {
        width: 14px; height: 14px; border-radius: 999px; background: #166534; border: 3px solid #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,.4);
    }
    .leaf-div-icon { background: transparent; border: none; }
    #trackLiveMap { height: 100%; min-height: 300px; border-radius: 12px; }
    #trackLiveMap .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.18); }
    #trackLiveMap .leaflet-popup-content { margin: 12px 14px; font-family: 'Inter', sans-serif; }
    .ci-toast {
        transform: translateY(14px); opacity: 0; transition: all .25s ease;
    }
    .ci-toast.show { transform: translateY(0); opacity: 1; }
</style>
@endpush

@section('content')
<div class="flex flex-col w-full">
    <!-- Header Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md mb-space-xl">
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
            <button id="btn-refresh" type="button" class="flex items-center gap-space-xs px-space-md py-space-xs bg-surface-container-lowest text-on-surface hover:bg-surface-container-high rounded-xl shadow-sm transition-all text-body-sm font-body-sm">
                <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
                <span class="font-medium">Segarkan Data</span>
                <span class="tick text-[10px] text-secondary font-data-mono pl-1">00:15</span>
            </button>
            <button id="btn-auto-assign" data-act="auto-assign" type="button" class="flex items-center gap-space-xs px-space-md py-space-xs bg-primary text-on-primary hover:bg-surface-tint rounded-xl shadow-sm hover:shadow-md transition-all text-body-sm font-body-sm font-semibold">
                <span class="material-symbols-outlined text-[18px]">bolt</span>
                <span>Auto-Assign Tugas</span>
            </button>
        </div>
    </div>

    <!-- KPI Stat Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mb-space-xl">
        <!-- Card 1: Kurir Online -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Online</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-online" class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['online_couriers'] }}</span>
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
                <span id="kpi1-sub" class="font-label-sm text-label-sm text-secondary font-data-mono">+{{ $stats['standby_count'] }} Standby Drop Point</span>
            </div>
        </div>
        <!-- Card 2: Tugas Berjalan -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Tugas Berjalan</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-active" class="font-display-lg text-display-lg text-primary font-bold font-data-mono">{{ $stats['active_tasks'] }}</span>
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
                <span id="kpi2-sub" class="font-label-sm text-label-sm text-secondary">{{ $stats['featured_eta'] ? 'ETA ' . $stats['featured_eta'] . ' Mnt' : '—' }}</span>
            </div>
        </div>
        <!-- Card 3: Selesai Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Selesai Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-completed" class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $stats['completed_today'] }}</span>
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
                    {{ $stats['on_time_pct'] }}% On-Time
                </span>
                <span id="kpi3-sub" class="font-label-sm text-label-sm text-secondary font-data-mono">SLA 0 Pelanggaran</span>
            </div>
        </div>
        <!-- Card 4: Omzet Hari Ini -->
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Omzet Hari Ini</span>
                    <div class="flex items-baseline gap-space-xs mt-space-xs">
                        <span id="stat-omzet" class="font-headline-xl text-headline-xl text-on-surface font-bold font-data-mono">{{ 'Rp ' . number_format($stats['total_earnings_today'], 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-space-md pt-space-xs flex items-center justify-between">
                <span class="font-label-sm text-label-sm text-secondary font-medium">Bersih: <strong id="stat-net" class="text-on-surface font-data-mono">{{ 'Rp ' . number_format($stats['net_earnings'], 0, ',', '.') }}</strong></span>
                <span id="kpi4-fee" class="font-label-sm text-label-sm text-emerald-700 bg-emerald-50 px-space-xs py-space-2xs rounded-lg font-semibold">+{{ $stats['commission_rate'] }}% fee mitra</span>
            </div>
        </div>
    </div>

    <!-- 12-Column Operational Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl">
        <!-- Left Column: Active Tasks & Dispatch Stream (8 cols) -->
        <div class="lg:col-span-8 flex flex-col gap-space-xl">
            <div id="realtimeZone">
                @include('admin.ci-work.partials.active-tasks', ['stats' => $stats, 'featured' => $featured, 'extraTasks' => $extraTasks, 'queue' => $queue])
            </div>
        </div>

        <!-- Right Column: Dispatch Actions & Fleet Diagnostics (4 cols) -->
        <div class="lg:col-span-4 flex flex-col gap-space-xl">
            <!-- Tautan Cepat & Aksi Dispatcher Card -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex items-center gap-space-sm pb-space-md mb-space-xs">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                        <span class="material-symbols-outlined text-[18px]">link</span>
                    </div>
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Tautan Cepat &amp; Aksi Dispatcher</h2>
                </div>
                <div class="flex flex-col gap-space-sm">
                    <a class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all" href="{{ route('admin.ci-work.attendance') }}">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">badge</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Cek Presensi Kurir</span>
                                <span class="font-label-sm text-label-sm text-secondary">Absensi harian &amp; foto selfie</span>
                            </div>
                        </div>
                        <span id="badge-hadir" class="px-space-xs py-space-2xs rounded-md bg-emerald-100 text-emerald-800 font-label-sm text-label-sm font-bold">
                            {{ $stats['attended_today'] }} Hadir
                        </span>
                    </a>
                    <a class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all" href="{{ route('admin.ci-work.tasks') }}">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-secondary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">swap_calls</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Pantau Antrean Tugas</span>
                                <span class="font-label-sm text-label-sm text-secondary">Alokasi &amp; re-dispatch darurat</span>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-secondary text-[20px] group-hover:translate-x-0.5 transition-transform">chevron_right</span>
                    </a>
                    <a class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all" href="{{ route('admin.ci-work.finance') }}">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-secondary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">account_balance</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Verifikasi Setoran</span>
                                <span class="font-label-sm text-label-sm text-secondary">Kas COD kurir &amp; split fee</span>
                            </div>
                        </div>
                        <span id="badge-setoran" class="px-space-xs py-space-2xs rounded-md bg-primary-fixed text-on-primary-fixed-variant font-label-sm text-label-sm font-bold">
                            {{ $stats['total_earnings_today'] ? 'Rp ' . number_format($stats['total_earnings_today'], 0, ',', '.') : 'Rp 0' }}
                        </span>
                    </a>
                    <a class="group flex items-center justify-between p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-all" href="{{ route('admin.couriers') }}">
                        <div class="flex items-center gap-space-sm">
                            <div class="w-9 h-9 rounded-lg bg-surface-container-lowest flex items-center justify-center text-secondary group-hover:bg-primary group-hover:text-on-primary transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">person_add</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-body-md text-body-md text-on-surface font-semibold group-hover:text-primary transition-colors">Verifikasi Mitra Baru</span>
                                <span class="font-label-sm text-label-sm text-secondary">Validasi KTP, SIM C &amp; STNK</span>
                            </div>
                        </div>
                        <span id="badge-verifikasi" class="px-space-xs py-space-2xs rounded-md bg-amber-100 text-amber-900 font-label-sm text-label-sm font-bold">
                            {{ $stats['unverified_count'] }} Menunggu
                        </span>
                    </a>
                </div>
            </div>

            <!-- Ringkasan Armada Lapangan -->
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col">
                <div class="flex items-center justify-between pb-space-md mb-space-xs">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-8 h-8 rounded-lg bg-secondary-container flex items-center justify-center text-on-secondary-container">
                            <span class="material-symbols-outlined text-[18px]">commute</span>
                        </div>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Ringkasan Armada Lapangan</h2>
                    </div>
                    <span class="font-data-mono text-label-sm text-secondary">Surabaya Zone</span>
                </div>
                <div class="space-y-space-md">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-body-sm text-body-sm text-secondary font-medium">Tipe Kendaraan (Motor)</span>
                            <span id="armada-motor-text" class="font-label-sm text-label-sm text-on-surface font-bold font-data-mono">{{ $stats['motor_pct'] }}% ({{ $stats['motor_units'] }} Unit)</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-surface-container-high overflow-hidden">
                            <div id="armada-motor-bar" class="h-full bg-primary rounded-full" style="width: {{ $stats['motor_pct'] }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-body-sm text-body-sm text-secondary font-medium">Kapasitas Muatan Rata-rata</span>
                            <span id="armada-payload-text" class="font-label-sm text-label-sm text-on-surface font-bold font-data-mono">{{ $stats['capacity_pct'] }}% ({{ $stats['capacity_pct'] >= 75 ? 'Maksimal' : ($stats['capacity_pct'] >= 40 ? 'Optimal' : 'Ringan') }})</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-surface-container-high overflow-hidden">
                            <div id="armada-payload-bar" class="h-full bg-amber-500 rounded-full" style="width: {{ $stats['capacity_pct'] }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-body-sm text-body-sm text-secondary font-medium">Kesehatan Baterai &amp; GPS Telemetri</span>
                            <span id="armada-battery-text" class="font-label-sm text-label-sm text-emerald-700 font-bold font-data-mono">
                                @if($stats['avg_battery'] === null) — @else {{ $stats['avg_battery'] }}% {{ $stats['avg_battery'] >= 80 ? 'Sangat Baik' : ($stats['avg_battery'] >= 50 ? 'Baik' : 'Perlu Charge') }} @endif
                            </span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-surface-container-high overflow-hidden">
                            <div id="armada-battery-bar" class="h-full bg-emerald-500 rounded-full" style="width: {{ $stats['avg_battery'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-space-lg p-space-sm bg-surface-container-low rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-space-sm">
                        <div class="w-7 h-7 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-800">
                            <span class="material-symbols-outlined text-[16px]">location_on</span>
                        </div>
                        <div class="flex flex-col">
                            <span id="dp-name" class="font-label-md text-label-md text-on-surface font-semibold">{{ $stats['drop_point'] }}</span>
                            <span id="dp-coverage" class="font-label-sm text-label-sm text-secondary font-data-mono">Coverage Radius: {{ $stats['coverage_radius_km'] }} km</span>
                        </div>
                    </div>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
            </div>

            <!-- Hub Bantuan & Dispatch Protocol -->
            <div class="bg-surface-container rounded-xl p-space-md flex items-center gap-space-sm shadow-sm">
                <div class="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center text-primary shrink-0 shadow-xs">
                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="font-label-md text-label-md text-on-surface font-semibold block truncate">Pusat Bantuan Operasional (SOP)</span>
                    <span class="font-label-sm text-label-sm text-secondary block truncate">Ada kendala alamat atau pembatalan paket?</span>
                </div>
                <button data-act="sop" class="px-space-xs py-1 rounded bg-surface-container-lowest text-on-surface font-label-sm text-label-sm font-semibold hover:bg-surface-container-high transition-colors">
                    Buka SOP
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Track Live Modal --}}
<div id="trackModal" class="hidden fixed inset-0 z-[1200] flex items-center justify-center p-space-md bg-black/50 backdrop-blur-sm">
    <div class="w-full max-w-2xl bg-surface-container-lowest rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-space-lg py-space-md bg-surface-container-lowest border-b border-surface-container-high">
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-primary-container flex items-center justify-center text-on-primary">
                    <span class="material-symbols-outlined text-[18px]">live_tv</span>
                </div>
                <div>
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Lacak Live</h3>
                    <span id="track-sub" class="font-label-sm text-label-sm text-secondary">#-</span>
                </div>
            </div>
            <button data-act="close-track" class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container-high rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="p-space-lg">
            <div id="trackLiveMap" class="w-full rounded-xl overflow-hidden"></div>
            <div class="mt-space-md grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <div class="rounded-xl bg-surface-container-low p-space-md flex items-start gap-space-sm">
                    <span class="material-symbols-outlined text-[18px] text-primary mt-0.5 shrink-0">motorcycle</span>
                    <div class="min-w-0">
                        <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Kurir</span>
                        <p id="track-name" class="font-body-sm text-body-sm text-on-surface font-semibold truncate">-</p>
                        <p id="track-vehicle" class="font-label-sm text-label-sm text-secondary truncate">-</p>
                    </div>
                </div>
                <div class="rounded-xl bg-surface-container-low p-space-md flex items-start gap-space-sm">
                    <span class="material-symbols-outlined text-[18px] text-primary mt-0.5 shrink-0">pin_drop</span>
                    <div class="min-w-0">
                        <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Tujuan Antar</span>
                        <p id="track-dest" class="font-body-sm text-body-sm text-on-surface font-semibold">-</p>
                        <p id="track-eta" class="font-label-sm text-label-sm text-emerald-700 font-medium">-</p>
                    </div>
                </div>
                <div class="rounded-xl bg-surface-container-low p-space-md flex items-start gap-space-sm">
                    <span class="material-symbols-outlined text-[18px] text-primary mt-0.5 shrink-0">satellite_alt</span>
                    <div class="min-w-0">
                        <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Telemetri GPS</span>
                        <p id="track-acc" class="font-body-sm text-body-sm text-on-surface font-semibold">-</p>
                        <p id="track-lastseen" class="font-label-sm text-label-sm text-secondary">-</p>
                    </div>
                </div>
                <div class="rounded-xl bg-surface-container-low p-space-md flex items-start gap-space-sm">
                    <span class="material-symbols-outlined text-[18px] text-primary mt-0.5 shrink-0">local_shipping</span>
                    <div class="min-w-0">
                        <span class="font-label-sm text-label-sm text-secondary uppercase font-semibold">Resi / Status</span>
                        <p id="track-tracking" class="font-body-sm text-body-sm text-on-surface font-semibold truncate">-</p>
                        <p id="track-status" class="font-label-sm text-label-sm text-amber-700 font-semibold">-</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-space-sm px-space-lg py-space-md bg-surface-container-lowest border-t border-surface-container-high">
            <a id="track-call" href="#" class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-surface-container-highest text-on-surface hover:bg-surface-container-high transition-colors font-label-md text-label-md font-semibold">
                <span class="material-symbols-outlined text-[16px] text-primary">call</span> Telepon
            </a>
            <a id="track-wa" href="#" target="_blank" rel="noopener" class="flex items-center gap-space-2xs px-space-md py-space-xs rounded-lg bg-primary text-on-primary hover:bg-surface-tint transition-colors font-label-md text-label-md font-semibold">
                <span class="material-symbols-outlined text-[16px]">chat</span> WhatsApp
            </a>
        </div>
    </div>
</div>

{{-- SOP Modal --}}
<div id="sopModal" class="hidden fixed inset-0 z-[1200] flex items-center justify-center p-space-md bg-black/50 backdrop-blur-sm">
    <div class="w-full max-w-lg bg-surface-container-lowest rounded-2xl shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-space-lg py-space-md bg-surface-container-lowest border-b border-surface-container-high">
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-surface-container-high flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Panduan Dispatcher &amp; SOP</h3>
            </div>
            <button data-act="close-sop" class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container-high rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="p-space-lg space-y-space-md">
            <div class="rounded-xl bg-surface-container-low p-space-md">
                <span class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-space-xs"><span class="material-symbols-outlined text-[16px] text-primary">location_off</span>Kendala alamat</span>
                <p class="font-body-sm text-body-sm text-secondary mt-1">Hubungi kurir via WhatsApp, minta koordinat GPS, lalu lanjutkan pengantaran atau kembalikan ke Drop Point bila alamat tidak valid.</p>
            </div>
            <div class="rounded-xl bg-surface-container-low p-space-md">
                <span class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-space-xs"><span class="material-symbols-outlined text-[16px] text-primary">cancel</span>Pembatalan paket</span>
                <p class="font-body-sm text-body-sm text-secondary mt-1">Batalkan dari menu Manajemen Tugas lalu setor barang ke Drop Point hub. Catat alasan pembatalan di riwayat pelacakan.</p>
            </div>
            <div class="rounded-xl bg-surface-container-low p-space-md">
                <span class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-space-xs"><span class="material-symbols-outlined text-[16px] text-primary">sos</span>Kontak darurat Ops Lead</span>
                <p class="font-body-sm text-body-sm text-secondary mt-1">Ops Lead: <strong class="text-on-surface">0813-4332-3155</strong> (WhatsApp) — aktif 06.00-22.00 WIB.</p>
            </div>
        </div>
    </div>
</div>

<div id="toastZone" class="fixed bottom-4 right-4 z-[1400] flex flex-col gap-space-sm"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';

    const CIWORK = {
        refresh: '{{ route('admin.ci-work.tasks.refresh') }}',
        autoAssign: '{{ route('admin.ci-work.auto-assign') }}',
        assignBase: '{{ route('admin.ci-work.queue.assign', 0) }}',
        csrf: '{{ csrf_token() }}',
        secs: 15,
    };
    const TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTR = '&copy; OpenStreetMap contributors';

    const $id = (s) => document.getElementById(s);
    const setTxt = (id, v) => { const el = $id(id); if (el) el.textContent = v; };
    const nfRp = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0);
    const fmtK = (n) => { n = n || 0; return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n); };
    const mmss = (n) => String(Math.floor(n / 60)).padStart(2, '0') + ':' + String(n % 60).padStart(2, '0');

    /* ── Toast ── */
    window.toast = function (msg, type) {
        const zone = $id('toastZone');
        if (!zone) return;
        const colors = { success: 'bg-emerald-600', info: 'bg-surface-container-high text-on-surface', error: 'bg-error' };
        const el = document.createElement('div');
        el.className = 'ci-toast show rounded-xl shadow-lg px-space-md py-space-sm text-white font-label-md text-label-md font-semibold ' + (colors[type] || colors.info);
        if (type === 'info') el.className = el.className.replace('text-white', '');
        el.textContent = msg;
        zone.appendChild(el);
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 3800);
    };

    /* ── Featured map strip ── */
    let featuredMap = null, trackMap = null, trackCard = null;

    function initFeaturedStrip() {
        const el = $id('featuredMapStrip');
        if (!el) return;
        if (featuredMap) { featuredMap.remove(); featuredMap = null; }

        const lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
        const dlat = parseFloat(el.dataset.destlat), dlng = parseFloat(el.dataset.destlng);
        if (!lat || !lng) { el.classList.add('hidden'); return; }

        el.classList.remove('hidden');
        featuredMap = L.map(el, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, boxZoom: false, keyboard: false }).setView([lat, lng], 14);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(featuredMap);

        L.marker([lat, lng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-dot"></div>', iconSize: [16, 16], iconAnchor: [8, 8] }) }).addTo(featuredMap);
        if (dlat && dlng) {
            L.marker([dlat, dlng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-pin"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }) }).addTo(featuredMap);
            L.polyline([[lat, lng], [dlat, dlng]], { color: '#f97316', weight: 3, opacity: .85, dashArray: '2 6' }).addTo(featuredMap);
        }
        setTimeout(() => featuredMap && featuredMap.invalidateSize(), 150);
    }

    /* ── Stats sync ── */
    function applyStats(s) {
        if (!s) return;
        setTxt('stat-online', s.online_couriers);
        setTxt('kpi1-sub', '+' + s.standby_count + ' Standby Drop Point');
        setTxt('stat-active', s.active_tasks);
        setTxt('kpi2-sub', s.featured_eta ? 'ETA ' + s.featured_eta + ' Mnt' : '—');
        setTxt('stat-completed', s.completed_today);
        setTxt('stat-omzet', nfRp(s.total_earnings_today));
        setTxt('stat-net', nfRp(s.net_earnings));
        setTxt('kpi4-fee', '+' + s.commission_rate + '% fee mitra');
        setTxt('stat-active-label', s.active_tasks + ' Penugasan live terhubung satelit GPS');
        setTxt('badge-hadir', s.attended_today + ' Hadir');
        setTxt('badge-setoran', fmtK(s.total_earnings_today));
        setTxt('badge-verifikasi', s.unverified_count + ' Menunggu');

        const motor = s.motor_pct || 0;
        setTxt('armada-motor-text', motor + '% (' + s.motor_units + ' Unit)');
        const mb = $id('armada-motor-bar'); if (mb) mb.style.width = motor + '%';

        const cap = s.capacity_pct || 0;
        const capLbl = cap >= 75 ? 'Maksimal' : (cap >= 40 ? 'Optimal' : 'Ringan');
        setTxt('armada-payload-text', cap + '% (' + capLbl + ')');
        const cb = $id('armada-payload-bar'); if (cb) cb.style.width = cap + '%';

        const bat = s.avg_battery;
        let batTxt = '—', batPct = 0;
        if (bat !== null) {
            batPct = bat;
            batTxt = bat + '% ' + (bat >= 80 ? 'Sangat Baik' : (bat >= 50 ? 'Baik' : 'Perlu Charge'));
        }
        setTxt('armada-battery-text', batTxt);
        const bb = $id('armada-battery-bar'); if (bb) bb.style.width = batPct + '%';
    }

    /* ── Poll ── */
    let busy = false;
    async function pollNow() {
        if (busy) return;
        busy = true;
        try {
            const res = await fetch(CIWORK.refresh, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin', cache: 'no-store',
            });
            if (!res.ok) return;
            const d = await res.json();
            const zone = $id('realtimeZone');
            if (zone && d.html) zone.innerHTML = d.html;
            applyStats(d.stats);
            initFeaturedStrip();
        } catch (e) { /* koneksi bermasalah, coba lagi nanti */ }
        finally { busy = false; }
    }

    let cur = CIWORK.secs;
    function paintTick() { const el = document.querySelector('#btn-refresh .tick'); if (el) el.textContent = mmss(cur); }
    function resetCountdown() { cur = CIWORK.secs; paintTick(); }

    setInterval(() => { cur--; if (cur <= 0) { cur = CIWORK.secs; pollNow(); } paintTick(); }, 1000);
    paintTick();

    $id('btn-refresh').addEventListener('click', () => {
        const icon = $id('btn-refresh').querySelector('.material-symbols-outlined');
        if (icon) { icon.classList.add('animate-spin'); setTimeout(() => icon.classList.remove('animate-spin'), 800); }
        resetCountdown(); pollNow();
    });

    /* ── Track Live modal ── */
    window.openTrack = function (card) {
        if (!card) return;
        trackCard = card;
        const d = card.dataset;
        setTxt('track-sub', '#' + (d.tracking || '-'));
        setTxt('track-name', (d.name || '-'));
        setTxt('track-vehicle', (d.vehicle || '') + ' • Plat ' + (d.plate || '-'));
        setTxt('track-dest', d.address || '-');
        setTxt('track-tracking', '#' + (d.tracking || '-'));
        setTxt('track-status', (d.status || '').replace(/_/g, ' '));

        const accTxt = d.accuracy ? d.accuracy + ' Meter' : '—';
        setTxt('track-acc', 'Akurasi ' + accTxt + (d.lastSeen !== '' ? ' • Update ' + d.lastSeen + ' dtk' : ''));

        const lat = parseFloat(d.lat), lng = parseFloat(d.lng);
        const dlat = parseFloat(d.destlat), dlng = parseFloat(d.destlng);
        if (!lat || !lng) {
            setTxt('track-acc', 'GPS kurir belum aktif');
        }
        if (!trackMap && lat && lng) {
            trackMap = L.map('trackLiveMap', { zoomControl: true, attributionControl: false }).setView([lat, lng], 14);
            L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(trackMap);
        } else if (trackMap && lat && lng) {
            trackMap.setView([lat, lng], Math.max(trackMap.getZoom(), 14));
        }

        if (trackMap) {
            trackMap.eachLayer((l) => { if (l instanceof L.Marker || l instanceof L.Polyline) trackMap.removeLayer(l); });
            const c = L.marker([lat, lng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-dot-live"></div>', iconSize: [16, 16], iconAnchor: [8, 8] }) }).addTo(trackMap).bindPopup('<b>' + (d.name || 'Kurir') + '</b><br/><span style="font-size:11px;color:#6b7280">Posisi GPS live</span>');
            c.openPopup();
            if (dlat && dlng) {
                L.marker([dlat, dlng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-pin"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }) }).addTo(trackMap).bindPopup('<b>Tujuan</b><br/><span style="font-size:11px;color:#6b7280">' + (d.address || '') + '</span>');
                L.polyline([[lat, lng], [dlat, dlng]], { color: '#f97316', weight: 3, dashArray: '2 6' }).addTo(trackMap);
            }
        }

        const wa = d.wa ? 'https://wa.me/' + d.wa : '#';
        $id('track-wa').href = wa;
        $id('track-wa').classList.toggle('opacity-40', !d.wa);
        $id('track-call').href = d.phone ? 'tel:' + d.phone : '#';
        $id('track-call').classList.toggle('opacity-40', !d.phone);

        $id('trackModal').classList.remove('hidden');
        setTimeout(() => trackMap && trackMap.invalidateSize(), 120);
    };
    window.closeTrack = function () { $id('trackModal').classList.add('hidden'); };

    /* ── Actions (event delegation) ── */
    document.addEventListener('click', async (e) => {
        const autoBtn = e.target.closest('[data-act="auto-assign"]');
        if (autoBtn) {
            autoBtn.disabled = true; autoBtn.style.opacity = .7;
            toast('Memeriksa armada terdekat di radius 5 km...', 'info');
            try {
                const r = await fetch(CIWORK.autoAssign, { method: 'POST', headers: { 'X-CSRF-TOKEN': CIWORK.csrf, 'Accept': 'application/json' } });
                const d = await r.json();
                toast(d.message || 'Auto-assign selesai.', d.ok ? 'success' : 'error');
                await pollNow();
            } catch (err) { toast('Gagal terhubung ke server.', 'error'); }
            finally { autoBtn.disabled = false; autoBtn.style.opacity = 1; }
            return;
        }

        const assignBtn = e.target.closest('[data-act="assign"]');
        if (assignBtn) {
            const id = assignBtn.dataset.id;
            assignBtn.disabled = true; assignBtn.textContent = 'Menyiapkan...';
            try {
                const r = await fetch(CIWORK.assignBase.replace(/\/0$/, '/' + id), { method: 'POST', headers: { 'X-CSRF-TOKEN': CIWORK.csrf, 'Accept': 'application/json' } });
                const d = await r.json();
                toast(d.message || 'Berhasil.', d.ok ? 'success' : 'error');
                await pollNow();
            } catch (err) { toast('Gagal terhubung ke server.', 'error'); }
            return;
        }

        const poolBtn = e.target.closest('[data-act="pool-check"]');
        if (poolBtn) {
            poolBtn.disabled = true;
            try {
                const r = await fetch(CIWORK.refresh, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await r.json();
                const n = d.stats?.pending_count || 0;
                toast(n > 0 ? 'Ada ' + n + ' order tertunda di pool pesanan. Gunakan Auto-Assign Tugas.' : 'Tidak ada order tertunda. Pool pesanan aman.', n > 0 ? 'info' : 'success');
            } catch (err) { toast('Gagal memeriksa pool.', 'error'); }
            finally { poolBtn.disabled = false; }
            return;
        }

        const trackBtn = e.target.closest('[data-act="track"]');
        if (trackBtn) { window.openTrack(trackBtn.closest('[data-task]')); return; }

        if (e.target.closest('[data-act="close-track"]')) { window.closeTrack(); return; }
        if (e.target.closest('[data-act="sop"]')) { $id('sopModal').classList.remove('hidden'); return; }
        if (e.target.closest('[data-act="close-sop"]')) { $id('sopModal').classList.add('hidden'); return; }
    });

    /* Click outside to close modals */
    document.addEventListener('click', (e) => {
        const tm = $id('trackModal'), sm = $id('sopModal');
        if (tm && !tm.classList.contains('hidden') && e.target === tm) window.closeTrack();
        if (sm && !sm.classList.contains('hidden') && e.target === sm) sm.classList.add('hidden');
    });

    /* Initial featured strip */
    initFeaturedStrip();
})();
</script>
@endpush