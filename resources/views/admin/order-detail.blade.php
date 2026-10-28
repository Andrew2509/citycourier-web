@extends('layouts.admin')

@section('title', 'Detail Pesanan #' . ($order->tracking_number ?? $order->order_number))

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders') }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center justify-center transition-colors shadow-sm">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-display text-xl font-bold text-slate-900">Detail Pesanan</h1>
                    @php
                        $statusMap = [
                            'pending'    => ['label' => 'Pending',    'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                            'assigned'   => ['label' => 'Ditugaskan', 'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                            'picking_up' => ['label' => 'Penjemputan','bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                            'delivering' => ['label' => 'Diantar',    'bg' => 'bg-orange-50 text-brand-700 border-orange-200', 'dot' => 'bg-brand-500'],
                            'delivered'  => ['label' => 'Selesai',    'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                            'cancelled'  => ['label' => 'Dibatalkan', 'bg' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500'],
                        ];
                        $st = $statusMap[$order->status] ?? ['label' => ucfirst($order->status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $st['bg'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
                        {{ $st['label'] }}
                    </span>
                </div>
                <p class="text-xs text-slate-400 font-mono mt-0.5">
                    Resi: <strong class="text-brand-600">{{ $order->tracking_number ?? $order->order_number }}</strong>
                </p>
            </div>
        </div>

        @if($order->tracking_number)
        <div class="flex items-center gap-2">
            <a href="{{ route('tracking.show', $order->tracking_number) }}"
               target="_blank"
               rel="noopener"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs shadow-sm transition-all">
                <span class="material-symbols-outlined text-[16px]">map</span>
                <span>Buka Live Tracking Web ↗</span>
            </a>
        </div>
        @endif
    </div>

    {{-- Main 2-Column Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left Column (2 Cols) --}}
        <div class="lg:col-span-2 space-y-6">

            <!-- Detail Paket Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 text-brand-600 border border-orange-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[18px]">package_2</span>
                        </div>
                        <h2 class="font-display text-sm font-bold text-slate-900">Informasi Paket &amp; Transaksi</h2>
                    </div>
                    <span class="font-mono text-sm font-extrabold text-slate-900">
                        Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}
                    </span>
                </div>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs">
                    <div>
                        <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Nomor Resi</span>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ $order->tracking_number ?? $order->order_number }}</span>
                            <button onclick="navigator.clipboard.writeText('{{ $order->tracking_number ?? $order->order_number }}'); alert('Nomor resi disalin!')"
                                    class="text-slate-400 hover:text-brand-600" title="Salin Resi">
                                <span class="material-symbols-outlined text-[16px]">content_copy</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Berat Paket</span>
                        <span class="font-medium text-slate-800">{{ $order->package_weight ?? 1 }} kg</span>
                    </div>

                    <div>
                        <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Deskripsi Barang</span>
                        <span class="font-medium text-slate-800">{{ $order->package_description ?? 'Peralatan / Paket' }}</span>
                    </div>

                    <div>
                        <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Waktu Dibuat</span>
                        <span class="font-mono text-slate-600">{{ $order->created_at ? $order->created_at->format('d M Y, H:i:s') : '-' }}</span>
                    </div>

                    @if($order->notes)
                    <div class="sm:col-span-2 pt-2 border-t border-slate-100">
                        <span class="text-slate-400 font-bold uppercase tracking-wider block mb-1">Catatan Pengiriman</span>
                        <p class="text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200/80">{{ $order->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Alamat Pickup & Dropoff -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 border border-blue-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">route</span>
                    </div>
                    <h2 class="font-display text-sm font-bold text-slate-900">Rute Pengiriman</h2>
                </div>

                <div class="p-6 space-y-4 text-xs">
                    <!-- Pickup -->
                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 font-bold">
                            <span class="material-symbols-outlined text-[18px]">upload</span>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold uppercase tracking-wider text-[10px] text-emerald-700 block mb-0.5">Titik Penjemputan (Pickup)</span>
                            <span class="font-semibold text-slate-900 block text-sm">{{ $order->pickup_name ?? 'Lokasi Pengirim' }}</span>
                            <p class="text-slate-600 mt-0.5 leading-relaxed">{{ $order->pickup_address ?? '-' }}</p>
                        </div>
                    </div>

                    <!-- Dropoff -->
                    <div class="flex items-start gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center shrink-0 font-bold">
                            <span class="material-symbols-outlined text-[18px]">download</span>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold uppercase tracking-wider text-[10px] text-brand-700 block mb-0.5">Titik Tujuan (Dropoff)</span>
                            <span class="font-semibold text-slate-900 block text-sm">{{ $order->customer_name ?? 'Penerima' }}</span>
                            <p class="text-slate-600 mt-0.5 leading-relaxed">{{ $order->delivery_address ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline Status -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 border border-purple-100 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">timeline</span>
                    </div>
                    <h2 class="font-display text-sm font-bold text-slate-900">Perjalanan Status Pesanan</h2>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">check</span>
                            </div>
                            <div>
                                <span class="font-semibold text-slate-900 text-xs block">Pesanan Berhasil Dibuat</span>
                                <span class="text-[11px] text-slate-400 font-mono">{{ $order->created_at ? $order->created_at->format('d M Y, H:i') : '-' }}</span>
                            </div>
                        </div>

                        @if($order->picked_up_at)
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">check</span>
                            </div>
                            <div>
                                <span class="font-semibold text-slate-900 text-xs block">Paket Diambil oleh Kurir</span>
                                <span class="text-[11px] text-slate-400 font-mono">{{ $order->picked_up_at->format('d M Y, H:i') }}</span>
                            </div>
                        </div>
                        @endif

                        @if($order->delivered_at)
                        <div class="flex items-start gap-3">
                            <div class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                <span class="material-symbols-outlined text-[16px]">task_alt</span>
                            </div>
                            <div>
                                <span class="font-semibold text-slate-900 text-xs block">Paket Telah Tiba di Penerima</span>
                                <span class="text-[11px] text-slate-400 font-mono">{{ $order->delivered_at->format('d M Y, H:i') }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        {{-- Right Column (1 Col) --}}
        <div class="space-y-6">

            <!-- Customer Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono block">Customer / Penerima</span>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 font-bold flex items-center justify-center text-sm shrink-0">
                        {{ strtoupper(substr($order->customer_name ?? 'C', 0, 1)) }}
                    </div>
                    <div>
                        <span class="font-semibold text-slate-900 text-sm block">{{ $order->customer_name ?? 'Pelanggan' }}</span>
                        <a href="tel:{{ $order->customer_phone }}" class="text-xs text-brand-600 font-mono hover:underline flex items-center gap-1 mt-0.5">
                            <span class="material-symbols-outlined text-[14px]">phone</span>
                            <span>{{ $order->customer_phone ?? '-' }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Kurir Assignment Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono block">Kurir Lapangan</span>
                @if($order->courier && $order->courier->user)
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-600 text-white font-bold flex items-center justify-center text-sm shrink-0">
                        {{ strtoupper(substr($order->courier->user->name ?? 'K', 0, 1)) }}
                    </div>
                    <div>
                        <span class="font-semibold text-slate-900 text-sm block">{{ $order->courier->user->name }}</span>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->courier->phone ?? '') }}" target="_blank" class="text-xs text-emerald-600 font-mono hover:underline flex items-center gap-1 mt-0.5">
                            <span class="material-symbols-outlined text-[14px]">chat</span>
                            <span>{{ $order->courier->phone ?? '-' }}</span>
                        </a>
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                    <span>Kendaraan: <strong class="text-slate-900">{{ ucfirst($order->courier->vehicle_type ?? 'Motor') }}</strong></span>
                    <span class="font-mono text-slate-500">{{ $order->courier->vehicle_plate ?? '-' }}</span>
                </div>
                @else
                <div class="py-4 text-center text-slate-400">
                    <span class="material-symbols-outlined text-2xl mb-1 text-slate-300">person_off</span>
                    <p class="text-xs">Belum ada kurir ditugaskan.</p>
                </div>
                @endif
            </div>

            <!-- Quick Action Links -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-2.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 font-mono block mb-2">Aksi Pengiriman</span>

                @if($order->tracking_number)
                <a href="{{ route('tracking.show', $order->tracking_number) }}" target="_blank" rel="noopener"
                   class="w-full py-2.5 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-brand-600">map</span>
                    <span>Buka Live Tracking Peta</span>
                </a>
                <a href="{{ route('tracking.qrcode', $order->tracking_number) }}" target="_blank" rel="noopener"
                   class="w-full py-2.5 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-slate-600">qr_code_2</span>
                    <span>Lihat Gambar QR Code Resi</span>
                </a>
                @endif

                <button onclick="window.print()"
                   class="w-full py-2.5 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold border border-slate-200 transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-slate-600">print</span>
                    <span>Cetak Lembar Pesanan</span>
                </button>
            </div>

        </div>

    </div>
</div>
@endsection
