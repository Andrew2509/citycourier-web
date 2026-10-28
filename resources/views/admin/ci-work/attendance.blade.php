@extends('layouts.admin')

@section('title', 'Presensi & Pelacakan Kurir')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .chip-btn.active { background: #0f172a; color: #fff; }
    .chip-btn .chip-count { font-size: 10px; background: rgba(0,0,0,.08); border-radius: 999px; padding: 1px 6px; font-weight: 700; }
    .chip-btn.active .chip-count { background: rgba(255,255,255,.25); color: #fff; }
    @keyframes pulsate { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: .45; } }
    .pulsate-dot { animation: pulsate 1.6s ease-in-out infinite; }
    #attendanceMap .leaf-div-icon, #detailMiniMap .leaf-div-icon { background: transparent; border: none; }
    #attendanceMap .att-marker, #detailMiniMap .att-marker {
        display: flex; flex-direction: column; align-items: center; cursor: pointer;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,.3));
    }
    #attendanceMap .att-marker .dot, #detailMiniMap .att-marker .dot {
        width: 32px; height: 32px; border-radius: 999px; position: relative; display: flex; align-items: center;
        justify-content: center; color: #fff; border: 2.5px solid rgba(255,255,255,.95);
        font-size: 15px; box-shadow: 0 2px 6px rgba(0,0,0,.25);
    }
    #attendanceMap .leaflet-popup-content-wrapper, #detailMiniMap .leaflet-popup-content-wrapper { border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.15); }
    #attendanceMap .leaflet-popup-content { margin: 10px 12px; font-family: 'Inter', sans-serif; }
    @media print { header, nav, aside, footer { display: none !important; } }
</style>
@endpush

@php
if (!function_exists('attX')) {
    function attX($lng, $b) { $v = ($lng - $b['lngMin']) / ($b['lngMax'] - $b['lngMin']) * 100; return max(6, min(93, $v)); }
}
if (!function_exists('attY')) {
    function attY($lat, $b) { $v = ($b['latMax'] - $lat) / ($b['latMax'] - $b['latMin']) * 100; return max(7, min(91, $v)); }
}

$total        = $couriers->total();
$onlinePct    = $total > 0 ? (int) round($onlineCount / $total * 100) : 0;
$cutoffHi     = (int) str_replace(':', '', $cutoff ?? '08:00');
$avgOnTime    = $avgCheckin && (int) $avgCheckin->format('Hi') <= $cutoffHi;
$hubX         = attX((float) $hub->longitude, $mapBounds);
$hubY         = attY((float) $hub->latitude, $mapBounds);
$nowStr       = now()->format('H:i:s');

$statusMeta = [
    'online'     => ['Online • Siaga',  'badge badge-success', 'bg-emerald-500'],
    'delivering' => ['Sedang Mengantar','badge badge-warning', 'bg-amber-500'],
    'break'      => ['Istirahat',       'badge badge-neutral', 'bg-sky-500'],
    'offline'    => ['Offline',          'badge badge-neutral text-slate-500', 'bg-slate-400'],
];

$rows = $couriers->getCollection()->values()->map(function ($c) use ($statusMeta) {
    $att = $c->att;
    return [
        'id'        => $c->id,
        'name'      => $c->user->name ?? 'Kurir',
        'initial'   => strtoupper(substr($c->user->name ?? 'K', 0, 1)),
        'email'     => $c->user->email ?? '-',
        'phone'     => $c->user->phone ?? $c->phone ?? '-',
        'vehicle'   => trim(($c->vehicle_type ?? '') . ' ' . ($c->vehicle_plate ?? '')),
        'status'    => $c->presence_status,
        'statusLabel' => $statusMeta[$c->presence_status][0],
        'statusBadge' => $statusMeta[$c->presence_status][1],
        'statusDot'   => $statusMeta[$c->presence_status][2],
        'attTime'   => $att && $att->check_in_at ? $att->check_in_at->format('H:i') : null,
        'late'      => (bool) $c->is_late,
        'duration'  => $att ? $att->duration : null,
        'dropPoint' => $att->drop_point_name ?? '-',
        'address'   => $c->map_lat ? (($c->loc && $c->loc->latitude) ? ($c->city ?? 'Surabaya') : ($c->address ?? 'Surabaya')) : ($c->address ?? '-'),
        'lat'       => $c->map_lat,
        'lng'       => $c->map_lng,
        'accuracy'  => $c->loc && $c->loc->accuracy != null ? (float) $c->loc->accuracy : null,
        'speed'     => $c->loc && $c->loc->speed_kmh != null ? (float) $c->loc->speed_kmh : null,
        'battery'   => $c->loc ? $c->loc->battery_percent : null,
        'lastSeen'  => $c->last_seen_secs,
        'device'    => $att->device ?? 'Aplikasi v1.0.0',
        'active'    => (bool) $c->is_active,
    ];
});

$mapMarks = $mapCouriers->map(fn ($m) => array_merge($m, [
    'x' => attX($m['longitude'], $mapBounds),
    'y' => attY($m['latitude'], $mapBounds),
]))->values();

$hudDefault = $mapMarks->first();
@endphp

