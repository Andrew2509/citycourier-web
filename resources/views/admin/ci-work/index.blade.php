@extends('layouts.admin')

@section('title', 'Ci-Work Dispatch Cockpit')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #featuredMapStrip.leaflet-container { pointer-events: none; }
    #featuredMapStrip img.leaflet-tile { filter: saturate(.9) contrast(1.02); }
    .strip-dot, .strip-dot-live {
        width: 14px; height: 14px; border-radius: 999px; background: #ea580c; border: 2.5px solid #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,.35); position: relative;
    }
    .strip-dot::after { content: ''; position: absolute; inset: -5px; border-radius: 999px; background: rgba(234,88,12,.35); }
    .strip-pin {
        width: 13px; height: 13px; border-radius: 999px; background: #059669; border: 2.5px solid #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,.3);
    }
    .leaf-div-icon { background: transparent; border: none; }
    #trackLiveMap { height: 100%; min-height: 280px; border-radius: 8px; }
    #trackLiveMap .leaflet-popup-content-wrapper { border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.15); }
    #trackLiveMap .leaflet-popup-content { margin: 10px 12px; font-family: 'Inter', sans-serif; }
    .ci-toast {
        transform: translateY(12px); opacity: 0; transition: all .2s ease;
    }
    .ci-toast.show { transform: translateY(0); opacity: 1; }
</style>
@endpush

