@extends('layouts.admin')

@section('content')
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div class="flex items-start gap-4">
        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 shadow-sm shrink-0">
            <span class="material-symbols-outlined text-2xl">fingerprint</span>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Presensi & Pelacakan Kurir</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pemantauan real-time kehadiran dan lokasi kurir armada</p>
        </div>
    </div>
    <button onclick="window.location.reload()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-sm font-medium transition-colors self-start">
        <span class="material-symbols-outlined text-lg">refresh</span>
        Muat Ulang
    </button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 stat-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Kurir Aktif</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ $couriers->filter(fn($c) => $c->is_active)->count() }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-emerald-600">wifi</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Sedang siaga di lapangan</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 stat-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Terverifikasi</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ $couriers->filter(fn($c) => $c->is_verified)->count() }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-emerald-600">verified_user</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Lolos verifikasi dokumen</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 stat-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Dengan Lokasi</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ $couriers->filter(fn($c) => $c->latitude && $c->longitude)->count() }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600">location_on</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">GPS aktif di peta</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 stat-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-gray-500">Total Kurir</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ $couriers->total() }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-gray-500">group</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Seluruh armada terdaftar</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600">map</span>
            <h2 class="text-lg font-semibold text-gray-800">Peta Lokasi Kurir</h2>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            OpenStreetMap
        </span>
    </div>
    <div id="attendanceMap" class="rounded-lg border border-gray-200" style="height: 400px;"></div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Daftar Kehadiran</h2>
            <p class="text-sm text-gray-500">Rekapitulasi status kurir armada</p>
        </div>
        <span class="text-xs text-gray-400 font-mono">{{ $couriers->total() }} kurir</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-semibold">Kurir</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                    <th class="text-left px-5 py-3 font-semibold">Kendaraan</th>
                    <th class="text-left px-5 py-3 font-semibold">Lokasi Terakhir</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($couriers as $courier)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-emerald-600 text-lg">person</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">{{ $courier->user->name ?? '-' }}</p>
                                <p class="text-xs text-gray-400">{{ $courier->user->email ?? '' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        @if($courier->is_active && $courier->latitude && $courier->longitude)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Online
                            </span>
                        @elseif($courier->is_active)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                Aktif
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                Offline
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-gray-600">
                        @if($courier->vehicle_plate)
                            <span class="font-mono text-xs">{{ $courier->vehicle_plate }}</span>
                            <span class="text-gray-400 text-xs"> • {{ $courier->vehicle_type ?? '-' }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @if($courier->latitude && $courier->longitude)
                            <span class="text-xs text-gray-600 font-mono">{{ number_format((float)$courier->latitude, 5, ',', '.') }}, {{ number_format((float)$courier->longitude, 5, ',', '.') }}</span>
                        @else
                            <span class="text-gray-400 text-xs">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-gray-300 text-5xl block mb-2">group</span>
                        <p class="text-gray-400">Belum ada data kehadiran</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($couriers->hasPages())
    <div class="p-5 border-t border-gray-100">
        {{ $couriers->links() }}
    </div>
    @endif
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
            html: '<div style="background:#059669;width:34px;height:34px;border-radius:50%;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:white;"><span class="material-symbols-outlined" style="font-size:18px">delivery_dining</span></div>',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });

        const couriers = @json($couriers->getCollection()->map(fn($c) => [
            'name' => $c->user->name ?? 'Kurir',
            'phone' => $c->phone ?? '',
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'is_active' => $c->is_active,
        ])->filter(fn($c) => $c['latitude'] && $c['longitude'])->values());

        const markers = [];
        couriers.forEach(function (courier) {
            const m = L.marker([parseFloat(courier.latitude), parseFloat(courier.longitude)], { icon: courierIcon })
                .addTo(map)
                .bindPopup(
                    '<div style="font-family:Inter,sans-serif;padding:2px 0;">' +
                    '<strong style="font-size:13px;">' + courier.name + '</strong><br>' +
                    '<span style="font-size:12px;color:#6b7280;">' + (courier.phone || '') + '</span><br>' +
                    '<span style="font-size:12px;color:' + (courier.is_active ? '#059669' : '#6b7280') + ';font-weight:600;">' +
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