@section('content')
<div class="space-y-5">
    <!-- Flash Message -->
    @if(session('success'))
    <div class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-xs font-semibold flex items-center gap-2">
        <span class="material-symbols-outlined text-[17px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Live GPS Telemetri
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Monitoring Presensi Shift</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Presensi &amp; Pelacakan Kurir</h1>
            <p class="text-xs text-slate-500 mt-0.5">Pantau status kehadiran, posisi GPS live, dan telemetri armada secara real-time</p>
        </div>

        <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
            <button type="button" onclick="openDateModal()" class="btn btn-secondary text-xs h-9 px-3 gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-slate-500">calendar_today</span>
                <span>{{ $dateLabel }}</span>
                <span class="material-symbols-outlined text-[15px] text-slate-400">expand_more</span>
            </button>
            <a href="{{ route('admin.ci-work.attendance.export', ['date' => $date]) }}" class="btn btn-secondary text-xs h-9 px-3 gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-slate-500">download</span>
                <span>Ekspor CSV</span>
            </a>
            <button type="button" onclick="refreshSignal()" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">sync</span>
                <span>Refresh Sinyal</span>
            </button>
            <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-[11px] text-slate-500">
                <span id="livePulse" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>GPS Live:</span>
                <span id="liveSyncText" class="font-mono font-semibold text-slate-800">memuat...</span>
            </span>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Hadir / Online</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold font-mono text-slate-900">{{ $onlineCount }}</span>
                        <span class="text-xs text-slate-500 font-medium">Kurir Aktif</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
                </div>
            </div>
            <div class="mt-3 space-y-1.5">
                <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $onlinePct }}%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500">
                    <span>Shift Terpenuhi</span>
                    <span class="font-mono font-bold text-slate-800">{{ $onlinePct }}%</span>
                </div>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">GPS Node Terpantau</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold font-mono text-slate-900">{{ $gpsCount }}</span>
                        <span class="text-xs text-slate-500 font-medium">Node Live</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">radar</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="truncate">{{ $hub->address ?? 'Surabaya' }}</span>
                <span class="badge badge-neutral text-[10px] font-mono shrink-0">±4m Akurasi</span>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Jam Masuk Rata-rata</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-xl font-bold font-mono text-slate-900">{{ $avgCheckin ? $avgCheckin->format('H:i') : '--:--' }} WIB</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">alarm</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500 text-[11px]">Cut-off {{ $cutoff }} WIB</span>
                <span class="badge {{ $avgCheckin && $avgOnTime ? 'badge-success' : 'badge-warning' }} text-[10px]">
                    {{ $avgCheckin && $avgOnTime ? 'Tepat Waktu' : ($avgCheckin ? 'Terlambat' : 'Belum Ada') }}
                </span>
            </div>
        </div>

        <div class="card p-4 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Status Istirahat</span>
                    <div class="flex items-baseline gap-2 mt-1">
                        <span class="text-2xl font-bold font-mono text-slate-900">{{ $chipCounts['break'] }}</span>
                        <span class="text-xs text-slate-500 font-medium">Kurir Pause</span>
                    </div>
                </div>
                <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">free_breakfast</span>
                </div>
            </div>
            <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="text-[11px]">Kapasitas Operasional</span>
                <span class="badge badge-neutral text-[10px] font-mono">{{ $total > 0 ? round(($total - $chipCounts['break']) / $total * 100) : 100 }}%</span>
            </div>
        </div>
    </div>

    <!-- Live Map Cockpit -->
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">map</span>
                <div>
                    <h2 class="text-xs font-bold text-slate-900">Peta Live Tracking Kurir</h2>
                    <span class="text-[11px] text-slate-400">Surabaya &amp; Sekitarnya • Jangkauan {{ $mapCouriers->count() > 0 ? 'radius ±' . round(($mapBounds['latMax'] - $mapBounds['latMin']) * 55) . ' km' : '—' }}</span>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" onclick="mapCenter()" title="Pusatkan peta" class="btn btn-secondary text-xs p-1.5 h-7 w-7">
                    <span class="material-symbols-outlined text-[16px]">center_focus_strong</span>
                </button>
                <button type="button" onclick="mapZoom(1)" title="Perbesar" class="btn btn-secondary text-xs p-1.5 h-7 w-7">
                    <span class="material-symbols-outlined text-[16px]">zoom_in</span>
                </button>
                <button type="button" onclick="mapZoom(-1)" title="Perkecil" class="btn btn-secondary text-xs p-1.5 h-7 w-7">
                    <span class="material-symbols-outlined text-[16px]">zoom_out</span>
                </button>
                <button type="button" onclick="mapFullscreen()" title="Mode penuh layar" class="btn btn-secondary text-xs p-1.5 h-7 w-7">
                    <span class="material-symbols-outlined text-[16px]">fullscreen</span>
                </button>
            </div>
        </div>

        <div class="relative h-[380px] sm:h-[440px]">
            <div id="attendanceMap" class="absolute inset-0 z-0 w-full h-full"></div>

            @if($hudDefault)
            {{-- Telemetry HUD --}}
            <div id="hudCard" class="absolute right-3 top-3 z-[1000] w-[230px] rounded-lg bg-white/95 backdrop-blur shadow-lg border border-slate-200 p-3 space-y-2 text-xs">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs shrink-0">{{ $hudDefault['initial'] }}</div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-slate-900 truncate" id="hudName">{{ $hudDefault['name'] }}</span>
                            <span class="text-[10px] font-mono text-slate-400">#{{ $hudDefault['id'] }}</span>
                        </div>
                        <span id="hudStatus" class="inline-flex items-center gap-1 font-semibold text-emerald-600 text-[10px]">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulsate-dot"></span>Online • Bergerak
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                    <div class="rounded bg-slate-50 p-1.5 border border-slate-100">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Kecepatan</span>
                        <span class="font-mono font-bold text-xs text-slate-800" id="hudSpeed">{{ $hudDefault['speed'] !== null ? $hudDefault['speed'] : '-' }} <span class="text-[9px] font-normal text-slate-400">km/j</span></span>
                    </div>
                    <div class="rounded bg-slate-50 p-1.5 border border-slate-100">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Baterai</span>
                        <span class="font-mono font-bold text-xs text-slate-800" id="hudBattery">{{ $hudDefault['battery'] !== null ? $hudDefault['battery'] . '%' : '-' }}</span>
                    </div>
                </div>

                <div class="rounded bg-slate-50 px-2 py-1 border border-slate-100 flex items-center justify-between text-[11px]">
                    <span class="text-slate-400">Sinyal GPS</span>
                    <span class="font-bold text-emerald-700 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Optimal
                    </span>
                </div>

                <div>
                    <span class="text-[9px] uppercase font-semibold text-slate-400 block">Posisi Terakhir</span>
                    <span class="text-[11px] font-medium text-slate-700 truncate block" id="hudPlace">{{ $hudDefault['city'] }}</span>
                </div>

                <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
                    <span>Terakhir Update:</span>
                    <span class="font-mono font-bold text-slate-600" id="hudTime">{{ $nowStr }} WIB</span>
                </div>
            </div>
            @endif
        </div>

        <div class="flex items-center justify-between px-4 py-2.5 border-t border-slate-100 bg-slate-50/70 text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-slate-700">Zona Aktif:</span>
                <span>Gubeng • Wonokromo • Waru</span>
                <span class="hidden md:inline-flex items-center gap-1 ml-2 px-1.5 py-0.5 rounded bg-white text-[10px] font-mono border border-slate-200">
                    <span class="text-emerald-600 font-bold">●</span> Hub DP
                </span>
                <span class="hidden md:inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white text-[10px] font-mono border border-slate-200">
                    <span class="text-primary-600 font-bold">●</span> Kurir Aktif
                </span>
            </div>
            <span class="text-[11px] text-slate-400 font-mono">Sync: CC-SUB-DP01</span>
        </div>
    </div>

    <!-- Monitoring Kehadiran Table -->
    <div class="card overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">badge</span>
                <div>
                    <h2 class="text-xs font-bold text-slate-900">Daftar Kehadiran Kurir</h2>
                    <span class="text-[11px] text-slate-500">Presensi tanggal {{ $dateLabel }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-100 text-xs" id="filterChips">
                    <button type="button" data-chip="semua" class="chip-btn active px-2.5 py-1 rounded text-xs font-medium transition-colors">Semua <span class="chip-count">{{ $chipCounts['semua'] }}</span></button>
                    <button type="button" data-chip="online" class="chip-btn px-2.5 py-1 rounded text-xs font-medium text-slate-600 transition-colors">Online <span class="chip-count">{{ $chipCounts['online'] }}</span></button>
                    <button type="button" data-chip="delivering" class="chip-btn px-2.5 py-1 rounded text-xs font-medium text-slate-600 transition-colors">Mengantar <span class="chip-count">{{ $chipCounts['delivering'] }}</span></button>
                    <button type="button" data-chip="break" class="chip-btn px-2.5 py-1 rounded text-xs font-medium text-slate-600 transition-colors">Istirahat <span class="chip-count">{{ $chipCounts['break'] }}</span></button>
                    <button type="button" data-chip="offline" class="chip-btn px-2.5 py-1 rounded text-xs font-medium text-slate-600 transition-colors">Offline <span class="chip-count">{{ $chipCounts['offline'] }}</span></button>
                </div>
                <button type="button" onclick="refreshSignal()" title="Muat ulang data" class="btn btn-secondary text-xs h-8 w-8 p-0">
                    <span class="material-symbols-outlined text-[16px]">refresh</span>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between px-4 py-2.5 border-b border-slate-100 bg-white">
            <div class="relative flex-1 max-w-sm">
                <span class="material-symbols-outlined text-[16px] text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2">search</span>
                <input type="text" id="attSearch" oninput="filterRows()" placeholder="Cari nama, telepon, atau email kurir..."
                    class="form-input h-8 pl-8 text-xs w-full">
            </div>
            <div class="flex items-center gap-1.5">
                <a href="{{ route('admin.ci-work.attendance.export', ['date' => $date]) }}" title="Ekspor CSV" class="btn btn-secondary text-xs h-8 px-2.5 gap-1">
                    <span class="material-symbols-outlined text-[15px]">table_view</span>
                    <span class="hidden sm:inline">CSV</span>
                </a>
                <button type="button" onclick="window.print()" title="Cetak Presensi" class="btn btn-secondary text-xs h-8 w-8 p-0">
                    <span class="material-symbols-outlined text-[15px]">print</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kurir</th>
                        <th>Kontak</th>
                        <th>Waktu Check-In</th>
                        <th>Status Presensi</th>
                        <th>Lokasi Terakhir</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr data-courier="{{ $row['id'] }}" data-filter="{{ $row['status'] }}" data-search="{{ strtolower($row['name'] . ' ' . $row['email'] . ' ' . $row['phone']) }}">
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="relative shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs">{{ $row['initial'] }}</div>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full {{ $row['statusDot'] }} border border-white {{ $row['status'] === 'offline' ? '' : 'pulsate-dot' }}"></span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-900 truncate">{{ $row['name'] }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">#{{ $row['id'] }}</span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 block truncate">{{ $row['email'] }}</span>
                                    @if($row['vehicle'])
                                    <span class="text-[10px] text-slate-500 font-mono flex items-center gap-1 mt-0.5"><span class="material-symbols-outlined text-[12px]">two_wheeler</span>{{ $row['vehicle'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="space-y-1">
                                <div class="text-xs font-mono text-slate-700">{{ $row['phone'] }}</div>
                                <div class="flex items-center gap-2 text-xs">
                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['phone'])) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">
                                        <span class="material-symbols-outlined text-[13px]">chat</span> WhatsApp
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($row['attTime'])
                            <div class="space-y-0.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold text-xs text-slate-800">{{ $row['attTime'] }} WIB</span>
                                    <span class="badge {{ $row['late'] ? 'badge-warning' : 'badge-success' }} text-[10px]">
                                        {{ $row['late'] ? 'Terlambat' : 'Tepat Waktu' }}
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 block">{{ $row['dropPoint'] }}</span>
                                @if($row['duration'])
                                <span class="text-[10px] text-slate-400 block font-mono">Shift: {{ $row['duration'] }}</span>
                                @endif
                            </div>
                            @else
                            <span class="text-slate-400 text-xs italic">Belum check-in</span>
                            @endif
                        </td>
                        <td>
                            <div class="space-y-1">
                                <span class="{{ $row['statusBadge'] }} text-[11px]">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $row['statusDot'] }} {{ $row['status'] === 'online' ? 'pulsate-dot' : '' }}"></span>
                                    {{ $row['statusLabel'] }}
                                </span>
                                @if($row['active'])
                                    <span class="block text-[10px] text-slate-400 font-mono">{{ $row['device'] }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="space-y-0.5">
                                <span class="text-xs font-medium text-slate-800 block truncate max-w-[180px]">{{ $row['lat'] ? ($row['address']) : '—' }}</span>
                                <span class="text-[10px] font-mono text-slate-400 block" id="coords-{{ $row['id'] }}">
                                    {{ $row['lat'] ? number_format($row['lat'], 4, ',', '.') . ', ' . number_format($row['lng'], 4, ',', '.') : 'GPS nonaktif' }}
                                </span>
                                @if($row['lastSeen'] !== null)
                                <span class="text-[10px] {{ $row['lastSeen'] <= 60 ? 'text-emerald-600 font-semibold' : 'text-slate-400' }} flex items-center gap-1" id="ls-{{ $row['id'] }}">
                                    <span class="material-symbols-outlined text-[12px]">radar</span> {{ $row['lastSeen'] <= 60 ? $row['lastSeen'] . ' dtk lalu' : $row['lastSeen'] . ' dtk' }}
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1.5">
                                <button type="button" onclick="focusCourier({{ $row['id'] }})" class="btn btn-secondary text-xs h-7 px-2 gap-1" title="Lihat di Peta">
                                    <span class="material-symbols-outlined text-[14px] text-slate-500">map</span>
                                    <span class="hidden sm:inline">Peta</span>
                                </button>
                                <button type="button" onclick="openDetail({{ $row['id'] }})" class="btn btn-primary text-xs h-7 px-2.5 gap-1" title="Detail Kurir">
                                    <span class="material-symbols-outlined text-[14px]">info</span>
                                    <span>Detail</span>
                                </button>
                                <div class="relative">
                                    <button type="button" onclick="toggleMenu(event, {{ $row['id'] }})" class="btn btn-secondary text-xs h-7 w-7 p-0">
                                        <span class="material-symbols-outlined text-[16px]">more_vert</span>
                                    </button>
                                    <div id="qm-{{ $row['id'] }}" class="hidden absolute right-0 top-full mt-1 z-40 w-48 rounded-lg bg-white shadow-xl border border-slate-200 p-1 text-left">
                                        <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['phone'])) }}" target="_blank" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                                            <span class="material-symbols-outlined text-[15px] text-emerald-600">chat</span> WhatsApp
                                        </a>
                                        <a href="tel:{{ $row['phone'] }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                                            <span class="material-symbols-outlined text-[15px] text-sky-600">phone</span> Telepon
                                        </a>
                                        <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center gap-2 px-2.5 py-1.5 rounded text-xs text-slate-700 hover:bg-slate-50 transition-colors">
                                            <span class="material-symbols-outlined text-[15px] text-primary-600">assignment_add</span> Assign Tugas
                                        </a>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <form method="POST" action="{{ route('admin.ci-work.attendance.toggle-active', $row['id']) }}" onsubmit="return confirm('Yakin mengubah status aktif kurir ini?')">
                                            @csrf
                                            <button type="submit" class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded text-xs {{ $row['active'] ? 'text-red-600 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition-colors">
                                                <span class="material-symbols-outlined text-[15px]">{{ $row['active'] ? 'block' : 'power' }}</span>
                                                {{ $row['active'] ? 'Nonaktifkan Kurir' : 'Aktifkan Kurir' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">group_off</span>
                            <p class="text-xs font-semibold text-slate-600">Belum ada data kehadiran pada tanggal ini</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-500">
            <span>Menampilkan {{ $total > 0 ? $couriers->firstItem() . ' - ' . $couriers->lastItem() : 0 }} dari {{ $total }} kurir aktif</span>
            <div>{{ $couriers->withQueryString()->links() }}</div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="card w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50" id="detailHead">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs" id="d-initial">P</div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-sm font-bold text-slate-900" id="d-name">—</h3>
                        <span class="text-[10px] font-mono text-slate-400" id="d-id">ID: —</span>
                    </div>
                    <span class="text-[11px] text-slate-400" id="d-email">—</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="d-status" class="badge"></span>
                <button onclick="closeDetail()" class="text-slate-400 hover:text-slate-600 p-1 rounded-md hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>

        <div class="p-5 overflow-y-auto space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-2" id="detailGrid"></div>

            <div class="space-y-1.5">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Lokasi GPS Terkini</span>
                <div id="detailMiniMap" class="w-full h-36 rounded-lg overflow-hidden border border-slate-200"></div>
                <div class="space-y-0.5">
                    <span class="text-xs font-medium text-slate-800 block" id="d-address">—</span>
                    <span class="text-[10px] font-mono text-slate-400 block" id="d-coords">—</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-slate-100 bg-slate-50/50">
            <a id="d-call" href="#" class="btn btn-secondary text-xs h-8 px-3 gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-500">phone</span> Telepon
            </a>
            <a id="d-wa" href="#" target="_blank" class="btn btn-primary text-xs h-8 px-3 gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[15px]">chat</span> WhatsApp
            </a>
            <button onclick="openDetailOnMap()" class="btn btn-secondary text-xs h-8 px-3 gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-500">map</span> Lihat Peta
            </button>
        </div>
    </div>
</div>

{{-- Date Filter Modal --}}
<div id="dateModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="card w-full max-w-sm p-5 space-y-4 shadow-xl">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary-600">calendar_today</span>
                <h3 class="text-sm font-bold text-slate-900">Filter Tanggal Presensi</h3>
            </div>
            <button onclick="closeDateModal()" class="text-slate-400 hover:text-slate-600 p-1">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <form method="GET" action="{{ route('admin.ci-work.attendance') }}" class="space-y-3.5">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Tanggal</label>
                <input type="date" name="date" value="{{ $date }}" max="{{ today()->format('Y-m-d') }}"
                    class="form-input text-xs w-full">
            </div>
            <div class="flex items-center gap-2 pt-2">
                <button type="submit" class="btn btn-primary text-xs h-9 flex-1">
                    Terapkan
                </button>
                <a href="{{ route('admin.ci-work.attendance') }}" class="btn btn-secondary text-xs h-9 px-3">
                    Hari Ini
                </a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const ATT = {!! json_encode($rows->keyBy('id')) !!};
    const MAPMARKS = {!! json_encode($mapMarks) !!};
    const HUB = {!! json_encode(['name' => $hub->name ?? 'Hub DP', 'lat' => (float) $hub->latitude, 'lng' => (float) $hub->longitude]) !!};
    const ATT_LOC_URL = "{{ route('admin.ci-work.attendance.locations') }}";
    const POLL_MS = 10000;
    window.__ATT = ATT;

    const TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    const statusChipMap = {
        online: { c: 'bg-emerald-500', t: 'text-emerald-700', b: 'bg-emerald-50 border border-emerald-200' },
        delivering: { c: 'bg-amber-500', t: 'text-amber-700', b: 'bg-amber-50 border border-amber-200' },
        break: { c: 'bg-sky-500', t: 'text-sky-700', b: 'bg-sky-50 border border-sky-200' },
        offline: { c: 'bg-slate-400', t: 'text-slate-500', b: 'bg-slate-100 border border-slate-200' },
    };
    const statusLabel = (s) => s === 'online' ? 'Online • Siaga' : s === 'delivering' ? 'Sedang Mengantar' : s === 'break' ? 'Istirahat' : 'Offline';

    let liveMap = null;
    let liveMarkers = {};
    let hubMarker = null;
    let fitBoundsGroup = null;
    let detailMap = null;
    let detailMarker = null;
    let currentDetailId = null;

    function courierIcon(m) {
        const color = m.status === 'offline' ? '#64748b' : '#059669';
        const dot = m.status === 'offline' ? '#94a3b8' : '#34d399';
        const pulse = m.status === 'offline' ? '' : 'animation:pulsate 1.6s ease-in-out infinite';
        return L.divIcon({
            className: 'att-div',
            html: '<div class="att-marker">' +
                '<div class="dot" style="background:' + color + '">' +
                '<span class="material-symbols-outlined" style="font-size:16px;line-height:1">delivery_dining</span>' +
                '<span style="position:absolute;top:-2px;right:-2px;width:10px;height:10px;border-radius:999px;background:' + dot + ';border:2px solid #fff;' + pulse + '"></span>' +
                '</div>' +
                '<div style="margin-top:3px;padding:2px 6px;border-radius:4px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);font-size:10px;font-weight:700;color:#0f172a;white-space:nowrap;">' + m.name + ' <span style="color:#64748b;font-weight:500;">(ID: ' + m.id + ')</span></div>' +
                '</div>',
            iconSize: [78, 58],
            iconAnchor: [39, 36]
        });
    }

    function hubIcon() {
        return L.divIcon({
            className: 'att-div',
            html: '<div class="att-marker">' +
                '<div class="dot" style="background:#ea580c">' +
                '<span class="material-symbols-outlined" style="font-size:17px;line-height:1">warehouse</span>' +
                '<span style="position:absolute;inset:0;border-radius:999px;background:#ea580c;opacity:.35;animation:pulsate 1.6s ease-in-out infinite"></span>' +
                '</div>' +
                '<div style="margin-top:3px;padding:2px 6px;border-radius:4px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);font-size:10px;font-weight:700;color:#c2410c;white-space:nowrap;">' + HUB.name + '</div>' +
                '</div>',
            iconSize: [96, 58],
            iconAnchor: [48, 36]
        });
    }

    function popupHtml(m) {
        const milli = '<span style="display:inline-block;width:7px;height:7px;border-radius:999px;background:' + (m.status === 'offline' ? '#94a3b8' : '#34d399') + ';margin-right:4px;"></span>';
        return '<div style="font-family:Inter,sans-serif;min-width:190px;">' +
            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">' +
            '<div style="width:28px;height:28px;border-radius:999px;background:#0f172a;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:11px;">' + m.initial + '</div>' +
            '<div><div style="font-weight:700;font-size:12px;color:#0f172a;">' + m.name + '</div>' +
            '<div style="font-size:10px;color:#64748b;">' + milli + statusLabel(m.status) + '</div></div>' +
            '</div>' +
            '<div style="font-size:10px;color:#334155;margin-bottom:7px;">' +
            '&#9889; ' + (m.speed !== null ? m.speed : '-') + ' km/j &nbsp;&bull;&nbsp; &#128267; ' + (m.battery !== null ? m.battery : '-') + '% &nbsp;&bull;&nbsp; &#127919; ±' + (m.accuracy !== null ? m.accuracy : '-') + 'm' +
            '</div>' +
            '<button onclick="openDetail(' + m.id + ')" style="width:100%;padding:5px 0;border:0;border-radius:6px;background:#ea580c;color:#fff;font-weight:700;font-size:11px;cursor:pointer;">Lihat Detail</button>' +
            '</div>';
    }

    function hubPopupHtml() {
        return '<div style="font-family:Inter,sans-serif;min-width:160px;">' +
            '<div style="font-weight:700;font-size:12px;color:#c2410c;">' + HUB.name + '</div>' +
            '<div style="font-size:10px;color:#64748b;margin-top:2px;">Hub distribusi • Radius ±150 m</div>' +
            '</div>';
    }

    function initLiveMap() {
        if (liveMap) return;
        liveMap = L.map('attendanceMap', { zoomControl: false }).setView([-7.2575, 112.7521], 12);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(liveMap);

        if (HUB.lat && HUB.lng) {
            hubMarker = L.marker([HUB.lat, HUB.lng], { icon: hubIcon() }).addTo(liveMap).bindPopup(hubPopupHtml());
        }

        MAPMARKS.forEach(function (m) {
            const mk = L.marker([m.latitude, m.longitude], { icon: courierIcon(m) }).addTo(liveMap).bindPopup(popupHtml(m));
            mk.on('click', function () { selectCourier(m.id); });
            mk.__m = m;
            mk.__status = m.status;
            liveMarkers[m.id] = mk;
        });

        const pts = [];
        if (hubMarker) pts.push(hubMarker.getLatLng());
        Object.keys(liveMarkers).forEach(function (id) { pts.push(liveMarkers[id].getLatLng()); });
        if (pts.length > 0) {
            fitBoundsGroup = L.featureGroup(pts.map(function (p) { return L.marker(p); }));
            liveMap.fitBounds(fitBoundsGroup.getBounds().pad(0.25));
        }
        setTimeout(function () { liveMap && liveMap.invalidateSize(); }, 200);
    }

    function updateHud(m) {
        const fuel = document.getElementById('hudCard');
        if (!fuel) return;
        const on = m.status === 'online' || m.status === 'delivering';
        document.getElementById('hudName').textContent = m.name;
        document.getElementById('hudSpeed').innerHTML = (m.speed !== null ? m.speed : '-') + ' <span class="text-[9px] text-slate-400 font-normal">km/j</span>';
        document.getElementById('hudBattery').textContent = (m.battery !== null ? m.battery + '%' : '-');
        document.getElementById('hudStatus').innerHTML = on
            ? '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulsate-dot"></span>Online • Bergerak'
            : '<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Offline';
        document.getElementById('hudPlace').textContent = m.city || '-';
        document.getElementById('hudTime').textContent = new Date().toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
    }

    window.selectCourier = function (id) {
        const m = (liveMarkers[id] && liveMarkers[id].__m) || MAPMARKS.find(x => x.id === id);
        if (!m) return;
        window.__hudId = id;
        updateHud(m);
        const mk = liveMarkers[id];
        if (mk && liveMap) {
            mk.openPopup();
            liveMap.flyTo(mk.getLatLng(), Math.max(liveMap.getZoom(), 14), { duration: 0.5 });
        }
    };

    window.focusCourier = function (id) {
        document.getElementById('attendanceMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => selectCourier(id), 450);
    };

    function initDetailMap(id) {
        const r = ATT[id];
        if (!detailMap) {
            detailMap = L.map('detailMiniMap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false });
            L.tileLayer(TILE, { maxZoom: 19 }).addTo(detailMap);
        }
        if (r && r.lat && r.lng) {
            detailMap.setView([r.lat, r.lng], 15);
            if (detailMarker) {
                detailMarker.setLatLng([r.lat, r.lng]);
            } else {
                detailMarker = L.marker([r.lat, r.lng], { icon: courierIcon(r) }).addTo(detailMap);
            }
        }
        detailMap.invalidateSize();
    }

    window.openDetail = function (id) {
        const r = ATT[id];
        if (!r) return;
        currentDetailId = id;
        document.getElementById('d-initial').textContent = r.initial;
        document.getElementById('d-name').textContent = r.name;
        document.getElementById('d-id').textContent = 'ID: ' + r.id;
        document.getElementById('d-email').textContent = r.email;

        const st = statusChipMap[r.status] || statusChipMap.online;
        document.getElementById('d-status').className = 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold ' + st.b + ' ' + st.t;
        document.getElementById('d-status').innerHTML = '<span class="w-1.5 h-1.5 rounded-full ' + st.c + '"></span>' + statusLabel(r.status);

        const statusTitle = statusLabel(r.status);
        const isOnline = r.status !== 'offline';
        const phone = r.phone || '-';
        const items = [
            { label: 'Status', icon: 'toggle_on', value: statusTitle, extra: isOnline ? 'Aplikasi ' + (r.device || 'v1.0.0') : null },
            { label: 'Telepon', icon: 'call', value: phone, extra: null },
            { label: 'Kendaraan', icon: 'two_wheeler', value: r.vehicle || '-', extra: null },
            { label: 'Drop Point', icon: 'storefront', value: r.dropPoint || '-', extra: null },
            { label: 'Check-In', icon: 'schedule', value: r.attTime ? r.attTime + ' WIB' : 'Belum check-in', extra: r.attTime ? (r.late ? 'Terlambat' : 'Tepat Waktu') : null },
            { label: 'Durasi Shift', icon: 'hourglass', value: r.duration || '-', extra: null },
            { label: 'Kecepatan', icon: 'speed', value: r.speed !== null ? r.speed + ' km/j' : '-', extra: null },
            { label: 'Baterai', icon: 'battery_full', value: r.battery !== null ? r.battery + '%' : '-', extra: r.accuracy !== null ? 'Akurasi ±' + r.accuracy + ' m' : null },
        ];
        document.getElementById('detailGrid').innerHTML = items.map(it => `
            <div class="rounded-lg bg-slate-50 border border-slate-100 p-2.5">
                <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-1 uppercase tracking-wider mb-0.5"><span class="material-symbols-outlined text-[13px]">${it.icon}</span>${it.label}</span>
                <span class="font-medium text-xs text-slate-800">${it.value}</span>
                ${it.extra ? `<span class="block text-[10px] font-semibold text-emerald-600 mt-0.5">${it.extra}</span>` : ''}
            </div>`).join('');

        if (r.lat && r.lng) {
            document.getElementById('d-address').textContent = r.address || 'Surabaya';
            document.getElementById('d-coords').textContent = r.lat.toFixed(5).replace('.', ',') + ', ' + r.lng.toFixed(5).replace('.', ',') + ' • Update ' + (r.lastSeen !== null && r.lastSeen <= 60 ? r.lastSeen + ' dtk lalu' : '—');
        } else {
            document.getElementById('d-address').textContent = '-';
            document.getElementById('d-coords').textContent = 'GPS nonaktif';
        }

        const waNum = String(r.phone || '').replace(/\D/g, '').replace(/^0/, '62');
        document.getElementById('d-wa').href = waNum ? 'https://wa.me/' + waNum : '#';
        document.getElementById('d-call').href = phone !== '-' ? 'tel:' + phone : '#';
        document.getElementById('detailModal').classList.remove('hidden');
        setTimeout(function () { initDetailMap(id); }, 80);
    };
    window.closeDetail = function () { document.getElementById('detailModal').classList.add('hidden'); };
    window.openDetailOnMap = function () {
        closeDetail();
        if (currentDetailId) {
            document.getElementById('attendanceMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => selectCourier(currentDetailId), 450);
        }
    };

    window.openDateModal = function () { document.getElementById('dateModal').classList.remove('hidden'); };
    window.closeDateModal = function () { document.getElementById('dateModal').classList.add('hidden'); };

    window.refreshSignal = function () {
        const btn = event && event.currentTarget ? event.currentTarget : null;
        const spinner = btn ? btn.querySelector('.material-symbols-outlined') : null;
        if (spinner) { spinner.classList.add('animate-spin'); btn.disabled = true; }
        setTimeout(() => window.location.reload(), 250);
    };

    window.toggleMenu = function (e, id) {
        e.stopPropagation();
        const m = document.getElementById('qm-' + id);
        document.querySelectorAll('[id^="qm-"]').forEach(el => { if (el.id !== 'qm-' + id) { el.classList.add('hidden'); } });
        m.classList.toggle('hidden');
    };

    window.filterRows = function () {
        const q = (document.getElementById('attSearch').value || '').toLowerCase();
        const active = document.querySelector('#filterChips .chip-btn.active')?.dataset.chip || 'semua';
        document.querySelectorAll('tbody tr[data-filter]').forEach(tr => {
            const okFilter = active === 'semua' || tr.dataset.filter === active;
            const okSearch = !q || tr.dataset.search.includes(q);
            tr.style.display = okFilter && okSearch ? '' : 'none';
        });
    };

    document.querySelectorAll('#filterChips .chip-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#filterChips .chip-btn').forEach(b => {
                b.classList.remove('active');
                b.classList.add('text-slate-600');
            });
            this.classList.add('active');
            this.classList.remove('text-slate-600');
            filterRows();
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="qm-"]').forEach(el => { if (!el.contains(e.target)) { el.classList.add('hidden'); } });
    });
    document.addEventListener('fullscreenchange', function () { liveMap && liveMap.invalidateSize(); });

    window.mapZoom = function (d) { if (liveMap) liveMap.setZoom(liveMap.getZoom() + d); };
    window.mapCenter = function () {
        if (!liveMap) return;
        if (fitBoundsGroup) liveMap.fitBounds(fitBoundsGroup.getBounds().pad(0.25));
        else liveMap.setView([-7.2575, 112.7521], 12);
    };
    window.mapFullscreen = function () {
        const el = document.getElementById('attendanceMap');
        if (!document.fullscreenElement) { el.requestFullscreen && el.requestFullscreen(); } else { document.exitFullscreen(); }
    };

    function toRow(l) {
        return {
            id: l.id, name: l.name, initial: l.initial, status: l.status,
            lat: l.latitude, lng: l.longitude,
            speed: l.speed, battery: l.battery, accuracy: l.accuracy,
            lastSeen: l.lastSeen, city: l.city,
        };
    }

    let pollTimer = null;

    async function pollLocations() {
        if (!liveMap) return;
        try {
            const res = await fetch(ATT_LOC_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store', credentials: 'same-origin',
            });
            if (!res.ok) return;
            const locs = await res.json();

            locs.forEach(function (l) {
                if (ATT[l.id]) ATT[l.id] = Object.assign({}, ATT[l.id], toRow(l));
                else ATT[l.id] = toRow(l);

                let mk = liveMarkers[l.id];
                if (mk) {
                    mk.setLatLng([l.latitude, l.longitude]);
                    mk.__m = Object.assign({}, mk.__m, l);
                    if (mk.__status !== l.status) {
                        mk.__status = l.status;
                        mk.setIcon(courierIcon(l));
                    }
                    if (mk.isPopupOpen()) mk.setPopupContent(popupHtml(mk.__m));
                } else {
                    const nm = L.marker([l.latitude, l.longitude], { icon: courierIcon(l) }).addTo(liveMap).bindPopup(popupHtml(l));
                    nm.on('click', function () { selectCourier(l.id); });
                    nm.__m = l;
                    nm.__status = l.status;
                    liveMarkers[l.id] = nm;
                }

                const ls = document.getElementById('ls-' + l.id);
                if (ls) {
                    const n = l.lastSeen;
                    ls.textContent = n !== null ? (n <= 60 ? n + ' dtk lalu' : n + ' dtk') : 'GPS nonaktif';
                }
                const coords = document.getElementById('coords-' + l.id);
                if (coords && l.lastSeen !== null) {
                    coords.textContent = Number(l.latitude).toFixed(4).replace('.', ',') + ', ' + Number(l.longitude).toFixed(4).replace('.', ',');
                }
                const row = document.querySelector('tr[data-courier="' + l.id + '"]');
                if (row) row.dataset.filter = l.status;
            });

            if (window.__hudId && liveMarkers[window.__hudId]) updateHud(liveMarkers[window.__hudId].__m);

            if (currentDetailId && detailMarker && liveMarkers[currentDetailId]) {
                const ll = liveMarkers[currentDetailId].getLatLng();
                if (ll) detailMarker.setLatLng([ll.lat, ll.lng]);
            }

            const sync = document.getElementById('liveSyncText');
            if (sync) sync.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
        } catch (e) { /* retry */ }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollLocations, POLL_MS);
    }

    setTimeout(function () { initLiveMap(); startPolling(); pollLocations(); }, 50);
})();
</script>
@endpush