@section('content')
<div class="space-y-5">
    <!-- Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-50 text-primary-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-600 animate-pulse"></span>
                    Live Cockpit
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Zona Surabaya Pusat &amp; Timur</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Ci-Work Dispatch Cockpit</h1>
            <p class="text-xs text-slate-500 mt-0.5">Monitoring operasional kurir real-time dan dispatch tugas harian berbasis GPS</p>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
            <button id="btn-refresh" type="button" class="btn btn-secondary text-xs h-9 px-3 gap-1.5">
                <span class="material-symbols-outlined text-[17px] text-slate-500">refresh</span>
                <span>Segarkan</span>
                <span class="tick text-[11px] font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">00:15</span>
            </button>
            <button id="btn-auto-assign" data-act="auto-assign" type="button" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[17px]">bolt</span>
                <span>Auto-Assign Tugas</span>
            </button>
        </div>
    </div>

    <!-- KPI Stat Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Card 1: Kurir Online -->
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Kurir Online</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span id="stat-online" class="text-2xl font-bold font-mono text-slate-900">{{ $stats['online_couriers'] }}</span>
                        <span class="text-xs text-slate-500 font-medium">Armada Aktif</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $stats['online_couriers'] }} Siap Kerja
                </span>
                <span id="kpi1-sub" class="text-slate-500 font-mono text-[11px]">+{{ $stats['standby_count'] }} Standby Hub</span>
            </div>
        </div>

        <!-- Card 2: Tugas Berjalan -->
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Tugas Berjalan</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span id="stat-active" class="text-2xl font-bold font-mono text-primary-600">{{ $stats['active_tasks'] }}</span>
                        <span class="text-xs text-slate-500 font-medium">Pengiriman</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="inline-flex items-center gap-1 text-amber-700 font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    Sedang Diantar
                </span>
                <span id="kpi2-sub" class="text-slate-500 font-mono text-[11px]">{{ $stats['featured_eta'] ? 'ETA ' . $stats['featured_eta'] . ' mnt' : '—' }}</span>
            </div>
        </div>

        <!-- Card 3: Selesai Hari Ini -->
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Selesai Hari Ini</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span id="stat-completed" class="text-2xl font-bold font-mono text-slate-900">{{ $stats['completed_today'] }}</span>
                        <span class="text-xs text-slate-500 font-medium">Paket</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                    <span class="material-symbols-outlined text-[15px]">check_circle</span>
                    {{ $stats['on_time_pct'] }}% Tepat Waktu
                </span>
                <span id="kpi3-sub" class="text-slate-500 font-mono text-[11px]">SLA Terpenuhi</span>
            </div>
        </div>

        <!-- Card 4: Omzet Hari Ini -->
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Omzet Hari Ini</span>
                    <div class="mt-1">
                        <span id="stat-omzet" class="text-xl font-bold font-mono text-slate-900">{{ 'Rp ' . number_format($stats['total_earnings_today'], 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">payments</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Bersih: <strong id="stat-net" class="text-slate-900 font-mono font-semibold">{{ 'Rp ' . number_format($stats['net_earnings'], 0, ',', '.') }}</strong></span>
                <span id="kpi4-fee" class="badge badge-success text-[10px] font-mono">+{{ $stats['commission_rate'] }}% fee</span>
            </div>
        </div>
    </div>

    <!-- Main Operational Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <!-- Left Column: Active Tasks & Dispatch Stream (8 cols) -->
        <div class="lg:col-span-8 space-y-5">
            <div id="realtimeZone">
                @include('admin.ci-work.partials.active-tasks', ['stats' => $stats, 'featured' => $featured, 'extraTasks' => $extraTasks, 'queue' => $queue])
            </div>
        </div>

        <!-- Right Column: Dispatch Actions & Fleet Diagnostics (4 cols) -->
        <div class="lg:col-span-4 space-y-4">
            <!-- Tautan Cepat & Aksi Dispatcher Card -->
            <div class="card p-4">
                <div class="flex items-center gap-2 pb-3 mb-3 border-b border-slate-100">
                    <span class="material-symbols-outlined text-[18px] text-slate-500">bolt</span>
                    <h2 class="text-sm font-bold text-slate-800">Aksi &amp; Navigasi Operasional</h2>
                </div>
                <div class="space-y-2">
                    <a class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors group" href="{{ route('admin.ci-work.attendance') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded bg-primary-50 text-primary-600 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">badge</span>
                            </div>
                            <div class="truncate">
                                <div class="text-xs font-semibold text-slate-800 group-hover:text-primary-600 transition-colors">Presensi &amp; Telemetri Kurir</div>
                                <div class="text-[11px] text-slate-400">Absensi harian, GPS &amp; baterai</div>
                            </div>
                        </div>
                        <span id="badge-hadir" class="badge badge-success text-[11px] shrink-0 font-mono">
                            {{ $stats['attended_today'] }} Hadir
                        </span>
                    </a>

                    <a class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors group" href="{{ route('admin.ci-work.tasks') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">swap_calls</span>
                            </div>
                            <div class="truncate">
                                <div class="text-xs font-semibold text-slate-800 group-hover:text-primary-600 transition-colors">Manajemen Seluruh Tugas</div>
                                <div class="text-[11px] text-slate-400">Pipeline tugas &amp; bukti foto</div>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-slate-400 text-[18px] group-hover:translate-x-0.5 transition-transform">chevron_right</span>
                    </a>

                    <a class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors group" href="{{ route('admin.ci-work.finance') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">account_balance</span>
                            </div>
                            <div class="truncate">
                                <div class="text-xs font-semibold text-slate-800 group-hover:text-primary-600 transition-colors">Rekonsiliasi Kas &amp; COD</div>
                                <div class="text-[11px] text-slate-400">Penarikan saldo &amp; bagi hasil</div>
                            </div>
                        </div>
                        <span id="badge-setoran" class="badge badge-primary text-[11px] shrink-0 font-mono">
                            {{ $stats['total_earnings_today'] ? 'Rp ' . number_format($stats['total_earnings_today'], 0, ',', '.') : 'Rp 0' }}
                        </span>
                    </a>

                    <a class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 hover:border-slate-200 hover:bg-slate-50 transition-colors group" href="{{ route('admin.couriers') }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-8 h-8 rounded bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">person_add</span>
                            </div>
                            <div class="truncate">
                                <div class="text-xs font-semibold text-slate-800 group-hover:text-primary-600 transition-colors">Verifikasi Mitra Kurir</div>
                                <div class="text-[11px] text-slate-400">Persetujuan KTP, SIM, SKCK</div>
                            </div>
                        </div>
                        <span id="badge-verifikasi" class="badge badge-warning text-[11px] shrink-0 font-mono">
                            {{ $stats['unverified_count'] }} Pending
                        </span>
                    </a>
                </div>
            </div>

            <!-- Ringkasan Armada Lapangan -->
            <div class="card p-4 space-y-3.5">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-slate-500">commute</span>
                        <h2 class="text-sm font-bold text-slate-800">Ringkasan Armada</h2>
                    </div>
                    <span class="font-mono text-[11px] text-slate-400">Surabaya Hub</span>
                </div>

                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between items-center text-xs mb-1">
                            <span class="text-slate-500 font-medium">Armada Motor</span>
                            <span id="armada-motor-text" class="font-bold font-mono text-slate-800">{{ $stats['motor_pct'] }}% ({{ $stats['motor_units'] }} Unit)</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div id="armada-motor-bar" class="h-full bg-primary-500 rounded-full transition-all" style="width: {{ $stats['motor_pct'] }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center text-xs mb-1">
                            <span class="text-slate-500 font-medium">Beban Muatan Armada</span>
                            <span id="armada-payload-text" class="font-bold font-mono text-slate-800">{{ $stats['capacity_pct'] }}% ({{ $stats['capacity_pct'] >= 75 ? 'Maksimal' : ($stats['capacity_pct'] >= 40 ? 'Optimal' : 'Ringan') }})</span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div id="armada-payload-bar" class="h-full bg-amber-500 rounded-full transition-all" style="width: {{ $stats['capacity_pct'] }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center text-xs mb-1">
                            <span class="text-slate-500 font-medium">Telemetri &amp; Baterai Rata-rata</span>
                            <span id="armada-battery-text" class="font-bold font-mono text-emerald-600">
                                @if($stats['avg_battery'] === null) — @else {{ $stats['avg_battery'] }}% {{ $stats['avg_battery'] >= 80 ? 'Sangat Baik' : ($stats['avg_battery'] >= 50 ? 'Baik' : 'Perlu Charge') }} @endif
                            </span>
                        </div>
                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div id="armada-battery-bar" class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $stats['avg_battery'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[15px]">location_on</span>
                        </div>
                        <div>
                            <span id="dp-name" class="font-semibold text-slate-800 block">{{ $stats['drop_point'] }}</span>
                            <span id="dp-coverage" class="text-[11px] text-slate-400 font-mono">Radius: {{ $stats['coverage_radius_km'] }} km</span>
                        </div>
                    </div>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
            </div>

            <!-- Hub Bantuan & Dispatch Protocol -->
            <div class="card p-3.5 bg-slate-50 border-slate-200/80 flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-white text-primary-600 flex items-center justify-center shrink-0 border border-slate-200">
                    <span class="material-symbols-outlined text-[18px]">support_agent</span>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="text-xs font-semibold text-slate-800 block truncate">SOP &amp; Protokol Dispatcher</span>
                    <span class="text-[11px] text-slate-500 block truncate">Kendala alamat atau pembatalan paket</span>
                </div>
                <button data-act="sop" class="btn btn-secondary text-xs px-2.5 py-1 h-auto">
                    Buka SOP
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Track Live Modal --}}
<div id="trackModal" class="hidden fixed inset-0 z-[1200] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="card w-full max-w-2xl overflow-hidden shadow-xl border-slate-200">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">live_tv</span>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">Lacak Kurir Live</h3>
                    <span id="track-sub" class="text-xs font-mono text-slate-500">#-</span>
                </div>
            </div>
            <button data-act="close-track" class="text-slate-400 hover:text-slate-600 p-1 rounded-md hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="p-4 space-y-4">
            <div id="trackLiveMap" class="w-full border border-slate-200"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block mb-0.5">Kurir Bertugas</span>
                    <p id="track-name" class="font-semibold text-slate-800 truncate">-</p>
                    <p id="track-vehicle" class="text-[11px] text-slate-500 font-mono truncate">-</p>
                </div>
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block mb-0.5">Tujuan Antar</span>
                    <p id="track-dest" class="font-semibold text-slate-800 truncate">-</p>
                    <p id="track-eta" class="text-[11px] text-emerald-600 font-medium">-</p>
                </div>
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block mb-0.5">Telemetri Satelit</span>
                    <p id="track-acc" class="font-semibold text-slate-800 truncate">-</p>
                    <p id="track-lastseen" class="text-[11px] text-slate-500">-</p>
                </div>
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                    <span class="text-[10px] text-slate-400 uppercase font-semibold block mb-0.5">Nomor Resi / Status</span>
                    <p id="track-tracking" class="font-semibold font-mono text-slate-800 truncate">-</p>
                    <p id="track-status" class="text-[11px] text-amber-700 font-semibold capitalize">-</p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <a id="track-call" href="#" class="btn btn-secondary text-xs h-8 px-3 gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-500">call</span> Telepon
            </a>
            <a id="track-wa" href="#" target="_blank" rel="noopener" class="btn btn-primary text-xs h-8 px-3.5 gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[15px]">chat</span> Chat WhatsApp
            </a>
        </div>
    </div>
</div>

{{-- SOP Modal --}}
<div id="sopModal" class="hidden fixed inset-0 z-[1200] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="card w-full max-w-lg overflow-hidden shadow-xl border-slate-200">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-primary-600">support_agent</span>
                <h3 class="text-sm font-bold text-slate-900">Panduan Operasional &amp; SOP Dispatcher</h3>
            </div>
            <button data-act="close-sop" class="text-slate-400 hover:text-slate-600 p-1 rounded-md hover:bg-slate-100 transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <div class="p-5 space-y-3 text-xs">
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="font-semibold text-slate-800 flex items-center gap-1.5 mb-1">
                    <span class="material-symbols-outlined text-[16px] text-amber-600">location_off</span>
                    Kendala Alamat Tidak Ditemukan
                </div>
                <p class="text-slate-500 leading-relaxed">Hubungi kurir via WhatsApp untuk meminta share lokasi terkini. Bila penerima tidak merespon dalam 15 menit, arahkan kurir melanjutkan ke order berikutnya atau kembalikan barang ke Hub.</p>
            </div>
            <div class="p-3 rounded-lg bg-slate-50 border border-slate-100">
                <div class="font-semibold text-slate-800 flex items-center gap-1.5 mb-1">
                    <span class="material-symbols-outlined text-[16px] text-red-600">cancel</span>
                    Pembatalan Paket di Lapangan
                </div>
                <p class="text-slate-500 leading-relaxed">Ubah status pesanan lewat menu Manajemen Pesanan. Pastikan kurir menyerahkan barang fisik ke drop point penerima dan catat keterangan di riwayat pelacakan.</p>
            </div>
            <div class="p-3 rounded-lg bg-primary-50/50 border border-primary-100">
                <div class="font-semibold text-slate-900 flex items-center gap-1.5 mb-1">
                    <span class="material-symbols-outlined text-[16px] text-primary-600">sos</span>
                    Kontak Darurat Ops Lead
                </div>
                <p class="text-slate-600 leading-relaxed">Ops Lead: <strong class="text-slate-900 font-mono font-semibold">0813-4332-3155</strong> (WhatsApp) — Layanan koordinasi aktif 06:00 – 22:00 WIB.</p>
            </div>
        </div>
    </div>
</div>

<div id="toastZone" class="fixed bottom-5 right-5 z-[1400] flex flex-col gap-2"></div>
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
        const colors = { success: 'bg-emerald-600 text-white', info: 'bg-slate-800 text-white', error: 'bg-rose-600 text-white' };
        const el = document.createElement('div');
        el.className = 'ci-toast show rounded-lg shadow-lg px-3.5 py-2 text-xs font-semibold ' + (colors[type] || colors.info);
        el.textContent = msg;
        zone.appendChild(el);
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 250); }, 3800);
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

        L.marker([lat, lng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-dot"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }) }).addTo(featuredMap);
        if (dlat && dlng) {
            L.marker([dlat, dlng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-pin"></div>', iconSize: [13, 13], iconAnchor: [6.5, 6.5] }) }).addTo(featuredMap);
            L.polyline([[lat, lng], [dlat, dlng]], { color: '#ea580c', weight: 2.5, opacity: .85, dashArray: '2 6' }).addTo(featuredMap);
        }
        setTimeout(() => featuredMap && featuredMap.invalidateSize(), 150);
    }

    /* ── Stats sync ── */
    function applyStats(s) {
        if (!s) return;
        setTxt('stat-online', s.online_couriers);
        setTxt('kpi1-sub', '+' + s.standby_count + ' Standby Hub');
        setTxt('stat-active', s.active_tasks);
        setTxt('kpi2-sub', s.featured_eta ? 'ETA ' + s.featured_eta + ' mnt' : '—');
        setTxt('stat-completed', s.completed_today);
        setTxt('stat-omzet', nfRp(s.total_earnings_today));
        setTxt('stat-net', nfRp(s.net_earnings));
        setTxt('kpi4-fee', '+' + s.commission_rate + '% fee');
        setTxt('stat-active-label', s.active_tasks + ' Penugasan live terhubung satelit GPS');
        setTxt('badge-hadir', s.attended_today + ' Hadir');
        setTxt('badge-setoran', fmtK(s.total_earnings_today));
        setTxt('badge-verifikasi', s.unverified_count + ' Pending');

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
        } catch (e) { /* silent retry */ }
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
        setTxt('track-vehicle', (d.vehicle || '') + ' • ' + (d.plate || '-'));
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
            const c = L.marker([lat, lng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-dot-live"></div>', iconSize: [14, 14], iconAnchor: [7, 7] }) }).addTo(trackMap).bindPopup('<b>' + (d.name || 'Kurir') + '</b><br/><span style="font-size:11px;color:#6b7280">Posisi GPS live</span>');
            c.openPopup();
            if (dlat && dlng) {
                L.marker([dlat, dlng], { icon: L.divIcon({ className: 'leaf-div-icon', html: '<div class="strip-pin"></div>', iconSize: [13, 13], iconAnchor: [6.5, 6.5] }) }).addTo(trackMap).bindPopup('<b>Tujuan</b><br/><span style="font-size:11px;color:#6b7280">' + (d.address || '') + '</span>');
                L.polyline([[lat, lng], [dlat, dlng]], { color: '#ea580c', weight: 2.5, dashArray: '2 6' }).addTo(trackMap);
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
