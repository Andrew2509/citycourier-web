@extends('layouts.admin')

@section('title', 'Detail Pengiriman #' . ($shipment->tracking_number ?? $shipment->shipment_number))

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.shipments.index') }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center justify-center transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-display text-xl font-bold text-slate-900">Detail Pengiriman</h1>
                    @php
                        $statusMap = [
                            'pending'    => ['label' => 'Pending',    'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                            'confirmed'  => ['label' => 'Confirmed',  'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                            'picked_up'  => ['label' => 'Picked Up',  'bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                            'in_transit' => ['label' => 'In Transit', 'bg' => 'bg-orange-50 text-brand-700 border-orange-200', 'dot' => 'bg-brand-500'],
                            'delivered'  => ['label' => 'Delivered',  'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                            'cancelled'  => ['label' => 'Cancelled',  'bg' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500'],
                        ];
                        $st = $statusMap[$shipment->status] ?? ['label' => ucfirst($shipment->status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $st['bg'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
                        {{ $st['label'] }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 font-mono mt-0.5">
                    Resi: <strong class="text-brand-600">{{ $shipment->tracking_number ?? $shipment->shipment_number }}</strong>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs shadow-sm transition-all">
                <span class="material-symbols-outlined text-[16px]">map</span>
                <span>Buka Live Tracking Web ↗</span>
            </a>
        </div>
    </div>

    {{-- Main 2-Column Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Details (2 Cols) --}}
        <div class="lg:col-span-2 space-y-6">

            <!-- Route Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">route</span>
                        </div>
                        <h2 class="font-display text-sm font-bold text-slate-900">Rute Pengiriman Langsung</h2>
                    </div>
                    <span class="text-xs font-mono font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg">
                        {{ $shipment->package_weight }} kg
                    </span>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <!-- Pengirim -->
                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 font-bold">
                            <span class="material-symbols-outlined text-[18px]">upload</span>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold uppercase tracking-wider text-[10px] text-emerald-700 block mb-0.5">Pengirim (Pickup)</span>
                            <span class="font-semibold text-slate-900 block text-sm">{{ $shipment->sender_name }}</span>
                            <span class="text-slate-500 font-mono text-[11px] block mt-0.5">{{ $shipment->sender_phone }}</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ $shipment->sender_address }}</p>
                            @if($shipment->origin_name)
                                <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="material-symbols-outlined text-[12px]">location_on</span>
                                    {{ $shipment->origin_name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Penerima -->
                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center shrink-0 font-bold">
                            <span class="material-symbols-outlined text-[18px]">download</span>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold uppercase tracking-wider text-[10px] text-brand-700 block mb-0.5">Penerima (Dropoff)</span>
                            <span class="font-semibold text-slate-900 block text-sm">{{ $shipment->receiver_name }}</span>
                            <span class="text-slate-500 font-mono text-[11px] block mt-0.5">{{ $shipment->receiver_phone }}</span>
                            <p class="text-slate-600 mt-1 leading-relaxed">{{ $shipment->receiver_address }}</p>
                            @if($shipment->destination_name)
                                <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-brand-50 text-brand-700 border border-brand-200">
                                    <span class="material-symbols-outlined text-[12px]">location_on</span>
                                    {{ $shipment->destination_name }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package & Service Breakdown -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 text-brand-600 border border-orange-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                        </div>
                        <h2 class="font-display text-sm font-bold text-slate-900">Rincian Paket &amp; Biaya</h2>
                    </div>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs mb-6">
                        <div>
                            <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Deskripsi</span>
                            <span class="font-semibold text-slate-800">{{ $shipment->package_description ?? 'Peralatan / Paket' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Berat Pengiriman</span>
                            <span class="font-semibold text-slate-800">{{ $shipment->package_weight }} kg</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Layanan</span>
                            <span class="font-semibold text-slate-800">{{ $shipment->courier_name ?? 'City Courier' }} &bull; {{ $shipment->courier_service ?? 'Instant' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Estimasi</span>
                            <span class="font-semibold text-slate-800">{{ $shipment->etd ? $shipment->etd . ' Menit/Jam' : 'Instant 30–60 mnt' }}</span>
                        </div>
                    </div>

                    <!-- Cost Breakdown List -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
                        <div class="flex justify-between text-slate-600">
                            <span>Ongkos Kirim Pokok</span>
                            <span class="font-mono font-semibold">Rp {{ number_format($shipment->shipping_cost, 0, ',', '.') }}</span>
                        </div>
                        @if($shipment->insurance)
                        <div class="flex justify-between text-slate-600">
                            <span>Asuransi Pengiriman</span>
                            <span class="font-mono font-semibold">Rp 2.500</span>
                        </div>
                        @endif
                        @if($shipment->wood_packing)
                        <div class="flex justify-between text-slate-600">
                            <span>Packing Tambahan</span>
                            <span class="font-mono font-semibold">Rp 50.000</span>
                        </div>
                        @endif
                        <div class="pt-2 border-t border-slate-200 flex justify-between items-center text-sm">
                            <span class="font-bold text-slate-900">Total Biaya</span>
                            <span class="font-mono font-extrabold text-brand-600 text-base">
                                Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking History Timeline -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">history</span>
                        </div>
                        <h2 class="font-display text-sm font-bold text-slate-900">Riwayat Perjalanan (Log)</h2>
                    </div>
                    <button type="button" onclick="document.getElementById('addLogModal').classList.remove('hidden')"
                            class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-600 text-white hover:bg-brand-700 transition-colors flex items-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>Tambah Log</span>
                    </button>
                </div>

                <div class="p-6">
                    @if($shipment->logs->count() > 0)
                        <div class="space-y-4">
                            @foreach($shipment->logs as $log)
                            <div class="flex items-start gap-3">
                                <div class="w-7 h-7 rounded-full {{ $log->status === 'delivered' ? 'bg-emerald-100 text-emerald-700' : 'bg-brand-100 text-brand-700' }} flex items-center justify-center shrink-0 mt-0.5">
                                    <span class="material-symbols-outlined text-[16px]">
                                        {{ $log->status === 'delivered' ? 'task_alt' : 'location_on' }}
                                    </span>
                                </div>
                                <div class="flex-1 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-slate-900">{{ $log->location }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $log->created_at->format('d M, H:i') }}</span>
                                    </div>
                                    <p class="text-slate-600 mt-0.5">{{ $log->description }}</p>
                                    <span class="inline-block mt-1 font-mono uppercase text-[10px] font-bold text-brand-600">
                                        {{ str_replace('_', ' ', $log->status) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-3xl mb-1 text-slate-300">history</span>
                            <p class="text-xs">Belum ada riwayat aktivitas logistik.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Right: Status & Actions (1 Col) --}}
        <div class="space-y-6">

            <!-- Resi & QR Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 text-center space-y-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono block">Resi Pengiriman</span>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 inline-block w-full">
                    <span class="font-mono text-base font-extrabold text-slate-900 tracking-wider block">
                        {{ $shipment->tracking_number ?? 'BELUM ADA RESI' }}
                    </span>
                    <span class="text-[10px] text-slate-400 block mt-0.5">ID: {{ $shipment->shipment_number }}</span>
                </div>

                @if($shipment->tracking_number)
                <div class="p-4 bg-white rounded-xl border border-slate-200/80 inline-block shadow-sm">
                    {!! \Milon\Barcode\Facades\DNS2DFacade::getBarcodeHTML($shipment->tracking_number, 'QRCODE', 5, 5) !!}
                    <a href="{{ route('tracking.qrcode', $shipment->tracking_number) }}" target="_blank" class="text-[11px] text-brand-600 hover:underline block mt-2 font-mono font-semibold">
                        Unduh QR Code (.png) ↗
                    </a>
                </div>
                @endif

                <!-- WhatsApp Notification Card -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 text-left space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-700">Notifikasi WA:</span>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $shipment->whatsapp_notification === 'sent' ? 'bg-emerald-100 text-emerald-800' : ($shipment->whatsapp_notification === 'failed' ? 'bg-red-100 text-red-800' : 'bg-slate-200 text-slate-700') }}">
                            {{ ucfirst($shipment->whatsapp_notification ?? 'unsent') }}
                        </span>
                    </div>

                    @if($shipment->whatsapp_sent_at)
                    <p class="text-[11px] text-slate-400 font-mono">
                        Terkirim: {{ $shipment->whatsapp_sent_at->format('d M Y, H:i') }}
                    </p>
                    @endif

                    <form method="POST" action="{{ route('admin.shipments.send-whatsapp', $shipment) }}" class="pt-1">
                        @csrf
                        <button type="submit" class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition-all flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">send</span>
                            <span>Kirim Ulang WA Resi</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Update Status Form -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-brand-600 text-[20px]">edit</span>
                    <h3 class="font-display text-sm font-bold text-slate-900">Perbarui Status</h3>
                </div>

                <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}" class="space-y-3">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Status Pengiriman</label>
                        <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 transition-all">
                            <option value="pending" {{ $shipment->status === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="confirmed" {{ $shipment->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                            <option value="picked_up" {{ $shipment->status === 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                            <option value="in_transit" {{ $shipment->status === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                            <option value="delivered" {{ $shipment->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="cancelled" {{ $shipment->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-2 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shadow-sm transition-all flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        <span>Simpan Status</span>
                    </button>
                </form>
            </div>

        </div>

    </div>
</div>

<!-- Modal Tambah Log -->
<div id="addLogModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-xl overflow-hidden animate-fade-in">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-display text-sm font-bold text-slate-900">Tambah Riwayat Pelacakan</h3>
            <button type="button" onclick="document.getElementById('addLogModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.shipments.logs.store', $shipment) }}" class="p-5 space-y-3">
            @csrf
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Lokasi / Hub</label>
                <input type="text" name="location" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600" placeholder="Contoh: Drop Point Waru Sidoarjo" required>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Keterangan Aktivitas</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600" placeholder="Paket sedang disortir / diantar kurir" required></textarea>
            </div>
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Status Milestone</label>
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600">
                    <option value="picked_up">Picked Up</option>
                    <option value="in_transit">In Transit</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>
            <div class="pt-3 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('addLogModal').classList.add('hidden')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Batal</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold shadow-sm">Simpan Log</button>
            </div>
        </form>
    </div>
</div>
@endsection
