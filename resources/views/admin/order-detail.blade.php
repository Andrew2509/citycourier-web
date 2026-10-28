@extends('layouts.admin')

@section('title', 'Detail Pesanan #' . ($order->tracking_number ?? $order->order_number))

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders') }}" class="back-link" title="Kembali ke Daftar Pesanan">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="page-title">Detail Pesanan</h1>
                    @php
                        $st = strtolower($order->status ?? 'pending');
                        $badgeClass = match($st) {
                            'pending'              => 'badge-amber',
                            'assigned', 'matching' => 'badge-blue',
                            'picking_up', 'picked_up', 'in_transit', 'delivering' => 'badge-indigo',
                            'completed', 'delivered' => 'badge-green',
                            'cancelled', 'rejected'  => 'badge-red',
                            default                => 'badge-gray',
                        };
                        $statusLabel = match($st) {
                            'pending'    => 'Menunggu Kurir',
                            'assigned'   => 'Kurir Ditugaskan',
                            'matching'   => 'Mencari Kurir',
                            'picking_up' => 'Menuju Pickup',
                            'picked_up'  => 'Barang Diambil',
                            'delivering', 'in_transit' => 'Sedang Diantar',
                            'completed', 'delivered' => 'Selesai',
                            'cancelled'  => 'Dibatalkan',
                            'rejected'   => 'Ditolak',
                            default      => ucfirst($st),
                        };
                        $resi = $order->tracking_number ?? $order->order_number ?? ('ORD#' . $order->id);
                        $courier = $order->courier ?? $order->shipment?->courier ?? null;
                    @endphp
                    <span class="badge {{ $badgeClass }}">
                        <span class="badge-dot"></span>
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="page-subtitle font-mono">
                    Resi: <strong class="text-brand-700 font-semibold">{{ $resi }}</strong>
                    <span class="mx-1 text-slate-300">&bull;</span>
                    ID: #{{ $order->id }}
                </div>
            </div>
        </div>

        <div class="page-actions">
            @if($order->tracking_number)
            <a href="{{ route('tracking.show', $order->tracking_number) }}"
               target="_blank"
               rel="noopener"
               class="btn btn-primary"
               title="Buka Live Tracking Publik">
                <span class="material-symbols-outlined">map</span>
                Buka Live Tracking
            </a>
            @endif
            <button type="button"
                    onclick="window.print()"
                    class="btn btn-secondary"
                    title="Cetak Salinan Pesanan">
                <span class="material-symbols-outlined">print</span>
                Cetak Lembar Pesanan
            </button>
        </div>
    </div>

    {{-- Konten Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Kolom Kiri / Utama (2 Kolom) --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Rute Pengiriman --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Rute Pengiriman &amp; Kontak</div>
                        <div class="card-subtitle">Titik penjemputan dan tujuan pengiriman kurir</div>
                    </div>
                    <span class="badge badge-gray text-xs">
                        {{ ucfirst($order->vehicle_type ?? 'Motor') }} &bull; {{ $order->service_type ?? 'Instant' }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        {{-- Titik Pickup --}}
                        <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3.5 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-700">
                                <span class="material-symbols-outlined text-[16px]">upload</span>
                                Titik Penjemputan (Pickup)
                            </div>
                            <div>
                                <div class="text-[13.5px] font-semibold text-slate-900">
                                    {{ $order->sender_name ?? $order->customer_name ?? '-' }}
                                </div>
                                @php
                                    $senderPhone = $order->sender_phone ?? $order->customer_phone ?? null;
                                @endphp
                                @if($senderPhone)
                                <div class="text-xs text-slate-500 font-mono mt-0.5">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $senderPhone) }}"
                                       target="_blank"
                                       class="text-brand-700 hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">call</span>
                                        {{ $senderPhone }}
                                    </a>
                                </div>
                                @endif
                            </div>
                            <div class="text-xs text-slate-600 leading-relaxed border-t border-slate-200/80 pt-2">
                                {{ $order->pickup_address ?? '-' }}
                            </div>
                        </div>

                        {{-- Titik Dropoff --}}
                        <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3.5 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-700">
                                <span class="material-symbols-outlined text-[16px]">download</span>
                                Titik Penerima (Dropoff)
                            </div>
                            <div>
                                <div class="text-[13.5px] font-semibold text-slate-900">
                                    {{ $order->receiver_name ?? '-' }}
                                </div>
                                @if($order->receiver_phone)
                                <div class="text-xs text-slate-500 font-mono mt-0.5">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->receiver_phone) }}"
                                       target="_blank"
                                       class="text-brand-700 hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">call</span>
                                        {{ $order->receiver_phone }}
                                    </a>
                                </div>
                                @endif
                            </div>
                            <div class="text-xs text-slate-600 leading-relaxed border-t border-slate-200/80 pt-2">
                                {{ $order->delivery_address ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Detail Barang & Biaya --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Informasi Paket &amp; Rincian Tarif</div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <div class="stat-label">Jenis / Deskripsi Barang</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1 truncate" title="{{ $order->item_description ?? $order->package_description }}">
                                {{ $order->item_description ?? $order->package_description ?? 'Paket Standar' }}
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Berat Paket</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1">
                                {{ $order->weight ?? $order->package_weight ?? 1 }} kg
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Dimensi Paket</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1">
                                {{ $order->length ?? 0 }} x {{ $order->width ?? 0 }} x {{ $order->height ?? 0 }} cm
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Metode Pembayaran</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1 uppercase">
                                {{ $order->payment_method ?? 'TUNAI / COD' }}
                            </div>
                        </div>
                    </div>

                    {{-- Biaya --}}
                    <div class="pt-4 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Ongkos Kirim:</span>
                            <span class="font-mono font-medium">Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if(($order->insurance_fee ?? 0) > 0)
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Asuransi Perlindungan:</span>
                            <span class="font-mono font-medium">Rp {{ number_format($order->insurance_fee, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200 text-sm font-semibold text-slate-900">
                            <span>Total Tagihan:</span>
                            <span class="font-mono text-brand-700">Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Informasi Kurir yang Bertugas --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Kurir Penanggung Jawab</div>
                </div>
                <div class="card-body">
                    @if($courier)
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="avatar bg-brand-50 text-brand-700 text-sm font-bold">
                                {{ strtoupper(substr($courier->name ?? 'K', 0, 1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $courier->name }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5">{{ $courier->phone ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-green text-xs">Aktif Bertugas</span>
                            <div class="text-xs text-slate-400 mt-1">
                                Plat: <strong class="text-slate-700">{{ $courier->vehicle_plate ?? '-' }}</strong>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="empty-state py-6">
                        <span class="material-symbols-outlined text-[32px] text-slate-300">two_wheeler</span>
                        <div class="empty-title">Belum ada kurir yang ditugaskan</div>
                        <div class="empty-text">Pesanan masih dalam antrean penugasan atau pencarian kurir terdekat.</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan / Sidebar (1 Kolom) --}}
        <div class="space-y-5">
            {{-- QR Code Resi --}}
            <div class="card text-center p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
                    QR Code Resi Pesanan
                </div>
                <div class="inline-block p-2 rounded-lg border border-slate-200 bg-white shadow-sm">
                    @if($order->tracking_number)
                        <img src="{{ route('tracking.qrcode', $order->tracking_number) }}"
                             alt="QR Code Resi {{ $order->tracking_number }}"
                             class="w-40 h-40 object-contain mx-auto">
                    @else
                        <div class="w-40 h-40 bg-slate-100 flex items-center justify-center text-slate-400 text-xs">
                            No QR Code
                        </div>
                    @endif
                </div>
                <div class="mt-2 text-xs font-mono font-semibold text-slate-800">
                    {{ $resi }}
                </div>
                <p class="text-[11px] text-slate-400 mt-1">
                    Gunakan untuk verifikasi serah terima paket kurir
                </p>
                @if($order->tracking_number)
                <a href="{{ route('tracking.qrcode', $order->tracking_number) }}"
                   download="QR-{{ $order->tracking_number }}.png"
                   class="btn btn-sm btn-secondary w-full mt-3">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Unduh QR Code
                </a>
                @endif
            </div>

            {{-- Metadata Waktu --}}
            <div class="card p-4 space-y-2.5 text-xs">
                <div class="text-xs font-semibold text-slate-900 border-b border-slate-100 pb-2">
                    Riwayat Waktu Pesanan
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>Waktu Dibuat:</span>
                    <span class="font-mono text-slate-900">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
                <div class="flex items-center justify-between text-slate-600">
                    <span>Pembaruan Terakhir:</span>
                    <span class="font-mono text-slate-900">{{ $order->updated_at ? $order->updated_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
