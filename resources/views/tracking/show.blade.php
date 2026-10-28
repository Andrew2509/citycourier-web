<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking - {{ $shipment->tracking_number }} | City Courier</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        #map { height: 320px; z-index: 10; }
        @media (min-width: 768px) {
            #map { height: 420px; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-orange-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    CC
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 leading-tight">City Courier</h1>
                    <p class="text-xs text-slate-500">Live Delivery Tracking</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Live
                </span>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-3xl mx-auto px-4 py-6 w-full flex-1">

        <!-- Tracking Header Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100">
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider">Nomor Resi</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span id="txt-resi" class="text-xl font-bold font-mono text-slate-900 tracking-wide">{{ $shipment->tracking_number }}</span>
                        <button onclick="copyResi()" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition" title="Salin Resi">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center">
                    <span id="badge-status" class="px-3.5 py-1.5 rounded-full text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        {{ $displayStatus }}
                    </span>
                </div>
            </div>

            <!-- Notice / Courier Status Banner -->
            <div id="courier-banner" class="mt-4 p-3.5 rounded-xl {{ $isCourierAssigned ? 'bg-blue-50 border border-blue-200 text-blue-900' : 'bg-amber-50 border border-amber-200 text-amber-900' }}">
                <div class="flex items-center gap-2.5">
                    @if($isCourierAssigned)
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                            🏍️
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-blue-700">Kurir Ditugaskan</p>
                            <p class="text-sm font-bold text-blue-900">{{ $tracking['courier_name'] ?? 'Kurir City Courier' }} ({{ $tracking['courier_phone'] ?? '-' }})</p>
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-full bg-amber-500/20 text-amber-700 flex items-center justify-center shrink-0">
                            ⏳
                        </div>
                        <div>
                            <p class="text-sm font-bold text-amber-900">Menunggu kurir menerima pesanan.</p>
                            <p class="text-xs text-amber-700">Lokasi kurir akan muncul secara live setelah kurir mengambil tugas ini.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Interactive Map Card -->
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-200 mb-5">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Peta Rute & Posisi Kurir</span>
                <span class="text-xs text-slate-400" id="last-updated">Memperbarui realtime...</span>
            </div>
            <div id="map"></div>
        </div>

        <!-- Detail Rute & Alamat -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 mb-5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4">Informasi Pengiriman</h2>

            <div class="relative pl-6 space-y-6 before:content-[''] before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                <!-- Lokasi Pickup -->
                <div class="relative">
                    <span class="absolute -left-6 top-0.5 w-4 h-4 rounded-full border-2 border-white bg-emerald-500 ring-2 ring-emerald-100"></span>
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-wide">Lokasi Pickup</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->sender_name }} ({{ $shipment->sender_phone }})</p>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $shipment->sender_address }}</p>
                    <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-xs font-semibold text-slate-700">
                        <span>📅 Jadwal:</span>
                        <span>{{ $shipment->schedule_pickup_text }}</span>
                    </div>
                </div>

                <!-- Lokasi Dropoff -->
                <div class="relative">
                    <span class="absolute -left-6 top-0.5 w-4 h-4 rounded-full border-2 border-white bg-rose-500 ring-2 ring-rose-100"></span>
                    <p class="text-xs font-bold text-rose-600 uppercase tracking-wide">Lokasi Tujuan</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->receiver_name }} ({{ $shipment->receiver_phone }})</p>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $shipment->receiver_address }}</p>
                </div>
            </div>

            <!-- Grid Info Tambahan -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-5 border-t border-slate-100">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase">Kendaraan</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">
                        {{ $shipment->vehicle_display_name == 'Mobil' ? '🚗 Mobil' : '🏍️ Motor' }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase">Berat Barang</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->package_weight }} kg</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase">Pembayaran</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->payment_method ?? 'COD' }}</p>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <p class="text-[11px] text-slate-400 font-semibold uppercase">Total Biaya</p>
                    <p class="text-sm font-bold text-orange-600 mt-0.5">{{ $shipment->formatted_total_cost }}</p>
                </div>
            </div>
        </div>

        <!-- Timeline Log Card -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200 mb-5">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4">Riwayat Perjalanan</h2>
            <div id="timeline-list" class="space-y-4">
                @if(isset($tracking['timeline']) && count($tracking['timeline']) > 0)
                    @foreach($tracking['timeline'] as $log)
                        <div class="flex items-start gap-3">
                            <div class="w-3 h-3 rounded-full bg-orange-500 mt-1.5 shrink-0 ring-4 ring-orange-100"></div>
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ $log['label'] ?? ucfirst($log['status'] ?? '') }}</p>
                                <p class="text-xs text-slate-600 mt-0.5">{{ $log['description'] ?? '-' }}</p>
                                <p class="text-[11px] text-slate-400 mt-1">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-xs text-slate-400">Belum ada riwayat tercatat.</p>
                @endif
            </div>
        </div>

        <!-- QR Code Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 text-center mb-8">
            <h3 class="text-sm font-bold text-slate-900 mb-1">QR Code Pesanan</h3>
            <p class="text-xs text-slate-500 mb-4">Tunjukkan QR Code ini kepada kurir saat proses pickup</p>
            <div class="inline-block p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
                <img src="{{ $shipment->qr_code_url }}" alt="QR Code Resi {{ $shipment->tracking_number }}" class="w-48 h-48 mx-auto object-contain" />
                <p class="text-sm font-bold font-mono text-slate-900 mt-2">{{ $shipment->tracking_number }}</p>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        &copy; {{ date('Y') }} City Courier • Pengiriman Kilat Dalam Kota
    </footer>

    <!-- Leaflet & Polling Scripts -->
    <script>
        const resi = "{{ $shipment->tracking_number }}";
        const originLat = {{ $shipment->sender_latitude ?? -7.3498032 }};
        const originLng = {{ $shipment->sender_longitude ?? 112.7498118 }};
        const destLat   = {{ $shipment->receiver_latitude ?? -7.2975884 }};
        const destLng   = {{ $shipment->receiver_longitude ?? 112.7660130 }};

        // Inisialisasi peta
        const map = L.map('map', { zoomControl: true }).setView([originLat, originLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Marker Icons
        const pickupIcon = L.divIcon({
            html: '<div style="background-color:#10B981;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;box-shadow:0 2px 6px rgba(0,0,0,0.3);border:2px solid white;">📍</div>',
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        const dropoffIcon = L.divIcon({
            html: '<div style="background-color:#F43F5E;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;box-shadow:0 2px 6px rgba(0,0,0,0.3);border:2px solid white;">🏁</div>',
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        const courierIcon = L.divIcon({
            html: '<div style="background-color:#EA580C;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;box-shadow:0 0 0 6px rgba(234,88,12,0.25);border:2px solid white;">🏍️</div>',
            className: '',
            iconSize: [38, 38],
            iconAnchor: [19, 19]
        });

        // Add Origin & Dest Markers
        const originMarker = L.marker([originLat, originLng], { icon: pickupIcon }).addTo(map).bindPopup('<b>Lokasi Pickup</b><br>{{ addslashes($shipment->sender_address) }}');
        const destMarker = L.marker([destLat, destLng], { icon: dropoffIcon }).addTo(map).bindPopup('<b>Lokasi Tujuan</b><br>{{ addslashes($shipment->receiver_address) }}');

        // Route line
        const latlngs = [[originLat, originLng], [destLat, destLng]];
        const polyline = L.polyline(latlngs, { color: '#EA580C', weight: 4, dashArray: '6, 8' }).addTo(map);
        map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

        let courierMarker = null;

        @if($isCourierAssigned && !empty($tracking['courier_location']))
            const cLat = {{ $tracking['courier_location']['latitude'] }};
            const cLng = {{ $tracking['courier_location']['longitude'] }};
            courierMarker = L.marker([cLat, cLng], { icon: courierIcon }).addTo(map).bindPopup('<b>Posisi Kurir</b>');
        @endif

        // Polling status dan lokasi kurir setiap 10 detik
        setInterval(async () => {
            try {
                const res = await fetch(`/track/${resi}/data`);
                if (!res.ok) return;
                const json = await res.json();
                if (json.success && json.tracking) {
                    const t = json.tracking;
                    document.getElementById('badge-status').textContent = json.display_status || t.status_label;
                    document.getElementById('last-updated').textContent = 'Diperbarui: ' + new Date().toLocaleTimeString();

                    // Kurir location check
                    if (t.courier_location && t.courier_location.latitude && t.courier_location.longitude) {
                        const newLat = t.courier_location.latitude;
                        const newLng = t.courier_location.longitude;
                        if (!courierMarker) {
                            courierMarker = L.marker([newLat, newLng], { icon: courierIcon }).addTo(map).bindPopup('<b>Posisi Kurir</b>');
                        } else {
                            courierMarker.setLatLng([newLat, newLng]);
                        }
                    }
                }
            } catch (e) {
                console.error('Polling error', e);
            }
        }, 10000);

        function copyResi() {
            navigator.clipboard.writeText(resi);
            alert('Nomor Resi ' + resi + ' disalin ke clipboard!');
        }
    </script>
</body>
</html>

