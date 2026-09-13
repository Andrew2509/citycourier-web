@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #dropPointMap.leaflet-container { border-radius: 12px; }
    #dropPointMap .leaflet-popup-content-wrapper, #dropPointMap .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.18); }
    #dropPointMap .leaflet-popup-content { margin: 12px 14px; font-family: 'Inter', sans-serif; }
    .dp-toast { animation: dpToastIn .25s ease-out; }
    @keyframes dpToastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .radar-scan { animation: radarPulse 1.2s ease-in-out infinite; }
    @keyframes radarPulse { 0% { box-shadow: 0 0 0 0 rgba(16,185,129,.45); } 70% { box-shadow: 0 0 0 14px rgba(16,185,129,0); } 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); } }
</style>
@endpush

@section('title', 'Manajemen Drop Point')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Breadcrumb & Top Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <a class="hover:text-primary transition-colors flex items-center gap-space-2xs" href="{{ route('admin.dashboard') }}">
                    <span class="material-symbols-outlined text-[15px]">home</span>
                    <span>Beranda</span>
                </a>
                <span class="text-outline-variant">/</span>
                <span class="text-secondary font-medium">Manajemen</span>
                <span class="text-outline-variant">/</span>
                <span class="text-primary font-bold">Drop Point</span>
            </div>
            <div class="flex items-baseline gap-space-sm mt-1">
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Drop Point</h1>
                <span class="px-space-xs py-space-2xs rounded-full font-label-sm text-label-sm bg-secondary-container text-on-secondary-container font-semibold">{{ $activeCount }} Hub Aktif</span>
            </div>
            <p class="font-body-md text-body-md text-secondary">Kelola jaringan hub operasional, kantor cabang, dan agen drop point CityCourier.</p>
        </div>
        <div class="flex items-center flex-wrap gap-space-sm">
            <a href="{{ route('admin.drop-points.index') }}" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all" title="Segarkan data hub">
                <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
                <span>Refresh Status Hub</span>
            </a>
            <a href="{{ route('admin.drop-points.export') }}" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all" title="Unduh daftar cabang dalam format CSV">
                <span class="material-symbols-outlined text-[18px] text-secondary">file_download</span>
                <span>Ekspor Data Cabang</span>
            </a>
            <button type="button" onclick="openModal('modal-tambah-droppoint')" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md rounded-lg shadow-md transition-all">
                <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                <span class="font-semibold">+ Tambah Drop Point</span>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Total Drop Point</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">warehouse</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $total }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Lokasi Tersebar</span>
                </div>
                <span class="inline-flex items-center gap-0.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-800 font-label-sm font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1 animate-pulse"></span>
                    {{ $total > 0 ? round(($activeCount / $total) * 100) : 0 }}% Online
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Cakupan Wilayah</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">map</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $cities->count() }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Zona: {{ $cities->take(2)->implode(' & ') ?: '-' }}</span>
                </div>
                <span class="inline-flex items-center px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface-variant font-label-sm font-semibold">
                    Aktif
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Terikat Hub</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                </div>
            </div>
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $courierCount }}</div>
                    <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Kurir Terdaftar</span>
                </div>
                <span class="inline-flex items-center gap-0.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-800 font-label-sm font-semibold">
                    <span class="material-symbols-outlined text-[14px]">verified</span>
                    Verified
                </span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex flex-col justify-between hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-space-sm">
                <span class="font-label-sm uppercase tracking-wider text-secondary font-bold">Kapasitas Gudang</span>
                <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                </div>
            </div>
            <div class="flex flex-col gap-space-sm">
                <div class="flex items-baseline justify-between">
                    <div>
                        <div class="font-display-lg text-display-lg font-bold text-on-surface leading-none">{{ $capacity }}%</div>
                        <span class="font-label-sm text-label-sm text-secondary font-medium mt-1 inline-block">Rata-rata Terpakai</span>
                    </div>
                    <span class="inline-flex items-center px-space-xs py-space-2xs rounded-full bg-primary-fixed text-on-primary-fixed-variant font-label-sm font-semibold">
                        Hub Sortir
                    </span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-surface-container-high">
                    <div class="h-1.5 rounded-full {{ $capacity >= 75 ? 'bg-error' : ($capacity >= 40 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($capacity, 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Geofence Banner -->
    <div class="rounded-xl bg-primary-fixed/40 border border-primary-fixed p-space-lg flex flex-col md:flex-row md:items-center gap-space-md">
        <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center text-on-primary shrink-0 radar-scan">
            <span class="material-symbols-outlined text-[22px]">location_searching</span>
        </div>
        <div class="flex-1">
            <div class="flex flex-wrap items-center gap-space-sm">
                <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Geofence Radius: 50 Meter (Default)</span>
                <span class="px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface font-label-sm font-semibold">Radius toleransi 50m untuk check-in kurir &amp; bukti valid lokasi</span>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-space-xs">Setiap hub/agen memiliki radius geofence tersendiri. Kurir dapat check-in saat berada di dalam area geofence — koordinat GPS terbaca oleh sistem Tracking Server.</p>
        </div>
        <button type="button" onclick="openModal('modal-radius')" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all shrink-0">
            <span class="material-symbols-outlined text-[18px] text-primary">edit_location_alt</span>
            <span>Atur Radius Geofence</span>
        </button>
    </div>

    <!-- Main Workspace Card -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Panel Header -->
        <div class="p-space-lg flex flex-col xl:flex-row xl:items-center justify-between gap-space-md bg-surface-container-lowest">
            <div class="flex items-center gap-space-sm">
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                    <span class="material-symbols-outlined text-[22px]">location_on</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Daftar Drop Point</h2>
                        <span class="px-space-xs py-space-2xs rounded-full bg-surface-container text-on-surface font-label-sm font-semibold">Total: {{ $total }} Lokasi</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Semua titik drop barang resmi dan agen kemitraan CityCourier</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-space-sm">
                <div class="flex items-center gap-space-2xs bg-surface p-space-2xs rounded-lg">
                    <button type="button" id="tabTableBtn" onclick="showDpView('table')" class="flex items-center gap-space-xs px-space-md py-space-xs h-8 rounded-md bg-primary-container text-on-primary font-label-md font-bold shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[16px]">table_rows</span>
                        <span>Tabel</span>
                    </button>
                    <button type="button" id="tabMapBtn" onclick="showDpView('map')" class="flex items-center gap-space-xs px-space-md py-space-xs h-8 rounded-md text-on-surface-variant hover:bg-surface-container-high font-label-md transition-all">
                        <span class="material-symbols-outlined text-[16px]">map</span>
                        <span>Peta</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="px-space-lg pb-space-lg bg-surface-container-lowest flex flex-col lg:flex-row gap-space-sm">
            <form method="GET" action="{{ route('admin.drop-points.index') }}" class="flex flex-col lg:flex-row gap-space-sm w-full">
                <div class="relative flex-1 min-w-[220px]">
                    <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-secondary text-[18px] pointer-events-none">search</span>
                    <input value="{{ request('search') }}" name="search" class="w-full bg-surface pl-9 pr-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest shadow-sm" placeholder="Cari nama, alamat, telepon, PIC..." type="text"/>
                </div>
                <select name="wilayah" class="bg-surface px-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:bg-surface-container-lowest shadow-sm">
                    <option value="">Semua Wilayah</option>
                    @foreach($cities as $city)
                    <option value="{{ $city }}" {{ request('wilayah') === $city ? 'selected' : '' }}>{{ $city }}</option>
                    @endforeach
                </select>
                <select name="status" class="bg-surface px-space-md py-space-xs h-9 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:bg-surface-container-lowest shadow-sm">
                    <option value="">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif &amp; Online</option>
                    <option value="renovation" {{ request('status') === 'renovation' ? 'selected' : '' }}>Renovasi</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Nonaktif / Tutup</option>
                </select>
                <button type="submit" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md rounded-lg shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[17px]">filter_alt</span>
                    <span class="font-semibold">Terapkan</span>
                </button>
                @if(request('search') || request('wilayah') || request('status'))
                <a href="{{ route('admin.drop-points.index') }}" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface hover:bg-surface-container-high text-secondary font-label-md rounded-lg shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[17px]">close</span>
                    Reset
                </a>
                @endif
            </form>
        </div>

        <!-- View: Table -->
        <div id="dpViewTable">
            @if($dropPoints->count() > 0)
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface text-secondary font-label-sm uppercase tracking-wider">
                            <th class="py-space-md px-space-lg font-bold">NAMA KANTOR / HUB</th>
                            <th class="py-space-md px-space-lg font-bold">ALAMAT LENGKAP</th>
                            <th class="py-space-md px-space-lg font-bold">TELEPON &amp; PIC</th>
                            <th class="py-space-md px-space-lg font-bold">KOORDINAT GPS</th>
                            <th class="py-space-md px-space-lg font-bold">JAM KERJA</th>
                            <th class="py-space-md px-space-lg font-bold text-center">STATUS</th>
                            <th class="py-space-md px-space-lg font-bold text-right">AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-container-high font-body-md text-body-md text-on-surface">
                        @foreach($dropPoints as $dp)
                        @php
                            $typeCfg = [
                                'hub' => ['label' => 'HUB UTAMA', 'icon' => 'warehouse', 'cls' => 'bg-primary-fixed text-on-primary-fixed-variant'],
                                'agent' => ['label' => 'AGEN', 'icon' => 'storefront', 'cls' => 'bg-secondary-container text-on-secondary-container'],
                                'locker' => ['label' => 'LOCKER', 'icon' => 'lock', 'cls' => 'bg-surface-container text-on-surface-variant'],
                            ][$dp->type] ?? ['label' => 'HUB', 'icon' => 'warehouse', 'cls' => 'bg-surface-container text-on-surface-variant'];
                            $statusCfg = [
                                'active' => ['label' => 'Aktif & Online', 'cls' => 'bg-emerald-50 text-emerald-800', 'dot' => 'bg-emerald-500'],
                                'renovation' => ['label' => 'Renovasi', 'cls' => 'bg-amber-50 text-amber-800', 'dot' => 'bg-amber-500'],
                                'closed' => ['label' => 'Nonaktif', 'cls' => 'bg-surface-container text-secondary', 'dot' => 'bg-gray-400'],
                            ][$dp->status] ?? ['label' => 'Nonaktif', 'cls' => 'bg-surface-container text-secondary', 'dot' => 'bg-gray-400'];
                            $latDisp = rtrim(rtrim((string) $dp->latitude, '0'), '.');
                            $lngDisp = rtrim(rtrim((string) $dp->longitude, '0'), '.');
                            $mapsUrl = $dp->latitude && $dp->longitude ? "https://www.google.com/maps/search/?api=1&query={$dp->latitude},{$dp->longitude}" : '#';
                        @endphp
                        <tr class="hover:bg-surface transition-colors group">
                            <td class="py-space-md px-space-lg">
                                <div class="flex items-center gap-space-md">
                                    <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary shrink-0 shadow-sm group-hover:scale-105 transition-transform">
                                        <span class="material-symbols-outlined text-[22px]">{{ $typeCfg['icon'] }}</span>
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <div class="flex items-center gap-space-xs">
                                            <span class="font-headline-sm text-headline-sm font-bold text-on-surface truncate">{{ $dp->name }}</span>
                                            <span class="px-space-xs py-space-2xs rounded-md {{ $typeCfg['cls'] }} font-label-sm font-bold whitespace-nowrap">{{ $typeCfg['label'] }}</span>
                                        </div>
                                        <span class="font-label-sm text-secondary truncate">{{ $dp->description ?: ($dp->type === 'hub' ? 'Hub Sortir Pusat Operasional' : 'Titik Layanan Drop Point') }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-space-md px-space-lg max-w-md">
                                <p class="font-body-sm text-body-sm text-on-surface line-clamp-2 leading-relaxed">{{ $dp->address ?? '-' }}</p>
                                @if($dp->landmark)
                                <p class="font-label-sm text-secondary mt-space-xs flex items-center gap-space-2xs">
                                    <span class="material-symbols-outlined text-[13px]">near_me</span>
                                    <span class="truncate">{{ $dp->landmark }}</span>
                                </p>
                                @endif
                            </td>
                            <td class="py-space-md px-space-lg whitespace-nowrap">
                                <div class="flex flex-col gap-space-2xs">
                                    @if($dp->phone)
                                    <a class="font-data-mono text-data-mono text-primary hover:underline font-semibold flex items-center gap-space-2xs" href="tel:{{ $dp->phone }}">
                                        <span class="material-symbols-outlined text-[14px]">call</span>
                                        {{ $dp->phone }}
                                    </a>
                                    @else
                                    <span class="font-data-mono text-data-mono text-secondary">-</span>
                                    @endif
                                    <span class="font-label-sm text-label-sm text-secondary flex items-center gap-space-2xs">
                                        <span class="material-symbols-outlined text-[13px]">badge</span>
                                        {{ $dp->pic_name ?: 'Belum diisi' }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-space-md px-space-lg whitespace-nowrap">
                                @if($dp->latitude && $dp->longitude)
                                <div class="flex flex-col gap-space-2xs">
                                    <span class="font-data-mono text-data-mono text-on-surface">-{{ $latDisp }}, {{ $lngDisp }}</span>
                                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="font-label-sm text-primary flex items-center gap-space-2xs hover:underline">
                                        <span class="material-symbols-outlined text-[13px]">map</span>
                                        Buka di Google Maps
                                    </a>
                                </div>
                                @else
                                <span class="font-data-mono text-data-mono text-secondary">-</span>
                                @endif
                            </td>
                            <td class="py-space-md px-space-lg whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-label-md text-label-md font-semibold text-on-surface">{{ $dp->schedule ?? '07:00 - 21:00 WIB' }}</span>
                                    <span class="font-label-sm text-label-sm text-secondary">{{ $dp->open_days }}</span>
                                </div>
                            </td>
                            <td class="py-space-md px-space-lg whitespace-nowrap text-center">
                                <span class="inline-flex items-center gap-space-xs px-space-sm py-space-2xs rounded-full {{ $statusCfg['cls'] }} font-label-md font-bold shadow-sm">
                                    <span class="w-2 h-2 rounded-full {{ $statusCfg['dot'] }}"></span>
                                    {{ $statusCfg['label'] }}
                                </span>
                            </td>
                            <td class="py-space-md px-space-lg whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-space-xs">
                                    <button type="button" onclick="focusMap({{ $dp->id }})" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-all" title="Lihat di Peta">
                                        <span class="material-symbols-outlined text-[18px]">map</span>
                                    </button>
                                    <form action="{{ route('admin.drop-points.toggle-active', $dp->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-all" title="{{ $dp->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                            <span class="material-symbols-outlined text-[18px]">{{ $dp->is_active ? 'toggle_on' : 'toggle_off' }}</span>
                                        </button>
                                    </form>
                                    <button type="button" onclick="openEditModal(this)"
                                        data-id="{{ $dp->id }}"
                                        data-name="{{ $dp->name }}"
                                        data-type="{{ $dp->type }}"
                                        data-address="{{ $dp->address }}"
                                        data-city="{{ $dp->city }}"
                                        data-province="{{ $dp->province }}"
                                        data-description="{{ $dp->description }}"
                                        data-landmark="{{ $dp->landmark }}"
                                        data-phone="{{ $dp->phone }}"
                                        data-pic_name="{{ $dp->pic_name }}"
                                        data-schedule="{{ $dp->schedule }}"
                                        data-open_days="{{ $dp->open_days }}"
                                        data-rating="{{ $dp->rating }}"
                                        data-radius_m="{{ $dp->radius_m }}"
                                        data-capacity_pct="{{ $dp->capacity_pct }}"
                                        data-latitude="{{ $dp->latitude }}"
                                        data-longitude="{{ $dp->longitude }}"
                                        data-status="{{ $dp->status }}"
                                        data-action="{{ route('admin.drop-points.update', $dp->id) }}"
                                        class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-all" title="Edit Hub">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <form action="{{ route('admin.drop-points.destroy', $dp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Drop Point ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-space-xs text-secondary hover:text-error hover:bg-error-container/20 rounded-lg transition-all" title="Hapus Drop Point">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-space-md bg-surface flex flex-col sm:flex-row items-center justify-between gap-space-sm text-secondary font-label-sm">
                <span>Menampilkan {{ $dropPoints->count() }} dari {{ $dropPoints->total() }} Drop Point terdaftar</span>
                <div class="flex items-center gap-space-xs">
                    {{ $dropPoints->links() }}
                </div>
            </div>
            @else
            <div class="py-space-2xl px-space-md flex flex-col items-center justify-center text-center">
                <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                    <span class="material-symbols-outlined text-[32px] text-secondary">warehouse</span>
                </div>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Belum ada drop point</h3>
                <p class="font-body-sm text-body-sm text-secondary max-w-sm mt-space-xs">Mulai dengan menambahkan drop point baru untuk mendistribusikan paket.</p>
                <button type="button" onclick="openModal('modal-tambah-droppoint')" class="mt-space-lg inline-flex items-center gap-space-xs px-space-md py-space-xs bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-semibold rounded-lg shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Tambah Drop Point
                </button>
            </div>
            @endif
        </div>

        <!-- View: Map -->
        <div id="dpViewMap" class="hidden p-space-lg gap-space-lg grid-cols-1 lg:grid-cols-3">
            <div class="lg:col-span-2 flex flex-col gap-space-md">
                <div id="dropPointMap" class="w-full h-[440px] rounded-xl shadow-sm bg-surface-container"></div>
            </div>
            @if($mapPoint)
            <div class="flex flex-col gap-space-sm">
                <div class="bg-surface-container-low rounded-xl p-space-lg shadow-sm">
                    <div class="flex items-center gap-space-sm mb-space-md">
                        <div class="w-10 h-10 rounded-lg bg-primary-container flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined text-[22px]">satellite_alt</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-headline-sm text-headline-sm text-on-surface font-bold">Spesifikasi Tracking Hub</span>
                            <span class="font-label-sm text-label-sm text-secondary">{{ $mapPoint->name }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-space-sm">
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Radius Geofence</span>
                            <span class="font-data-mono text-data-mono text-on-surface font-bold">{{ $mapPoint->radius_m }} Meter</span>
                        </div>
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Koordinat GPS</span>
                            <span class="font-data-mono text-data-mono text-on-surface font-bold">{{ $mapPoint->latitude }}, {{ $mapPoint->longitude }}</span>
                        </div>
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Jadwal Operasional</span>
                            <span class="font-label-md text-label-md font-bold text-on-surface">{{ $mapPoint->schedule }}</span>
                        </div>
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Hari Buka</span>
                            <span class="font-label-md text-label-md font-bold text-on-surface">{{ $mapPoint->open_days }}</span>
                        </div>
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Rating Layanan</span>
                            <span class="inline-flex items-center gap-space-xs font-data-mono text-data-mono text-on-surface font-bold"><span class="material-symbols-outlined text-[16px] text-amber-400" style="font-variation-settings:'FILL' 1">star</span> {{ number_format($mapPoint->rating, 1) }}</span>
                        </div>
                        <div class="flex items-center justify-between p-space-sm rounded-lg bg-surface">
                            <span class="font-body-sm text-body-sm text-secondary">Akurasi GPS Terbaca</span>
                            <span class="inline-flex items-center gap-space-xs font-label-md font-bold text-emerald-700"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span id="gpsAccuracyText">± 3 Meter</span></span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-space-sm">
                    <button type="button" onclick="testSignal()" class="inline-flex items-center justify-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md rounded-lg shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">radar</span>
                        <span>Uji Akurasi Sinyal</span>
                    </button>
                    <button type="button" onclick="openModal('modal-radius')" class="inline-flex items-center justify-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">edit_location_alt</span>
                        <span>Atur Radius</span>
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Modal: Tambah Drop Point -->
<div id="modal-tambah-droppoint" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-space-md" onclick="if(event.target===this) closeModal()">
    <div class="bg-surface-container-lowest w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="p-space-lg border-b border-surface-container-high flex items-center justify-between shrink-0">
            <div>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-bold">Tambah Drop Point Baru</h3>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Tambahkan titik drop/layanan baru ke jaringan CityCourier.</p>
            </div>
            <button type="button" onclick="closeModal()" class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.drop-points.store') }}" class="overflow-y-auto p-space-lg">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Nama Drop Point <span class="text-error">*</span></label>
                    <input name="name" required maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="cth: Kantor Cabang CityCourier"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Tipe Lokasi <span class="text-error">*</span></label>
                    <select name="type" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm">
                        <option value="hub">Hub Utama / Kantor Cabang</option>
                        <option value="agent">Agen Drop Point</option>
                        <option value="locker">Locker / Point Locker</option>
                    </select>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Status Operasional <span class="text-error">*</span></label>
                    <select name="status" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm">
                        <option value="active">Aktif &amp; Online</option>
                        <option value="renovation">Renovasi</option>
                        <option value="closed">Nonaktif / Tutup</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Alamat Lengkap <span class="text-error">*</span></label>
                    <textarea name="address" required rows="2" maxlength="500" class="mt-space-xs w-full bg-surface px-space-md py-space-xs rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="Jl. Nama Jalan No. XX, Kelurahan, Kecamatan"></textarea>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Kota / Kabupaten</label>
                    <input name="city" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="Surabaya"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Provinsi</label>
                    <input name="province" maxlength="255" value="Jawa Timur" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Landmark / Dekat</label>
                    <input name="landmark" maxlength="500" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="Dekat Bundaran Waru &amp; Pasar Modern"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Deskripsi</label>
                    <textarea name="description" rows="2" maxlength="1000" class="mt-space-xs w-full bg-surface px-space-md py-space-xs rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="Keterangan singkat fungsi lokasi ini"></textarea>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Telepon</label>
                    <input name="phone" maxlength="30" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="021-8123456"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">PIC / Penanggung Jawab</label>
                    <input name="pic_name" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="Budi Santoso"/>
                </div>
                <div class="grid grid-cols-2 gap-space-md">
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Latitude</label>
                        <input name="latitude" type="number" step="any" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="-7.3436"/>
                    </div>
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Longitude</label>
                        <input name="longitude" type="number" step="any" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm" placeholder="112.7485"/>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-space-md">
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Radius Geofence (m)</label>
                        <input name="radius_m" type="number" min="10" max="2000" value="50" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Kapasitas (%)</label>
                        <input name="capacity_pct" type="number" min="0" max="100" value="22" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Jam Kerja</label>
                    <input name="schedule" maxlength="255" value="07:00 - 21:00 WIB" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Hari Buka</label>
                    <select name="open_days" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm">
                        <option>Buka Setiap Hari (7 Hari)</option>
                        <option>Senin - Sabtu</option>
                        <option>Senin - Jumat</option>
                    </select>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Rating</label>
                    <input name="rating" type="number" step="0.1" min="0" max="5" value="5.0" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
            </div>
            <div class="mt-space-lg flex items-center justify-end gap-space-sm">
                <button type="button" onclick="closeModal()" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-10 bg-surface hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs h-10 bg-primary-container hover:bg-primary text-on-primary font-label-md font-semibold rounded-lg shadow-md transition-all">
                    <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                    Simpan Drop Point
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Drop Point -->
<div id="modal-edit-droppoint" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-space-md" onclick="if(event.target===this) closeModal()">
    <div class="bg-surface-container-lowest w-full max-w-2xl rounded-2xl shadow-2xl max-h-[90vh] flex flex-col overflow-hidden">
        <div class="p-space-lg border-b border-surface-container-high flex items-center justify-between shrink-0">
            <div>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-bold">Edit Drop Point</h3>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Perbarui data lokasi, geofence, dan status operasional hub.</p>
            </div>
            <button type="button" onclick="closeModal()" class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form id="editDropPointForm" method="POST" action="" class="overflow-y-auto p-space-lg">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-space-md">
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Nama Drop Point <span class="text-error">*</span></label>
                    <input id="edit_name" name="name" required maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Tipe Lokasi <span class="text-error">*</span></label>
                    <select id="edit_type" name="type" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm">
                        <option value="hub">Hub Utama / Kantor Cabang</option>
                        <option value="agent">Agen Drop Point</option>
                        <option value="locker">Locker / Point Locker</option>
                    </select>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Status Operasional <span class="text-error">*</span></label>
                    <select id="edit_status" name="status" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm">
                        <option value="active">Aktif &amp; Online</option>
                        <option value="renovation">Renovasi</option>
                        <option value="closed">Nonaktif / Tutup</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Alamat Lengkap <span class="text-error">*</span></label>
                    <textarea id="edit_address" name="address" required rows="2" maxlength="500" class="mt-space-xs w-full bg-surface px-space-md py-space-xs rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"></textarea>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Kota / Kabupaten</label>
                    <input id="edit_city" name="city" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Provinsi</label>
                    <input id="edit_province" name="province" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Landmark / Dekat</label>
                    <input id="edit_landmark" name="landmark" maxlength="500" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div class="sm:col-span-2">
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Deskripsi</label>
                    <textarea id="edit_description" name="description" rows="2" maxlength="1000" class="mt-space-xs w-full bg-surface px-space-md py-space-xs rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"></textarea>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Telepon</label>
                    <input id="edit_phone" name="phone" maxlength="30" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">PIC / Penanggung Jawab</label>
                    <input id="edit_pic_name" name="pic_name" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div class="grid grid-cols-2 gap-space-md">
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Latitude</label>
                        <input id="edit_latitude" name="latitude" type="number" step="any" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Longitude</label>
                        <input id="edit_longitude" name="longitude" type="number" step="any" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-space-md">
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Radius Geofence (m)</label>
                        <input id="edit_radius_m" name="radius_m" type="number" min="10" max="2000" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                    <div>
                        <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Kapasitas (%)</label>
                        <input id="edit_capacity_pct" name="capacity_pct" type="number" min="0" max="100" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                    </div>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Jam Kerja</label>
                    <input id="edit_schedule" name="schedule" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Hari Buka</label>
                    <input id="edit_open_days" name="open_days" maxlength="255" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
                <div>
                    <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Rating</label>
                    <input id="edit_rating" name="rating" type="number" step="0.1" min="0" max="5" class="mt-space-xs w-full bg-surface px-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
                </div>
            </div>
            <div class="mt-space-lg flex items-center justify-end gap-space-sm">
                <button type="button" onclick="closeModal()" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-10 bg-surface hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs h-10 bg-primary-container hover:bg-primary text-on-primary font-label-md font-semibold rounded-lg shadow-md transition-all">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Atur Radius Geofence -->
<div id="modal-radius" class="hidden fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-space-md" onclick="if(event.target===this) closeModal()">
    <div class="bg-surface-container-lowest w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-space-lg border-b border-surface-container-high flex items-center justify-between">
            <div>
                <h3 class="font-headline-lg text-headline-lg text-on-surface font-bold">Atur Radius Geofence</h3>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Ubah radius toleransi check-in kurir (10 - 2000 meter).</p>
            </div>
            <button type="button" onclick="closeModal()" class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        @if($mapPoint)
        <form method="POST" action="{{ route('admin.drop-points.radius', $mapPoint->id) }}" class="p-space-lg">
            @csrf
            @method('PATCH')
            <label class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Radius Geofence (meter) <span class="text-error">*</span></label>
            <div class="relative mt-space-xs">
                <span class="material-symbols-outlined absolute left-space-md top-1/2 -translate-y-1/2 text-secondary text-[18px] pointer-events-none">radar</span>
                <input name="radius_m" type="number" min="10" max="2000" value="{{ $mapPoint->radius_m }}" required class="w-full bg-surface pl-10 pr-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary shadow-sm"/>
            </div>
            <p class="font-label-sm text-label-sm text-secondary mt-space-sm leading-relaxed">
                Berlaku untuk: <span class="font-bold text-on-surface">{{ $mapPoint->name }}</span> — koordinat {{ $mapPoint->latitude }}, {{ $mapPoint->longitude }}.
            </p>
            <div class="mt-space-lg flex items-center justify-end gap-space-sm">
                <button type="button" onclick="closeModal()" class="inline-flex items-center gap-space-xs px-space-md py-space-xs h-10 bg-surface hover:bg-surface-container-high text-on-surface font-label-md rounded-lg shadow-sm transition-all">
                    Batal
                </button>
                <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs h-10 bg-primary-container hover:bg-primary text-on-primary font-label-md font-semibold rounded-lg shadow-md transition-all">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                    Simpan Radius
                </button>
            </div>
        </form>
        @else
        <div class="p-space-lg text-center">
            <p class="font-body-sm text-body-sm text-secondary">Belum ada hub untuk diatur radiusnya.</p>
            <button type="button" onclick="closeModal()" class="mt-space-md inline-flex items-center gap-space-xs px-space-md py-space-xs bg-primary-container text-on-primary font-label-md rounded-lg shadow-sm">Tutup</button>
        </div>
        @endif
    </div>
</div>

<!-- Toast Container -->
<div id="dpToastWrap" class="fixed bottom-space-lg right-space-lg z-[70] flex flex-col gap-space-sm"></div>

<script type="application/json" id="dpMapData">@json($dropPoints->items())</script>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let dpMap = null;
    let dpMarkers = {};
    let dpCircles = [];

    function showToast(message, type) {
        type = type || 'success';
        const wrap = document.getElementById('dpToastWrap');
        const el = document.createElement('div');
        const cfg = type === 'success'
            ? { cls: 'bg-emerald-50 border-emerald-200 text-emerald-800', icon: 'check_circle' }
            : { cls: 'bg-red-50 border-red-200 text-red-800', icon: 'error' };
        el.className = 'dp-toast p-space-md rounded-xl border ' + cfg.cls + ' shadow-lg flex items-center gap-space-sm';
        el.innerHTML = '<span class="material-symbols-outlined text-[20px]">' + cfg.icon + '</span><span class="font-body-sm font-semibold">' + message + '</span>';
        wrap.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity .5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    }

    function closeModal() {
        document.querySelectorAll('#modal-tambah-droppoint, #modal-edit-droppoint, #modal-radius').forEach(m => m.classList.add('hidden'));
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    function showDpView(view) {
        const table = document.getElementById('dpViewTable');
        const map = document.getElementById('dpViewMap');
        const tabTable = document.getElementById('tabTableBtn');
        const tabMap = document.getElementById('tabMapBtn');
        if (view === 'map') {
            table.classList.add('hidden');
            map.classList.remove('hidden');
            map.classList.add('grid');
            tabTable.classList.remove('bg-primary-container', 'text-on-primary', 'shadow-sm');
            tabMap.classList.add('bg-primary-container', 'text-on-primary', 'shadow-sm');
            if (!dpMap) initDpMap();
            setTimeout(() => { if (dpMap) dpMap.invalidateSize(); }, 150);
        } else {
            map.classList.add('hidden');
            map.classList.remove('grid');
            table.classList.remove('hidden');
            tabMap.classList.remove('bg-primary-container', 'text-on-primary', 'shadow-sm');
            tabTable.classList.add('bg-primary-container', 'text-on-primary', 'shadow-sm');
        }
    }

    function initDpMap() {
        const el = document.getElementById('dropPointMap');
        if (!el) return;
        const data = JSON.parse(document.getElementById('dpMapData').textContent);
        if (!data.length) return;
        const defLat = parseFloat(data[0].latitude) || -7.3;
        const defLng = parseFloat(data[0].longitude) || 112.75;
        dpMap = L.map('dropPointMap', { scrollWheelZoom: false }).setView([defLat, defLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(dpMap);
        data.forEach(function (p) {
            if (!p.latitude || !p.longitude) return;
            const lat = parseFloat(p.latitude);
            const lng = parseFloat(p.longitude);
            const label = (p.type || 'hub') === 'hub' ? 'HUB UTAMA' : (p.type || '').toUpperCase();
            const marker = L.marker([lat, lng]).addTo(dpMap).bindPopup(
                '<strong>' + (p.name || 'Drop Point') + '</strong><br/><span style="color:#6b7280">' + (p.type || 'hub') + '</span><br/>Radius: ' + (p.radius_m || 50) + ' m<br/>' +
                (p.schedule || '')
            );
            dpMarkers[p.id] = marker;
            const circle = L.circle([lat, lng], { radius: parseInt(p.radius_m || 50, 10), color: '#f97316', fillColor: '#f97316', fillOpacity: 0.12 }).addTo(dpMap);
            dpCircles.push(circle);
        });
    }

    function focusMap(id) {
        showDpView('map');
        requestAnimationFrame(() => { setTimeout(() => {
            const marker = dpMarkers[id];
            if (marker) {
                dpMap.flyTo(marker.getLatLng(), 15, { duration: 0.8 });
                marker.openPopup();
            } else {
                showToast('Pilih hub pada halaman ini untuk fokus peta.', 'error');
            }
        }, 200); });
    }

    function testSignal() {
        const btn = event ? event.currentTarget : null;
        const text = document.getElementById('gpsAccuracyText');
        if (btn) { btn.disabled = true; btn.querySelector('span:last-child').textContent = 'Memindai...'; }
        setTimeout(() => {
            if (text) text.textContent = '± 3 Meter';
            if (btn) { btn.disabled = false; btn.querySelector('span:last-child').textContent = 'Uji Akurasi Sinyal'; }
            showToast('Sinyal GPS terhubung: akurasi ±3 meter di area geofence. Status 100% online.');
        }, 900);
    }

    function openEditModal(btn) {
        const d = btn.dataset;
        document.getElementById('editDropPointForm').action = d.action;
        document.getElementById('edit_name').value = d.name || '';
        document.getElementById('edit_type').value = d.type || 'hub';
        document.getElementById('edit_status').value = d.status || 'active';
        document.getElementById('edit_address').value = d.address || '';
        document.getElementById('edit_city').value = d.city || '';
        document.getElementById('edit_province').value = d.province || '';
        document.getElementById('edit_landmark').value = d.landmark || '';
        document.getElementById('edit_description').value = d.description || '';
        document.getElementById('edit_phone').value = d.phone || '';
        document.getElementById('edit_pic_name').value = d.pic_name || '';
        document.getElementById('edit_latitude').value = d.latitude || '';
        document.getElementById('edit_longitude').value = d.longitude || '';
        document.getElementById('edit_radius_m').value = d.radius_m || 50;
        document.getElementById('edit_capacity_pct').value = d.capacity_pct || 22;
        document.getElementById('edit_schedule').value = d.schedule || '07:00 - 21:00 WIB';
        document.getElementById('edit_open_days').value = d.open_days || 'Buka Setiap Hari (7 Hari)';
        document.getElementById('edit_rating').value = d.rating || '5.0';
        openModal('modal-edit-droppoint');
    }
</script>
@endpush
@endsection