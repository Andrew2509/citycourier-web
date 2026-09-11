@extends('layouts.admin')

@push('styles')
<style>
    .chip-btn.active { background: var(--md-sys-color-surface-container-lowest, #fff); box-shadow: 0 1px 3px rgba(0,0,0,.12); }
    .chip-btn .chip-count { font-size: 10px; background: rgba(0,0,0,.06); border-radius: 999px; padding: 0 5px; font-weight: 700; }
    .chip-btn.active .chip-count { background: var(--md-sys-color-primary-container, #ccf1d0); color: var(--md-sys-color-primary, #166534); }
    @keyframes pulsate { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: .45; } }
    .pulsate-dot { animation: pulsate 1.6s ease-in-out infinite; }
    .courier-marker { transition: opacity .25s ease; }
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
    'online'     => ['Online • Siaga',  'bg-emerald-50 text-emerald-700', 'bg-emerald-500'],
    'delivering' => ['Sedang Mengantar','bg-amber-50 text-amber-700',    'bg-amber-500'],
    'break'      => ['Istirahat',       'bg-sky-50 text-sky-700',         'bg-sky-500'],
    'offline'    => ['Offline',          'bg-surface-container text-secondary', 'bg-secondary'],
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
<div class="flex flex-col w-full gap-space-xl">

    <!-- Flash -->
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-space-lg py-space-sm flex items-center gap-space-sm font-label-md text-label-md">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>
        {{ session('success') }}
    </div>
    @endif

    <!-- Breadcrumb -->
    <div class="flex flex-col gap-space-2xs">
        <div class="flex items-center gap-space-xs text-secondary font-label-md">
            <span>City-Work Operasional</span>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-on-surface font-semibold">Presensi & Pelacakan Kurir</span>
        </div>
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-space-lg">
            <div class="flex items-center gap-space-md">
                <div class="w-11 h-11 rounded-xl bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[22px]">near_me</span>
                </div>
                <div class="flex flex-col gap-space-2xs">
                    <h1 class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight leading-tight">Presensi & Pelacakan Kurir</h1>
                    <p class="font-body-sm text-body-sm text-secondary">Pemantauan real-time kehadiran, lokasi GPS, dan telemetri mitra kurir aktif</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="flex items-center gap-space-sm flex-wrap">
                <button type="button" onclick="openDateModal()"
                    class="h-10 px-space-md rounded-lg bg-surface-container-lowest hover:bg-surface-container shadow-sm border border-outline-variant inline-flex items-center gap-space-xs font-label-md text-label-md text-on-surface font-medium transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-secondary">calendar_today</span>
                    <span>Hari ini: <strong class="font-semibold">{{ $dateLabel }}</strong></span>
                    <span class="material-symbols-outlined text-[16px] text-secondary">expand_more</span>
                </button>
                <button type="button" onclick="openDateModal()"
                    class="h-10 px-space-md rounded-lg bg-surface-container-lowest hover:bg-surface-container shadow-sm border border-outline-variant inline-flex items-center gap-space-xs font-label-md text-label-md text-on-surface font-medium transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-secondary">filter_alt</span>
                    Filter Tanggal
                </button>
                <a href="{{ route('admin.ci-work.attendance.export', ['date' => $date]) }}"
                    class="h-10 px-space-md rounded-lg bg-surface-container-lowest hover:bg-surface-container shadow-sm border border-outline-variant inline-flex items-center gap-space-xs font-label-md text-label-md text-on-surface font-medium transition-colors">
                    <span class="material-symbols-outlined text-[18px] text-secondary">download</span>
                    Ekspor Log Kehadiran
                </a>
                <button type="button" onclick="refreshSignal()"
                    class="h-10 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm inline-flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[18px]">sync</span>
                    Refresh Sinyal
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">

        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col gap-space-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[16px]">how_to_reg</span> Total Hadir / Online
                </span>
                <span class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <span class="material-symbols-outlined text-[18px]">person_pin</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $onlineCount }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Kurir Aktif</span>
                <span class="ml-auto inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-xs text-label-sm font-bold {{ $onlinePct >= 100 ? 'bg-emerald-50 text-emerald-700' : 'bg-primary-container text-primary' }}">
                    {{ $onlinePct }}% Shift
                </span>
            </div>
            <div class="w-full h-1.5 rounded-full bg-surface-container-high overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $onlinePct }}%"></div>
            </div>
            <p class="font-label-sm text-label-xs text-secondary flex items-center gap-space-2xs">
                <span class="material-symbols-outlined text-[14px]">task_alt</span> Kehadiran shift tercatat
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col gap-space-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[16px]">gps_fixed</span> Lokasi GPS Terpantau
                </span>
                <span class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600">
                    <span class="material-symbols-outlined text-[18px]">radar</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $gpsCount }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Node Live</span>
                <span class="ml-auto inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-xs text-label-sm font-bold bg-sky-50 text-sky-700">
                    ±4m Akurasi
                </span>
            </div>
            <p class="font-label-sm text-label-xs text-secondary flex items-center gap-space-2xs">
                <span class="material-symbols-outlined text-[14px]">location_on</span>
                {{ $hub->address ?? 'Surabaya' }}
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col gap-space-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[16px]">schedule</span> Jam Masuk Rata-Rata
                </span>
                <span class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center text-violet-600">
                    <span class="material-symbols-outlined text-[18px]">alarm</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold font-data-mono">{{ $avgCheckin ? $avgCheckin->format('H:i') : '--:--' }} WIB</span>
                <span class="ml-auto inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-xs text-label-sm font-bold {{ $avgCheckin && $avgOnTime ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $avgCheckin && $avgOnTime ? 'Tepat Waktu' : ($avgCheckin ? 'Terlambat' : 'Belum Ada') }}
                </span>
            </div>
            <p class="font-label-sm text-label-xs text-secondary flex items-center gap-space-2xs">
                <span class="material-symbols-outlined text-[14px]">flag</span> Shift Pagi ({{ $cutoff }} WIB Cut-off)
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-lg flex flex-col gap-space-sm hover:shadow-md transition-all">
            <div class="flex items-center justify-between">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[16px]">coffee</span> Status Istirahat
                </span>
                <span class="w-8 h-8 rounded-lg bg-primary-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">free_breakfast</span>
                </span>
            </div>
            <div class="flex items-baseline gap-space-sm">
                <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $chipCounts['break'] }}</span>
                <span class="font-label-sm text-label-sm text-secondary">Kurir Pause</span>
                <span class="ml-auto inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-xs text-label-sm font-bold bg-surface-container text-secondary">
                    Kapasitas {{ $total > 0 ? round(($total - $chipCounts['break']) / $total * 100) : 100 }}%
                </span>
            </div>
            <p class="font-label-sm text-label-xs text-secondary flex items-center gap-space-2xs">
                <span class="material-symbols-outlined text-[14px]">restaurant</span> Tidak ada kurir dalam mode istirahat
            </p>
        </div>
    </div>

    <!-- Live Map Cockpit -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">map</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Peta Live Tracking Kurir</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Surabaya &amp; Sekitarnya • Jangkauan {{ $mapCouriers->count() > 0 ? 'radius ±' . round(($mapBounds['latMax'] - $mapBounds['latMin']) * 55) . ' km' : '—' }}</span>
                </div>
            </div>
            <div class="flex items-center gap-space-sm">
                <button type="button" onclick="mapCenter()" title="Pusatkan peta"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">center_focus_strong</span>
                </button>
                <button type="button" onclick="mapZoom(1)" title="Perbesar"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                </button>
                <button type="button" onclick="mapZoom(-1)" title="Perkecil"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">zoom_out</span>
                </button>
                <button type="button" onclick="mapFullscreen()" title="Mode penuh layar"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">fullscreen</span>
                </button>
            </div>
        </div>

        <div id="attendanceMap" class="relative h-[420px] sm:h-[480px] overflow-hidden">
            {{-- Map background + SVG grid --}}
            <div id="mapBg" class="absolute inset-0 bg-[linear-gradient(180deg,#e8f3ec_0%,#ddeeda_35%,#cfe2d8_70%,#f4e7d0_100%)]">
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 800 480" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#7b8f7b" stroke-opacity="0.18" stroke-width="1"/>
                        </pattern>
                        <pattern id="gridLite" width="200" height="200" patternUnits="userSpaceOnUse">
                            <path d="M 200 0 L 0 0 0 200" fill="none" stroke="#5f7a5f" stroke-opacity="0.16" stroke-width="1.5"/>
                        </pattern>
                    </defs>
                    <rect width="800" height="480" fill="url(#grid)"/>
                    <rect width="800" height="480" fill="url(#gridLite)"/>
                    {{-- water --}}
                    <path d="M -20 20 C 120 10, 180 96, 300 80 C 420 64, 470 130, 420 180 C 350 250, 220 210, 120 260 C 40 300, -20 260, -20 20 Z" fill="#a8cfe6" fill-opacity="0.55"/>
                    <path d="M 560 -20 C 620 40, 720 30, 820 80 L 820 -20 Z" fill="#a8cfe6" fill-opacity="0.45"/>
                    {{-- arterial roads --}}
                    <path d="M -20 220 C 140 200, 260 240, 420 210 C 560 186, 680 220, 820 190" fill="none" stroke="#f6c97a" stroke-opacity="0.7" stroke-width="16" stroke-linecap="round"/>
                    <path d="M 220 -20 C 200 120, 250 220, 200 480" fill="none" stroke="#f6c97a" stroke-opacity="0.6" stroke-width="13" stroke-linecap="round"/>
                    <path d="M 560 -20 C 540 140, 590 260, 620 480" fill="none" stroke="#f6c97a" stroke-opacity="0.55" stroke-width="12" stroke-linecap="round"/>
                    <path d="M -20 360 C 160 340, 420 380, 820 350" fill="none" stroke="#f3d89c" stroke-opacity="0.55" stroke-width="9" stroke-linecap="round"/>
                    <path d="M 100 60 C 140 160, 380 120, 460 200" fill="none" stroke="#e8c27a" stroke-opacity="0.5" stroke-width="7" stroke-linecap="round"/>
                    <circle cx="420" cy="240" r="180" fill="none" stroke="#4a7d5a" stroke-opacity="0.12" stroke-dasharray="6 10" stroke-width="1.5"/>
                    <circle cx="420" cy="240" r="230" fill="none" stroke="#4a7d5a" stroke-opacity="0.08" stroke-dasharray="4 12" stroke-width="1.5"/>
                </svg>
            </div>

            {{-- Hub marker --}}
            <button type="button" style="left: {{ $hubX }}%; top: {{ $hubY }}%; transform: translate(-50%, -50%);"
                class="absolute z-20 flex flex-col items-center cursor-pointer group" title="{{ $hub->name ?? 'Drop Point' }}">
                <span class="relative flex items-center justify-center w-10 h-10 rounded-full bg-amber-500 text-white shadow-lg ring-4 ring-white/80 group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-[20px]">warehouse</span>
                    <span class="absolute inset-0 rounded-full bg-amber-500 animate-ping opacity-30"></span>
                </span>
                <span class="mt-1 whitespace-nowrap px-2 py-0.5 rounded-md bg-white/95 shadow text-[11px] font-bold text-amber-800">{{ $hub->name ?? 'Hub' }}</span>
            </button>

            {{-- Courier markers --}}
            @foreach($mapMarks as $mark)
            <button type="button" data-marker="{{ $mark['id'] }}" onclick="selectCourier({{ $mark['id'] }})"
                style="left: {{ $mark['x'] }}%; top: {{ $mark['y'] }}%; transform: translate(-50%, -50%);"
                class="courier-marker absolute z-10 flex flex-col items-center cursor-pointer group">
                <span class="relative flex items-center justify-center w-9 h-9 rounded-full bg-primary text-white shadow-lg ring-4 ring-white/90 group-hover:scale-110 transition-transform courier-dot">
                    <span class="material-symbols-outlined text-[18px]">delivery_dining</span>
                    <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full {{ $mark['status'] === 'offline' ? 'bg-secondary' : 'bg-emerald-400' }} border-2 border-white pulsate-dot"></span>
                </span>
                <span class="mt-1 whitespace-nowrap px-2 py-0.5 rounded-md bg-white/95 shadow text-[11px] font-bold text-on-surface">{{ $mark['name'] }}
                    <span class="text-secondary font-medium">(ID: {{ $mark['id'] }})</span>
                </span>
            </button>
            @endforeach

            @if($mapMarks->isEmpty())
            <div class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-space-xs text-secondary pointer-events-none">
                <span class="material-symbols-outlined text-[40px]">gps_off</span>
                <span class="font-label-md text-label-md">Belum ada sinyal GPS dari kurir</span>
            </div>
            @endif

            @if($hudDefault)
            {{-- Telemetry HUD --}}
            <div id="hudCard" class="absolute right-3 top-3 z-30 w-[248px] rounded-xl bg-white/95 backdrop-blur shadow-xl border border-surface-container-high p-3.5 flex flex-col gap-2.5">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-primary-container text-primary flex items-center justify-center font-bold text-sm shrink-0">{{ $hudDefault['initial'] }}</div>
                    <div class="flex flex-col min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-semibold text-sm text-on-surface truncate" id="hudName">{{ $hudDefault['name'] }}</span>
                            <span class="text-[10px] font-mono font-bold bg-surface-container rounded px-1 py-0.5 text-secondary">ID: {{ $hudDefault['id'] }}</span>
                        </div>
                        <span class="text-[11px] text-secondary flex items-center gap-1">
                            <span id="hudStatus" class="inline-flex items-center gap-1 font-semibold text-emerald-600"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulsate-dot"></span>Online • Bergerak</span>
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1.5">
                    <div class="rounded-lg bg-surface-container-low px-2 py-1.5 flex flex-col">
                        <span class="text-[10px] text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">speed</span>Kecepatan</span>
                        <span class="font-mono font-bold text-sm text-on-surface" id="hudSpeed">{{ $hudDefault['speed'] !== null ? $hudDefault['speed'] : '-' }} <span class="text-[10px] text-secondary font-medium">km/j</span></span>
                    </div>
                    <div class="rounded-lg bg-surface-container-low px-2 py-1.5 flex flex-col">
                        <span class="text-[10px] text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">battery_full</span>Baterai</span>
                        <span class="font-mono font-bold text-sm text-on-surface" id="hudBattery">{{ $hudDefault['battery'] !== null ? $hudDefault['battery'] . '%' : '-' }}</span>
                    </div>
                </div>
                <div class="rounded-lg bg-surface-container-low px-2 py-1.5 flex items-center justify-between">
                    <span class="text-[10px] text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">signal_cellular_alt</span>Sinyal GPS</span>
                    <span class="inline-flex items-center gap-1 font-bold text-[11px] text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Baik</span>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-[10px] text-secondary font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">place</span>Last Known Place</span>
                    <span class="text-[11px] font-medium text-on-surface" id="hudPlace">{{ $hudDefault['city'] }}</span>
                </div>
                <div class="flex items-center justify-between pt-1.5 border-t border-surface-container-high">
                    <span class="text-[10px] text-secondary">Update</span>
                    <span class="font-mono text-[11px] font-bold text-secondary" id="hudTime">{{ $nowStr }} WIB</span>
                </div>
            </div>
            @endif
        </div>

        <div class="flex items-center justify-between px-space-xl py-space-sm border-t border-surface-container-high bg-surface-container-low/40">
            <div class="flex items-center gap-space-sm font-label-sm text-label-sm text-secondary">
                <span class="material-symbols-outlined text-[16px]">layers</span>
                <span><strong class="text-on-surface font-semibold">Zona Aktif:</strong> Gubeng • Wonokromo • Waru</span>
                <span class="hidden md:inline-flex items-center gap-1 ml-2 px-2 py-0.5 rounded-md bg-white/80 text-[11px] font-mono"><span class="text-emerald-600 font-bold">●</span> Hub DP</span>
                <span class="hidden md:inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/80 text-[11px] font-mono"><span class="text-primary font-bold">●</span> Kurir Online</span>
            </div>
            <span class="font-label-xs text-label-xs text-secondary flex items-center gap-space-2xs">
                <span class="material-symbols-outlined text-[14px]">compass_calibration</span>
                Sync Server ID: <span class="font-mono font-semibold text-on-surface">CC-SUB-DP01</span>
            </span>
        </div>
    </div>

    <!-- Monitoring Kehadiran Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-space-md px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">badge</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Monitoring Kehadiran</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Daftar presensi {{ $dateLabel }}</span>
                </div>
            </div>
            <div class="flex items-center gap-space-sm">
                <div class="flex items-center gap-1 p-1 rounded-lg bg-surface-container font-label-sm text-label-sm font-semibold" id="filterChips">
                    <button type="button" data-chip="semua" class="chip-btn active px-2.5 py-1 rounded-md text-on-surface inline-flex items-center gap-1 transition-colors">Semua <span class="chip-count">{{ $chipCounts['semua'] }}</span></button>
                    <button type="button" data-chip="online" class="chip-btn px-2.5 py-1 rounded-md text-secondary inline-flex items-center gap-1 transition-colors">Online Siaga <span class="chip-count">{{ $chipCounts['online'] }}</span></button>
                    <button type="button" data-chip="delivering" class="chip-btn px-2.5 py-1 rounded-md text-secondary inline-flex items-center gap-1 transition-colors">Sedang Mengantar <span class="chip-count">{{ $chipCounts['delivering'] }}</span></button>
                    <button type="button" data-chip="break" class="chip-btn px-2.5 py-1 rounded-md text-secondary inline-flex items-center gap-1 transition-colors">Istirahat <span class="chip-count">{{ $chipCounts['break'] }}</span></button>
                    <button type="button" data-chip="offline" class="chip-btn px-2.5 py-1 rounded-md text-secondary inline-flex items-center gap-1 transition-colors">Offline <span class="chip-count">{{ $chipCounts['offline'] }}</span></button>
                </div>
                <button type="button" onclick="refreshSignal()" title="Muat ulang data"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between px-space-xl py-space-sm border-b border-surface-container-high/60">
            <div class="relative flex-1 max-w-sm">
                <span class="material-symbols-outlined text-[18px] text-secondary absolute left-3 top-1/2 -translate-y-1/2">search</span>
                <input type="text" id="attSearch" oninput="filterRows()" placeholder="Cari nama, telepon, atau email kurir..."
                    class="w-full h-9 pl-9 pr-3 rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface font-label-sm text-label-sm focus:outline-none focus:ring-2 focus:ring-primary/40 placeholder:text-secondary">
            </div>
            <div class="flex items-center gap-space-xs">
                <a href="{{ route('admin.ci-work.attendance.export', ['date' => $date]) }}" title="Ekspor Excel / CSV"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">table_view</span>
                </a>
                <button type="button" onclick="window.print()" title="Cetak"
                    class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[980px]">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Telepon / Kontak</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Waktu Check-In</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status Presensi</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Lokasi Terakhir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap text-right">Aksi Dispatch</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($rows as $row)
                    <tr class="hover:bg-surface transition-colors" data-filter="{{ $row['status'] }}" data-search="{{ strtolower($row['name'] . ' ' . $row['email'] . ' ' . $row['phone']) }}">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="relative shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center font-bold text-sm">{{ $row['initial'] }}</div>
                                    <span class="absolute -bottom-0 -right-0 w-3 h-3 rounded-full {{ $row['statusDot'] }} border-2 border-white {{ $row['status'] === 'offline' ? '' : 'pulsate-dot' }}"></span>
                                </div>
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-semibold">{{ $row['name'] }}</span>
                                        <span class="text-[10px] font-mono font-bold bg-surface-container rounded px-1 py-0.5 text-secondary">{{ $row['status'] === 'online' || $row['status'] === 'delivering' ? 'Online' : 'Nonaktif' }}</span>
                                    </div>
                                    <span class="text-xs text-secondary">{{ $row['email'] }}</span>
                                    @if($row['vehicle'])
                                    <span class="text-xs text-secondary flex items-center gap-1 mt-0.5"><span class="material-symbols-outlined text-[13px]">two_wheeler</span>{{ $row['vehicle'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex flex-col gap-1">
                                <span class="text-secondary flex items-center gap-1 whitespace-nowrap"><span class="material-symbols-outlined text-[15px]">call</span>{{ $row['phone'] }}</span>
                                <div class="flex items-center gap-1.5">
                                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['phone'])) }}"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:underline">
                                        <span class="material-symbols-outlined text-[14px]">chat</span>WhatsApp
                                    </a>
                                    <a href="tel:{{ $row['phone'] }}" title="Telepon"
                                        class="w-6 h-6 rounded-md bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[15px]">phone</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            @if($row['attTime'])
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono font-bold">{{ $row['attTime'] }} WIB</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $row['late'] ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $row['late'] ? 'Terlambat' : 'Tepat Waktu' }}
                                    </span>
                                </div>
                                <span class="text-xs text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">storefront</span>{{ $row['dropPoint'] }}</span>
                                @if($row['duration'])
                                <span class="text-xs text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">hourglass</span>Durasi Shift: {{ $row['duration'] }}</span>
                                @endif
                            </div>
                            @else
                            <span class="text-secondary text-xs">Belum check-in</span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            @if(in_array($row['status'], ['online', 'delivering']))
                            <span class="inline-flex items-center gap-2 px-space-sm py-space-2xs rounded-full {{ $row['statusBadge'] }} font-label-sm text-xs font-bold">
                                <span class="w-1.5 h-1.5 rounded-full {{ $row['statusDot'] }} {{ $row['status'] === 'online' ? 'pulsate-dot' : '' }}"></span>
                                {{ $row['statusLabel'] }}
                            </span>
                            <span class="block text-[11px] text-secondary mt-1">Aplikasi {{ $row['device'] }} • Aktif</span>
                            @else
                            <span class="inline-flex items-center gap-2 px-space-sm py-space-2xs rounded-full {{ $row['statusBadge'] }} font-label-sm text-xs font-bold">
                                <span class="w-1.5 h-1.5 rounded-full {{ $row['statusDot'] }}"></span>
                                {{ $row['statusLabel'] }}
                            </span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs font-semibold text-on-surface">{{ $row['lat'] ? ($row['address']) : '—' }}</span>
                                <span class="text-[11px] text-secondary">{{ $row['lat'] ? number_format($row['lat'], 5, ',', '.') . ', ' . number_format($row['lng'], 5, ',', '.') : 'GPS nonaktif' }}</span>
                                @if($row['lastSeen'] !== null)
                                <span class="text-[11px] {{ $row['lastSeen'] <= 60 ? 'text-emerald-600 font-semibold' : 'text-secondary' }} flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">radar</span> Sinyal update {{ $row['lastSeen'] <= 60 ? $row['lastSeen'] . ' detik lalu' : 'sejak ' . $row['lastSeen'] . ' dtk' }}
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" onclick="focusCourier({{ $row['id'] }})"
                                    class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high text-xs font-semibold transition-colors">
                                    <span class="material-symbols-outlined text-[15px] text-secondary">map</span> Lihat Map
                                </button>
                                <button type="button" onclick="openDetail({{ $row['id'] }})"
                                    class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg bg-primary-container text-primary hover:bg-primary hover:text-on-primary text-xs font-semibold transition-colors">
                                    <span class="material-symbols-outlined text-[15px]">info</span> Detail
                                </button>
                                <div class="relative">
                                    <button type="button" onclick="toggleMenu(event, {{ $row['id'] }})"
                                        class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">more_vert</span>
                                    </button>
                                    <div id="qm-{{ $row['id'] }}" class="hidden absolute right-0 top-full mt-1 z-40 w-52 rounded-xl bg-surface-container-lowest shadow-xl border border-surface-container-high p-1.5 flex-col">
                                        <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $row['phone'])) }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-on-surface hover:bg-surface-container transition-colors">
                                            <span class="material-symbols-outlined text-[17px] text-emerald-600">chat</span> WhatsApp
                                        </a>
                                        <a href="tel:{{ $row['phone'] }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-on-surface hover:bg-surface-container transition-colors">
                                            <span class="material-symbols-outlined text-[17px] text-sky-600">phone</span> Telepon
                                        </a>
                                        <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-on-surface hover:bg-surface-container transition-colors">
                                            <span class="material-symbols-outlined text-[17px] text-primary">assignment_add</span> Assign Tugas
                                        </a>
                                        <form method="POST" action="{{ route('admin.ci-work.attendance.toggle-active', $row['id']) }}" onsubmit="return confirm('Yakin mengubah status aktif kurir ini?')">
                                            @csrf
                                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm {{ $row['active'] ? 'text-red-600 hover:bg-red-50' : 'text-emerald-700 hover:bg-emerald-50' }} transition-colors">
                                                <span class="material-symbols-outlined text-[17px]">{{ $row['active'] ? 'block' : 'power' }}</span>
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
                        <td colspan="6" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">group</span>
                                <span class="font-body-sm text-body-sm">Belum ada data kehadiran pada tanggal ini</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-space-sm px-space-xl py-3.5 border-t border-surface-container-high">
            <span class="font-label-sm text-label-sm text-secondary">
                Menampilkan {{ $total > 0 ? $couriers->firstItem() . ' - ' . $couriers->lastItem() : 0 }} dari {{ $total }} kurir aktif • Sync Server ID: <span class="font-mono font-semibold text-on-surface">CC-SUB-DP01</span>
            </span>
            {{ $couriers->links() }}
        </div>
    </div>

    <!-- Bottom Banner -->
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-r from-[#0f4c2e] to-[#1f7a4d] shadow-md">
        <div class="absolute -right-8 -top-10 opacity-15">
            <span class="material-symbols-outlined text-[160px] text-white">route</span>
        </div>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-lg px-space-xl py-space-lg relative">
            <div class="flex flex-col gap-space-2xs max-w-xl">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-emerald-200">
                    <span class="material-symbols-outlined text-[14px]">security</span> Integrasi GPS Driver &amp; Geofencing Presensi
                </span>
                <h3 class="font-headline-sm text-headline-sm text-white font-bold">Pantau seluruh armada langsung dari satu dashboard</h3>
                <p class="font-body-sm text-body-sm text-emerald-100/90">Latensi telemetri &lt;15 detik • Radius geofence Drop Point ±150 m • Notifikasi keterlambatan otomatis.</p>
            </div>
            <a href="{{ route('admin.ci-work.tasks') }}"
                class="inline-flex items-center gap-2 h-11 px-space-lg rounded-xl bg-white text-[#0f4c2e] hover:bg-emerald-50 font-label-md text-label-md font-bold shadow-lg transition-colors">
                <span class="material-symbols-outlined text-[19px]">assignment_add</span>
                Assign Tugas ke {{ $mapMarks->first()['name'] ?? 'Kurir' }}
            </a>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeDetail()"></div>
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-surface-container-lowest shadow-2xl">
        <div class="flex items-start justify-between px-space-xl py-space-md border-b border-surface-container-high sticky top-0 bg-surface-container-lowest rounded-t-2xl z-10">
            <div class="flex items-center gap-space-sm" id="detailHead">
                <div class="w-11 h-11 rounded-full bg-primary-container text-primary flex items-center justify-center font-bold" id="d-initial">P</div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-2">
                        <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold" id="d-name">—</h3>
                        <span class="text-[10px] font-mono font-bold bg-surface-container rounded px-1 py-0.5 text-secondary" id="d-id">ID: —</span>
                    </div>
                    <span class="font-label-sm text-label-sm text-secondary" id="d-email">—</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="d-status" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-xs font-bold"></span>
                <button onclick="closeDetail()" class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>
        <div class="px-space-xl py-space-lg flex flex-col gap-space-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md" id="detailGrid"></div>
            <div class="flex flex-col gap-space-xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Lokasi Terakhir</span>
                <div class="relative rounded-xl overflow-hidden border border-surface-container-high h-40">
                    <div class="absolute inset-0 bg-[linear-gradient(180deg,#e8f3ec,#cfe2d8)]">
                        <svg class="absolute inset-0 w-full h-full" viewBox="0 0 800 480" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                            <defs><pattern id="dgrid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="#7b8f7b" stroke-opacity="0.18" stroke-width="1"/></pattern></defs>
                            <rect width="800" height="480" fill="url(#dgrid)"/>
                            <path d="M -20 220 C 140 200, 260 240, 420 210 C 560 186, 680 220, 820 190" fill="none" stroke="#f6c97a" stroke-opacity="0.7" stroke-width="16" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span id="d-minimarker" class="absolute w-9 h-9 rounded-full bg-primary text-white shadow-lg ring-4 ring-white/90 flex items-center justify-center" style="--mx: 50%; --my: 50%; left: var(--mx); top: var(--my); transform: translate(-50%,-50%);">
                        <span class="material-symbols-outlined text-[17px]">delivery_dining</span>
                    </span>
                </div>
                <span class="font-label-sm text-label-sm text-secondary" id="d-address">—</span>
                <span class="font-label-xs text-label-xs text-secondary font-mono" id="d-coords">—</span>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a id="d-wa" href="#" target="_blank" class="inline-flex items-center gap-1.5 h-10 px-space-md rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[17px]">chat</span> WhatsApp
                </a>
                <a id="d-call" href="#" class="inline-flex items-center gap-1.5 h-10 px-space-md rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[17px]">phone</span> Telepon
                </a>
                <button onclick="closeDetail(); focusCourierDetail()" class="inline-flex items-center gap-1.5 h-10 px-space-md rounded-lg bg-primary-container hover:bg-primary hover:text-on-primary text-primary font-label-md text-label-md font-semibold transition-colors">
                    <span class="material-symbols-outlined text-[17px]">map</span> Lihat di Peta
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Date Filter Modal --}}
<div id="dateModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40" onclick="closeDateModal()"></div>
    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm rounded-2xl bg-surface-container-lowest shadow-2xl p-space-lg flex flex-col gap-space-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary"><span class="material-symbols-outlined text-[20px]">filter_alt</span></span>
                <div class="flex flex-col">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Filter Tanggal</h3>
                    <span class="font-label-sm text-label-sm text-secondary">Pilih hari presensi yang ingin dilihat</span>
                </div>
            </div>
            <button onclick="closeDateModal()" class="w-8 h-8 rounded-lg bg-surface-container hover:bg-surface-container-high text-secondary inline-flex items-center justify-center"><span class="material-symbols-outlined text-[18px]">close</span></button>
        </div>
        <form method="GET" action="{{ route('admin.ci-work.attendance') }}" class="flex flex-col gap-space-md">
            <label>
                <span class="font-label-sm text-label-sm text-on-surface font-semibold">Tanggal Presensi</span>
                <input type="date" name="date" value="{{ $date }}" max="{{ today()->format('Y-m-d') }}"
                    class="mt-1 w-full h-11 px-space-md rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface font-label-md text-label-md focus:outline-none focus:ring-2 focus:ring-primary/40">
            </label>
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 h-10 rounded-lg bg-primary hover:bg-primary-container text-on-primary font-label-md text-label-md font-semibold transition-colors">
                    Terapkan Filter
                </button>
                <a href="{{ route('admin.ci-work.attendance') }}" class="h-10 px-space-md rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high inline-flex items-center font-label-md text-label-md font-semibold transition-colors">
                    Hari Ini
                </a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const ATT = {!! json_encode($rows->keyBy('id')) !!};
    const MAPMARKS = {!! json_encode($mapMarks) !!};
    window.__ATT = ATT;

    const statusChipMap = {
        online: { c: 'bg-emerald-500', t: 'text-emerald-700', b: 'bg-emerald-50' },
        delivering: { c: 'bg-amber-500', t: 'text-amber-700', b: 'bg-amber-50' },
        break: { c: 'bg-sky-500', t: 'text-sky-700', b: 'bg-sky-50' },
        offline: { c: 'bg-secondary', t: 'text-secondary', b: 'bg-surface-container' },
    };

    const label = (s) => s === 'online' ? 'Online • Siaga' : s === 'delivering' ? 'Sedang Mengantar' : s === 'break' ? 'Istirahat' : 'Offline';

    /* HUD update */
    window.selectCourier = function (id) {
        const m = MAPMARKS.find(x => x.id === id);
        const r = ATT[id];
        if (!m) return;
        const fuel = document.getElementById('hudCard');
        if (fuel) {
            const on = m.status === 'online' || m.status === 'delivering';
            document.getElementById('hudName').textContent = m.name;
            document.getElementById('hudSpeed').innerHTML = (m.speed !== null ? m.speed : '-') + ' <span class="text-[10px] text-secondary font-medium">km/j</span>';
            document.getElementById('hudBattery').textContent = (m.battery !== null ? m.battery + '%' : '-');
            document.getElementById('hudStatus').innerHTML = on
                ? '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulsate-dot"></span>Online • Bergerak'
                : '<span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>Offline';
            document.getElementById('hudPlace').textContent = m.city || '-';
            document.getElementById('hudTime').textContent = new Date().toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
        }
        document.querySelectorAll('.courier-marker').forEach(el => el.style.opacity = 0.45);
        const marker = document.querySelector('[data-marker="' + id + '"]');
        if (marker) marker.style.opacity = 1;
    };

    window.focusCourier = function (id) {
        document.getElementById('attendanceMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => selectCourier(id), 400);
    };
    window.focusCourierDetail = function () {};

    /* Detail modal */
    window.openDetail = function (id) {
        const r = ATT[id];
        if (!r) return;
        document.getElementById('d-initial').textContent = r.initial;
        document.getElementById('d-name').textContent = r.name;
        document.getElementById('d-id').textContent = 'ID: ' + r.id;
        document.getElementById('d-email').textContent = r.email;

        const st = statusChipMap[r.status] || statusChipMap.online;
        document.getElementById('d-status').className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-label-sm text-xs font-bold ' + st.b + ' ' + st.t;
        document.getElementById('d-status').innerHTML = '<span class="w-1.5 h-1.5 rounded-full ' + st.c + '"></span>' + (r.status === 'online' || r.status === 'delivering' ? r.statusLabel : (r.status === 'break' ? 'Istirahat' : 'Offline'));

        const statusTitle = r.status === 'break' ? 'Istirahat' : (r.status === 'delivering' ? 'Sedang Mengantar' : (r.status === 'online' ? 'Online • Siaga' : 'Offline'));
        const isOnline = r.status !== 'offline';
        const items = [
            { label: 'Status', icon: 'toggle_on', value: statusTitle, extra: isOnline ? 'Aplikasi ' + r.device + ' • Aktif' : null },
            { label: 'Telepon', icon: 'call', value: r.phone, extra: null },
            { label: 'Kendaraan', icon: 'two_wheeler', value: r.vehicle || '-', extra: null },
            { label: 'Drop Point', icon: 'storefront', value: r.dropPoint, extra: null },
            { label: 'Check-In', icon: 'schedule', value: r.attTime ? r.attTime + ' WIB' : 'Belum check-in', extra: r.attTime ? (r.late ? 'Terlambat' : 'Tepat Waktu') : null },
            { label: 'Durasi Shift', icon: 'hourglass', value: r.duration || '-', extra: null },
            { label: 'Kecepatan', icon: 'speed', value: r.speed !== null ? r.speed + ' km/j' : '-', extra: null },
            { label: 'Baterai', icon: 'battery_full', value: r.battery !== null ? r.battery + '%' : '-', extra: r.accuracy !== null ? 'Akurasi ±' + r.accuracy + ' m' : null },
        ];
        document.getElementById('detailGrid').innerHTML = items.map(it => `
            <div class="rounded-xl bg-surface-container-low p-3 flex flex-col gap-0.5">
                <span class="text-[11px] text-secondary font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">${it.icon}</span>${it.label}</span>
                <span class="font-medium text-sm">${it.value}</span>
                ${it.extra ? `<span class="text-[11px] font-semibold text-emerald-700">${it.extra}</span>` : ''}
            </div>`).join('');

        if (r.lat) {
            document.getElementById('d-address').textContent = r.address || 'Surabaya';
            document.getElementById('d-coords').textContent = r.lat.toFixed(5).replace('.', ',') + ', ' + r.lng.toFixed(5).replace('.', ',') + ' • Sinyal update ' + (r.lastSeen !== null && r.lastSeen <= 60 ? r.lastSeen + ' detik lalu' : '—');
            const pctX = Math.max(8, Math.min(92, (r.lng - 112.65) / 0.20 * 100));
            const pctY = Math.max(8, Math.min(92, (-7.20 - r.lat) / 0.15 * 100));
            document.getElementById('d-minimarker').style.setProperty('--mx', pctX + '%');
            document.getElementById('d-minimarker').style.setProperty('--my', pctY + '%');
        } else {
            document.getElementById('d-address').textContent = '-';
            document.getElementById('d-coords').textContent = 'GPS nonaktif';
        }

        const waNum = String(r.phone).replace(/\D/g, '').replace(/^0/, '62');
        document.getElementById('d-wa').href = 'https://wa.me/' + waNum;
        document.getElementById('d-call').href = 'tel:' + r.phone;
        document.getElementById('detailModal').classList.remove('hidden');
    };
    window.closeDetail = function () { document.getElementById('detailModal').classList.add('hidden'); };

    /* Date modal */
    window.openDateModal = function () { document.getElementById('dateModal').classList.remove('hidden'); };
    window.closeDateModal = function () { document.getElementById('dateModal').classList.add('hidden'); };

    /* Refresh */
    window.refreshSignal = function () {
        const btn = event && event.currentTarget ? event.currentTarget : null;
        const spinner = btn ? btn.querySelector('.material-symbols-outlined') : null;
        if (spinner) { spinner.classList.add('animate-spin'); btn.disabled = true; }
        setTimeout(() => window.location.reload(), 250);
    };

    /* Quick menu + chips + search */
    window.toggleMenu = function (e, id) {
        e.stopPropagation();
        const m = document.getElementById('qm-' + id);
        document.querySelectorAll('[id^="qm-"]').forEach(el => { if (el.id !== 'qm-' + id) { el.classList.add('hidden'); el.classList.remove('flex'); } });
        const wasHidden = m.classList.contains('hidden');
        m.classList.toggle('hidden', !wasHidden);
        m.classList.toggle('flex', wasHidden);
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
                b.classList.remove('active', 'text-on-surface', 'bg-surface-container-lowest', 'shadow-sm');
                b.classList.add('text-secondary');
            });
            this.classList.add('active', 'text-on-surface', 'bg-surface-container-lowest', 'shadow-sm');
            this.classList.remove('text-secondary');
            filterRows();
        });
    });

    document.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="qm-"]').forEach(el => { if (!el.contains(e.target)) { el.classList.add('hidden'); el.classList.remove('flex'); } });
    });

    /* Map controls */
    let mapScale = 1;
    window.mapZoom = function (d) {
        mapScale = Math.min(1.6, Math.max(0.6, mapScale + d * 0.2));
        const bg = document.getElementById('mapBg');
        if (bg) bg.style.transform = 'scale(' + mapScale + ')';
    };
    window.mapCenter = function () {
        mapScale = 1;
        const bg = document.getElementById('mapBg');
        if (bg) bg.style.transform = 'none';
    };
    window.mapFullscreen = function () {
        const el = document.getElementById('attendanceMap');
        if (!document.fullscreenElement) { el.requestFullscreen && el.requestFullscreen(); } else { document.exitFullscreen(); }
    };

    if (MAPMARKS.length > 0) selectCourier(MAPMARKS[0].id);
})();
</script>
@endpush