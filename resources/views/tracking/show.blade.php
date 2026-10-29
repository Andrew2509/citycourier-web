<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Delivery Tracking - {{ $shipment->tracking_number }} | City Courier</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap">
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        #map { height: 280px; z-index: 10; width: 100%; border-radius: 0 0 1rem 1rem; }
        @media (min-width: 640px) {
            #map { height: 320px; }
        }
        .leaflet-control-zoom {
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            margin-left: 12px !important;
            margin-top: 12px !important;
        }
        .leaflet-control-zoom a {
            border-radius: 8px !important;
            margin-bottom: 4px !important;
            border: 1px solid #E2E8F0 !important;
            color: #475569 !important;
            background-color: #FFFFFF !important;
        }
        .qr-bracket-box {
            position: relative;
            padding: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .qr-bracket-box::before, .qr-bracket-box::after {
            content: '';
            position: absolute;
            width: 14px;
            height: 14px;
            pointer-events: none;
        }
        .qr-bracket-box::before {
            top: 0; left: 0;
            border-top: 2.5px solid #EA580C;
            border-left: 2.5px solid #EA580C;
            border-top-left-radius: 4px;
        }
        .qr-bracket-box::after {
            top: 0; right: 0;
            border-top: 2.5px solid #EA580C;
            border-right: 2.5px solid #EA580C;
            border-top-right-radius: 4px;
        }
        .qr-bracket-sub {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 14px;
            pointer-events: none;
        }
        .qr-bracket-sub::before {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 14px; height: 14px;
            border-bottom: 2.5px solid #EA580C;
            border-left: 2.5px solid #EA580C;
            border-bottom-left-radius: 4px;
        }
        .qr-bracket-sub::after {
            content: '';
            position: absolute;
            bottom: 0; right: 0;
            width: 14px; height: 14px;
            border-bottom: 2.5px solid #EA580C;
            border-right: 2.5px solid #EA580C;
            border-bottom-right-radius: 4px;
        }
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex flex-col justify-between">

    <div class="max-w-md sm:max-w-lg md:max-w-xl mx-auto w-full px-3.5 py-4 flex-1">

        <!-- Top Header Bar -->
        <header class="flex items-center justify-between pb-3.5 mb-2.5">
            <div class="flex items-center gap-3">
                <button onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" 
                        class="w-8 h-8 rounded-full flex items-center justify-center text-slate-700 hover:bg-slate-200/60 transition"
                        title="Kembali">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="flex flex-col items-start">
                    <img src="{{ asset('images/logo-city-courier.png') }}" alt="City Courier" class="h-7 w-auto max-w-[120px] object-contain">
                    <p class="text-[11px] text-slate-500 font-medium mt-0.5">Live Delivery Tracking</p>
                </div>
            </div>

            <!-- Live Status Indicator -->
            <div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                    Live
                </span>
            </div>
        </header>

        <!-- CARD 1: Status & Resi -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100 mb-3.5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-slate-500 font-medium">Nomor Resi</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span id="txt-resi" class="text-lg sm:text-xl font-bold font-mono text-slate-900 tracking-tight">{{ $shipment->tracking_number }}</span>
                        <button onclick="copyResi()" class="p-1 text-slate-400 hover:text-slate-600 rounded transition" title="Salin Resi">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Status Badge Pill -->
                <div>
                    <span id="badge-status" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-orange-50 text-orange-600 border border-orange-200/60 shadow-xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $displayStatus }}</span>
                        <svg class="w-3 h-3 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </span>
                </div>
            </div>

            <!-- Notice / Status Banner -->
            <div id="status-banner" class="mt-3.5 p-3 rounded-xl bg-[#FFF9F2] border border-orange-200/70">
                <div class="flex items-start gap-2.5">
                    <div class="w-6 h-6 rounded-full bg-orange-500 text-white flex items-center justify-center shrink-0 mt-0.5 text-xs shadow-xs">
                        @if($isCourierAssigned)
                            🏍️
                        @else
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-900 leading-tight" id="banner-title">
                            @if($isCourierAssigned)
                                Kurir Ditugaskan: {{ $tracking['courier_name'] ?? 'Kurir City Courier' }}
                            @else
                                Menunggu kurir menerima pesanan.
                            @endif
                        </p>
                        <p class="text-[11.5px] text-slate-600 mt-0.5 leading-normal" id="banner-subtitle">
                            @if($isCourierAssigned)
                                Kurir sedang dalam proses pengantaran paket Anda.
                            @else
                                Lokasi kurir akan muncul secara live setelah kurir mengambil tugas ini.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: Peta Rute & Posisi Kurir -->
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-100 mb-3.5 relative">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                    <span class="text-xs font-bold text-slate-800">Peta Rute & Posisi Kurir</span>
                </div>
                <div class="flex items-center gap-1 text-[11px] text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span id="last-updated">Diperbarui: {{ now()->format('g:i:s A') }}</span>
                </div>
            </div>

            <!-- Map View Container -->
            <div class="relative">
                <div id="map"></div>
                
                <!-- Floating Center Map Button -->
                <button onclick="centerMap()" 
                        class="absolute bottom-3 right-3 z-[400] bg-white/95 backdrop-blur-sm border border-slate-200/90 shadow-md rounded-full px-3 py-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition active:scale-95">
                    <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Pusatkan</span>
                </button>
            </div>
        </div>

        <!-- CARD 3: Informasi Pengiriman -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100 mb-3.5">
            <h2 class="text-sm font-bold text-slate-900 mb-3.5">Informasi Pengiriman</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pb-4">
                <!-- Lokasi Pickup -->
                <div class="relative pl-5">
                    <span class="absolute left-0 top-1 w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <p class="text-[11px] font-bold text-emerald-600 uppercase tracking-wide">Lokasi Pickup</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->sender_name }}</p>
                    <a href="tel:{{ $shipment->sender_phone }}" class="text-xs text-slate-500 hover:text-slate-800 flex items-center gap-1 mt-0.5">
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>({{ $shipment->sender_phone }})</span>
                    </a>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $shipment->sender_address }}</p>
                    <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-[11px] font-medium text-slate-700">
                        <span>📦 Jadwal:</span>
                        <span class="font-semibold">{{ $shipment->schedule_pickup_text ?? 'Sekarang' }}</span>
                    </div>
                </div>

                <!-- Lokasi Dropoff -->
                <div class="relative pl-5 border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0">
                    <span class="absolute left-0 top-1 w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    <p class="text-[11px] font-bold text-rose-500 uppercase tracking-wide">Lokasi Tujuan</p>
                    <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $shipment->receiver_name }}</p>
                    <a href="tel:{{ $shipment->receiver_phone }}" class="text-xs text-slate-500 hover:text-slate-800 flex items-center gap-1 mt-0.5">
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>({{ $shipment->receiver_phone }})</span>
                    </a>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ $shipment->receiver_address }}</p>
                </div>
            </div>

            <!-- 4-Box Specs Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 pt-3.5 border-t border-slate-100">
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100/80">
                    <div class="flex items-center gap-1 text-[11px] text-slate-500 font-medium">
                        <span>🛵</span>
                        <span>Kendaraan</span>
                    </div>
                    <p class="text-xs font-bold text-slate-900 mt-1">
                        {{ $shipment->vehicle_display_name == 'Mobil' ? 'Mobil' : 'Motor' }}
                    </p>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100/80">
                    <div class="flex items-center gap-1 text-[11px] text-slate-500 font-medium">
                        <span>⚖️</span>
                        <span>Berat Barang</span>
                    </div>
                    <p class="text-xs font-bold text-slate-900 mt-1">{{ number_format($shipment->package_weight ?? 1, 2) }} kg</p>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100/80">
                    <div class="flex items-center gap-1 text-[11px] text-slate-500 font-medium">
                        <span>💳</span>
                        <span>Pembayaran</span>
                    </div>
                    <p class="text-xs font-bold text-slate-900 mt-1 uppercase">{{ $shipment->payment_method ?? 'COD' }}</p>
                </div>
                <div class="p-2.5 rounded-xl bg-orange-50/80 border border-orange-200/60">
                    <div class="flex items-center gap-1 text-[11px] text-orange-600 font-medium">
                        <span>💰</span>
                        <span>Total Biaya</span>
                    </div>
                    <p class="text-xs font-bold text-orange-600 mt-1">{{ $shipment->formatted_total_cost }}</p>
                </div>
            </div>
        </div>

        <!-- CARD 4: Riwayat Perjalanan -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100 mb-3.5">
            <div class="flex items-center justify-between mb-3.5">
                <h2 class="text-sm font-bold text-slate-900">Riwayat Perjalanan</h2>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </div>

            <div id="timeline-list" class="space-y-3 relative pl-4 before:content-[''] before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                @if(isset($tracking['timeline']) && count($tracking['timeline']) > 0)
                    @foreach($tracking['timeline'] as $index => $log)
                        <div class="relative pl-3">
                            <span class="absolute -left-[14px] top-1.5 w-2.5 h-2.5 rounded-full {{ $index === 0 ? 'bg-orange-500 ring-4 ring-orange-100' : 'bg-slate-400' }}"></span>
                            <p class="text-xs font-bold text-slate-900 leading-tight">{{ $log['label'] ?? ucfirst($log['status'] ?? '') }}</p>
                            <p class="text-[11.5px] text-slate-600 mt-0.5">{{ $log['description'] ?? '-' }}</p>
                            <p class="text-[10.5px] text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d M Y, H:i') }}</p>
                        </div>
                    @endforeach
                @else
                    <div class="relative pl-3">
                        <span class="absolute -left-[14px] top-1.5 w-2.5 h-2.5 rounded-full bg-orange-500 ring-4 ring-orange-100"></span>
                        <p class="text-xs font-bold text-slate-900 leading-tight">Pesanan Berhasil Dibuat</p>
                        <p class="text-[11.5px] text-slate-600 mt-0.5">Menunggu konfirmasi kurir untuk penjemputan.</p>
                        <p class="text-[10.5px] text-slate-400 mt-0.5">{{ now()->format('d M Y, H:i') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- CARD 5: QR Code Pesanan & Action Buttons -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-100 mb-6">
            <div class="flex items-center gap-4">
                <!-- QR Code Box with Bracket Accents -->
                <div class="qr-bracket-box bg-white shrink-0">
                    <img src="{{ $shipment->qr_code_url }}" alt="QR Code Resi {{ $shipment->tracking_number }}" class="w-20 h-20 sm:w-24 sm:h-24 object-contain" />
                    <div class="qr-bracket-sub"></div>
                </div>

                <!-- QR Info -->
                <div class="flex-1 min-w-0">
                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight">QR Code Pesanan</h3>
                    <p class="text-[11.5px] text-slate-500 mt-1 leading-normal">Tunjukkan QR Code ini kepada kurir saat proses pickup.</p>
                    <span class="inline-block mt-2 px-2.5 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-xs font-mono font-bold text-slate-700">
                        {{ $shipment->tracking_number }}
                    </span>
                </div>
            </div>

            <!-- Two Action Buttons -->
            <div class="grid grid-cols-2 gap-3 mt-4 pt-3.5 border-t border-slate-100">
                <button onclick="contactSupport()" 
                        class="h-10 rounded-xl bg-white border border-slate-200/90 text-slate-700 hover:bg-slate-50 text-xs font-bold flex items-center justify-center gap-1.5 transition">
                    <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <span>Bantuan</span>
                </button>
                <button onclick="shareTracking()" 
                        class="h-10 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 transition shadow-xs">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <span>Bagikan Resi</span>
                </button>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-400 border-t border-slate-200/60 bg-white">
        &copy; {{ date('Y') }} City Courier &bull; Pengiriman Kilat Dalam Kota
    </footer>

    <!-- Leaflet & Realtime Polling Scripts -->
    <script>
        const resi = "{{ $shipment->tracking_number }}";
        const originLat = {{ $shipment->sender_latitude ?? -7.3498032 }};
        const originLng = {{ $shipment->sender_longitude ?? 112.7498118 }};
        const destLat   = {{ $shipment->receiver_latitude ?? -7.2975884 }};
        const destLng   = {{ $shipment->receiver_longitude ?? 112.7660130 }};
        const pickupLabel = "{{ addslashes($shipment->origin_name ?? 'Pickup (Wedoro)') }}";
        const destLabel   = "{{ addslashes($shipment->destination_name ?? 'Tujuan') }}";

        // Inisialisasi peta
        const map = L.map('map', { zoomControl: true }).setView([originLat, originLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        // Marker Icons matching the screenshot layout
        const pickupIcon = L.divIcon({
            html: `<div style="display:flex;flex-direction:column;align-items:center;">
                     <div style="background:#059669;color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:12px;box-shadow:0 2px 4px rgba(0,0,0,0.15);white-space:nowrap;margin-bottom:3px;">${pickupLabel}</div>
                     <div style="background-color:#10B981;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.25);">📍</div>
                   </div>`,
            className: '',
            iconSize: [100, 48],
            iconAnchor: [50, 46]
        });

        const dropoffIcon = L.divIcon({
            html: `<div style="display:flex;flex-direction:column;align-items:center;">
                     <div style="background:#DC2626;color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:12px;box-shadow:0 2px 4px rgba(0,0,0,0.15);white-space:nowrap;margin-bottom:3px;">${destLabel}</div>
                     <div style="background-color:#EF4444;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.25);">🏁</div>
                   </div>`,
            className: '',
            iconSize: [100, 48],
            iconAnchor: [50, 46]
        });

        const courierIcon = L.divIcon({
            html: `<div style="background-color:#EA580C;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:16px;border:2px solid white;box-shadow:0 0 0 5px rgba(234,88,12,0.25);">🛵</div>`,
            className: '',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });

        // Add Origin & Dest Markers
        const originMarker = L.marker([originLat, originLng], { icon: pickupIcon }).addTo(map);
        const destMarker = L.marker([destLat, destLng], { icon: dropoffIcon }).addTo(map);

        // Dashed Route line
        const latlngs = [[originLat, originLng], [destLat, destLng]];
        const polyline = L.polyline(latlngs, { color: '#EA580C', weight: 3.5, dashArray: '5, 8' }).addTo(map);
        map.fitBounds(polyline.getBounds(), { padding: [40, 40] });

        let courierMarker = null;

        @if($isCourierAssigned && !empty($tracking['courier_location']))
            const cLat = {{ $tracking['courier_location']['latitude'] }};
            const cLng = {{ $tracking['courier_location']['longitude'] }};
            courierMarker = L.marker([cLat, cLng], { icon: courierIcon }).addTo(map).bindPopup('<b>Posisi Kurir</b>');
        @endif

        function centerMap() {
            if (courierMarker) {
                const group = L.featureGroup([originMarker, destMarker, courierMarker]);
                map.fitBounds(group.getBounds(), { padding: [35, 35] });
            } else {
                map.fitBounds(polyline.getBounds(), { padding: [35, 35] });
            }
        }

        // Realtime Polling
        setInterval(async () => {
            try {
                const res = await fetch(`/track/${resi}/data`);
                if (!res.ok) return;
                const json = await res.json();
                if (json.success && json.tracking) {
                    const t = json.tracking;
                    const st = json.display_status || t.status_label;
                    document.getElementById('badge-status').querySelector('span').textContent = st;
                    document.getElementById('last-updated').textContent = 'Diperbarui: ' + new Date().toLocaleTimeString();

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

        function contactSupport() {
            window.open('https://wa.me/6281343323155?text=' + encodeURIComponent('Halo Customer Service City Courier, saya ingin bertanya terkait pesanan dengan nomor resi ' + resi), '_blank');
        }

        function shareTracking() {
            const shareText = `📦 Live Tracking City Courier\nNomor Resi: ${resi}\nStatus: {{ $displayStatus }}\n\nCek posisi pengiriman realtime di link berikut:\n${window.location.href}`;
            if (navigator.share) {
                navigator.share({
                    title: 'Live Tracking City Courier',
                    text: shareText,
                    url: window.location.href
                }).catch(() => {});
            } else {
                window.open('https://wa.me/?text=' + encodeURIComponent(shareText), '_blank');
            }
        }
    </script>
</body>
</html>
