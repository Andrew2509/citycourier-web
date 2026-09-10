@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <span>City-Work Operasional</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface font-semibold">Presensi & Pelacakan Kurir</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Presensi & Pelacakan Kurir</h1>
            <p class="font-body-md text-body-md text-secondary">Pemantauan real-time kehadiran dan lokasi kurir armada</p>
        </div>
        <button onclick="window.location.reload()" class="h-9 px-space-md rounded-lg bg-surface-container-lowest text-on-surface hover:bg-surface-container shadow-sm flex items-center gap-space-xs font-label-md text-label-md transition-colors">
            <span class="material-symbols-outlined text-[18px] text-secondary">refresh</span>
            <span>Muat Ulang</span>
        </button>
    </div>

    <!-- Stat Cards -->
    @php
        $activeCount = $couriers->filter(fn($c) => $c->is_active)->count();
        $verifiedCount = $couriers->filter(fn($c) => $c->is_verified)->count();
        $withLocationCount = $couriers->filter(fn($c) => $c->latitude && $c->longitude)->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex items-center justify-between hover:shadow-md transition-all">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Kurir Online</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $activeCount }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">Aktif Bertugas</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[22px]">wifi</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex items-center justify-between hover:shadow-md transition-all">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Terverifikasi</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $verifiedCount }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">Lolos Dokumen</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-700">
                <span class="material-symbols-outlined text-[22px]">verified_user</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex items-center justify-between hover:shadow-md transition-all">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Dengan Lokasi</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $withLocationCount }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">GPS Aktif</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed-variant">
                <span class="material-symbols-outlined text-[22px]">location_on</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-lg rounded-xl shadow-sm flex items-center justify-between hover:shadow-md transition-all">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Total Kurir</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg text-on-surface font-bold">{{ $couriers->total() }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">Terdaftar</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-surface-container-high flex items-center justify-center text-secondary">
                <span class="material-symbols-outlined text-[22px]">group</span>
            </div>
        </div>
    </div>

    <!-- Map Section -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">map</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Peta Lokasi Kurir</h2>
                    <span class="font-label-sm text-label-sm text-secondary">OpenStreetMap Real-time</span>
                </div>
            </div>
            <span class="inline-flex items-center gap-space-2xs px-space-xs py-space-2xs rounded-full font-label-sm text-label-sm bg-emerald-50 text-emerald-700 font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Live
            </span>
        </div>
        <div id="attendanceMap" style="height: 400px;"></div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">badge</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Daftar Kehadiran</h2>
                    <span class="font-label-sm text-label-sm text-secondary">{{ $couriers->total() }} kurir terdaftar</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kendaraan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Lokasi Terakhir</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($couriers as $courier)
                    <tr class="hover:bg-surface transition-colors">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-sm shrink-0">
                                    {{ strtoupper(substr($courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-medium text-on-surface">{{ $courier->user->name ?? '-' }}</p>
                                    <p class="text-xs text-secondary">{{ $courier->user->email ?? '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            @if($courier->is_active && $courier->latitude && $courier->longitude)
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Online
                                </span>
                            @elseif($courier->is_active)
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-blue-50 text-blue-700 font-label-sm text-xs font-bold">
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-surface-container text-secondary font-label-sm text-xs font-bold">
                                    Offline
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            @if($courier->vehicle_plate)
                                <span class="font-data-mono text-xs text-on-surface">{{ $courier->vehicle_plate }}</span>
                                <span class="text-secondary text-xs"> • {{ $courier->vehicle_type ?? '-' }}</span>
                            @else
                                <span class="text-secondary text-xs">-</span>
                            @endif
                        </td>
                        <td class="py-4 px-4">
                            @if($courier->latitude && $courier->longitude)
                                <span class="text-xs text-secondary font-data-mono">{{ number_format((float)$courier->latitude, 5, ',', '.') }}, {{ number_format((float)$courier->longitude, 5, ',', '.') }}</span>
                            @else
                                <span class="text-secondary text-xs">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">group</span>
                                <span class="font-body-sm text-body-sm">Belum ada data kehadiran</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($couriers->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $couriers->links() }}
        </div>
        @endif
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const map = L.map('attendanceMap').setView([-7.2575, 112.7521], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        const courierIcon = L.divIcon({
            className: 'custom-marker',
            html: '<div style="background:#f97316;width:34px;height:34px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:white;"><span class="material-symbols-outlined" style="font-size:18px">delivery_dining</span></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });

        const couriers = {!! json_encode($couriers->getCollection()->map(fn($c) => [
            'name' => $c->user->name ?? 'Kurir',
            'phone' => $c->phone ?? '',
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'is_active' => $c->is_active,
        ])->filter(fn($c) => $c['latitude'] && $c['longitude'])->values()) !!};

        const markers = [];
        couriers.forEach(function (courier) {
            const m = L.marker([parseFloat(courier.latitude), parseFloat(courier.longitude)], { icon: courierIcon })
                .addTo(map)
                .bindPopup(
                    '<div style="font-family:Inter,sans-serif;padding:2px 0;">' +
                    '<strong style="font-size:13px;">' + courier.name + '</strong><br>' +
                    '<span style="font-size:12px;color:#6b7280;">' + (courier.phone || '') + '</span><br>' +
                    '<span style="font-size:12px;color:' + (courier.is_active ? '#f97316' : '#6b7280') + ';font-weight:600;">' +
                    (courier.is_active ? '&#8226; Online' : '&#8226; Offline') + '</span>' +
                    '</div>'
                );
            markers.push(m);
        });

        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.3));
        }
    });
</script>
@endpush
@endsection
