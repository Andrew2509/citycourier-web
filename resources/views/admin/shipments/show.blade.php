@extends('layouts.admin')

@section('title', 'Detail Pengiriman #' . ($shipment->tracking_number ?? $shipment->shipment_number))

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.shipments.index') }}" class="back-link" title="Kembali ke Daftar Pengiriman">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="page-title">Detail Pengiriman</h1>
                    @php
                        $stKey = strtolower($shipment->status ?? 'pending');
                        $badgeClass = match($stKey) {
                            'pending', 'draft'        => 'badge-amber',
                            'confirmed', 'assigned'   => 'badge-blue',
                            'picked_up', 'in_transit' => 'badge-indigo',
                            'delivered', 'completed'  => 'badge-green',
                            'cancelled', 'rejected'   => 'badge-red',
                            default                   => 'badge-gray',
                        };
                        $statusLabel = match($stKey) {
                            'pending'    => 'Pending',
                            'confirmed'  => 'Confirmed',
                            'assigned'   => 'Assigned',
                            'picked_up'  => 'Picked Up',
                            'in_transit' => 'In Transit',
                            'delivered'  => 'Delivered',
                            'completed'  => 'Delivered',
                            'cancelled'  => 'Cancelled',
                            'rejected'   => 'Rejected',
                            default      => ucfirst($stKey),
                        };
                        $trackingNum = $shipment->tracking_number ?? $shipment->shipment_number;
                    @endphp
                    <span class="badge {{ $badgeClass }}">
                        <span class="badge-dot"></span>
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="page-subtitle font-mono">
                    Resi: <strong class="text-brand-700 font-semibold">{{ $trackingNum }}</strong>
                    <span class="mx-1 text-slate-300">&bull;</span>
                    No. Pengiriman: #{{ $shipment->shipment_number }}
                </div>
            </div>
        </div>

        <div class="page-actions">
            <a href="{{ route('tracking.show', $trackingNum) }}"
               target="_blank"
               rel="noopener"
               class="btn btn-primary"
               title="Buka Halaman Live Tracking Publik">
                <span class="material-symbols-outlined">map</span>
                Buka Live Tracking
            </a>
        </div>
    </div>

    {{-- Grid Konten Utama --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Kolom Kiri / Utama (2 Kolom) --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Rute Pengiriman (Pickup & Dropoff) --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Rute &amp; Kontak Pengiriman</div>
                        <div class="card-subtitle">Informasi penjemputan dan tujuan paket</div>
                    </div>
                    <span class="badge badge-gray text-xs">
                        {{ ucfirst($shipment->vehicle_type ?? 'Motor') }} &bull; {{ $shipment->courier_service ?? 'Instant' }}
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
                                <div class="text-[13.5px] font-semibold text-slate-900">{{ $shipment->sender_name ?? '-' }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $shipment->sender_phone) }}"
                                       target="_blank"
                                       class="text-brand-700 hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">call</span>
                                        {{ $shipment->sender_phone ?? '-' }}
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs text-slate-600 leading-relaxed border-t border-slate-200/80 pt-2">
                                {{ $shipment->sender_address ?? '-' }}
                            </div>
                        </div>

                        {{-- Titik Dropoff --}}
                        <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3.5 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-700">
                                <span class="material-symbols-outlined text-[16px]">download</span>
                                Titik Penerima (Dropoff)
                            </div>
                            <div>
                                <div class="text-[13.5px] font-semibold text-slate-900">{{ $shipment->receiver_name ?? '-' }}</div>
                                <div class="text-xs text-slate-500 font-mono mt-0.5">
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $shipment->receiver_phone) }}"
                                       target="_blank"
                                       class="text-brand-700 hover:underline inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">call</span>
                                        {{ $shipment->receiver_phone ?? '-' }}
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs text-slate-600 leading-relaxed border-t border-slate-200/80 pt-2">
                                {{ $shipment->receiver_address ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Detail Barang & Biaya --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Spesifikasi Paket &amp; Rincian Biaya</div>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <div class="stat-label">Deskripsi Barang</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1 truncate" title="{{ $shipment->package_description }}">
                                {{ $shipment->package_description ?? 'Paket Standar' }}
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Berat Aktual</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1">
                                {{ $shipment->package_weight ?? 1 }} kg
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Dimensi (P x L x T)</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1">
                                {{ $shipment->length ?? 0 }} x {{ $shipment->width ?? 0 }} x {{ $shipment->height ?? 0 }} cm
                            </div>
                        </div>
                        <div>
                            <div class="stat-label">Berat Volumetrik</div>
                            <div class="text-xs font-semibold text-slate-900 mt-1">
                                {{ $shipment->volumetric_weight ?? $shipment->package_weight ?? 1 }} kg
                            </div>
                        </div>
                    </div>

                    {{-- Rincian Biaya --}}
                    <div class="pt-4 space-y-2 text-xs">
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Ongkos Kirim Pokok:</span>
                            <span class="font-mono font-medium">Rp {{ number_format($shipment->shipping_cost ?? 0, 0, ',', '.') }}</span>
                        </div>
                        @if(($shipment->insurance_cost ?? 0) > 0)
                        <div class="flex items-center justify-between text-slate-600">
                            <span>Biaya Asuransi:</span>
                            <span class="font-mono font-medium">Rp {{ number_format($shipment->insurance_cost, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <div class="flex items-center justify-between pt-2 border-t border-slate-200 text-sm font-semibold text-slate-900">
                            <span>Total Pembayaran:</span>
                            <span class="font-mono text-brand-700">Rp {{ number_format($shipment->total_cost ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Riwayat Tracking & Log Perjalanan --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Riwayat Perjalanan (Tracking Logs)</div>
                        <div class="card-subtitle">Log aktivitas pergerakan kurir dan pembaruan resi</div>
                    </div>
                    <button type="button"
                            onclick="openLogModal()"
                            class="btn btn-sm btn-secondary">
                        <span class="material-symbols-outlined">add</span>
                        Tambah Log
                    </button>
                </div>
                <div class="card-body">
                    @if(isset($shipment->trackingLogs) && $shipment->trackingLogs->count() > 0)
                    <div class="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                        @foreach($shipment->trackingLogs->sortByDesc('created_at') as $log)
                        <div class="relative group">
                            <span class="absolute -left-6 top-1 h-3 w-3 rounded-full border-2 border-white bg-brand-600 ring-2 ring-brand-100"></span>
                            <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                                <span class="text-xs font-semibold text-slate-900 uppercase tracking-wide">
                                    {{ $log->status }}
                                    @if($log->location)
                                        <span class="text-slate-400 font-normal">({{ $log->location }})</span>
                                    @endif
                                </span>
                                <span class="text-[11px] text-slate-400 font-mono">
                                    {{ $log->created_at ? $log->created_at->format('d M Y, H:i') : '-' }} WIB
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5">
                                {{ $log->description ?? $log->notes ?? '-' }}
                            </p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="empty-state py-8">
                        <span class="material-symbols-outlined">route</span>
                        <div class="empty-title">Belum ada riwayat pergerakan</div>
                        <div class="empty-text">Klik tombol "Tambah Log" untuk mencatat checkpoint kurir.</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan / Sidebar (1 Kolom) --}}
        <div class="space-y-5">
            {{-- QR Code Resi Resmi --}}
            <div class="card text-center p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
                    QR Code Resi Pengiriman
                </div>
                <div class="inline-block p-2 rounded-lg border border-slate-200 bg-white shadow-sm">
                    @if($shipment->tracking_number)
                        <img src="{{ route('tracking.qrcode', $shipment->tracking_number) }}"
                             alt="QR Code Resi {{ $shipment->tracking_number }}"
                             class="w-40 h-40 object-contain mx-auto">
                    @else
                        <div class="w-40 h-40 bg-slate-100 flex items-center justify-center text-slate-400 text-xs">
                            No QR
                        </div>
                    @endif
                </div>
                <div class="mt-2 text-xs font-mono font-semibold text-slate-800">
                    {{ $trackingNum }}
                </div>
                <p class="text-[11px] text-slate-400 mt-1">
                    Pindai untuk membuka live tracking kurir di smartphone
                </p>
                @if($shipment->tracking_number)
                <a href="{{ route('tracking.qrcode', $shipment->tracking_number) }}"
                   download="QR-{{ $shipment->tracking_number }}.png"
                   class="btn btn-sm btn-secondary w-full mt-3">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Unduh QR Code
                </a>
                @endif
            </div>

            {{-- Notifikasi WhatsApp Fonnte --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Notifikasi WhatsApp (Fonnte)</div>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Status Notifikasi:</span>
                        @if($shipment->whatsapp_sent ?? false)
                            <span class="badge badge-green">
                                <span class="badge-dot"></span>
                                Terkirim
                            </span>
                        @else
                            <span class="badge badge-gray">
                                <span class="badge-dot"></span>
                                Belum Terkirim
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">Nomor Pengirim (WA):</span>
                        <span class="font-mono font-semibold text-slate-800">{{ $shipment->sender_phone ?? '-' }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.shipments.send-whatsapp', $shipment) }}" class="pt-2">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-dark w-full">
                            <span class="material-symbols-outlined">send</span>
                            Kirim Ulang WA Resi
                        </button>
                    </form>
                    <p class="text-[11px] text-slate-400 leading-tight">
                        Mengirimkan rincian pesanan, tautan live tracking, dan QR Code ke pengirim via API Fonnte.
                    </p>
                </div>
            </div>

            {{-- Form Update Status Pengiriman --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Perbarui Status</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}" class="space-y-3">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="form-label">Status Pengiriman</label>
                            <select name="status" class="form-select">
                                <option value="pending" {{ $shipment->status === 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
                                <option value="confirmed" {{ $shipment->status === 'confirmed' ? 'selected' : '' }}>Confirmed (Dikonfirmasi)</option>
                                <option value="picked_up" {{ $shipment->status === 'picked_up' ? 'selected' : '' }}>Picked Up (Diambil Kurir)</option>
                                <option value="in_transit" {{ $shipment->status === 'in_transit' ? 'selected' : '' }}>In Transit (Dalam Perjalanan)</option>
                                <option value="delivered" {{ $shipment->status === 'delivered' ? 'selected' : '' }}>Delivered (Terkirim)</option>
                                <option value="cancelled" {{ $shipment->status === 'cancelled' ? 'selected' : '' }}>Cancelled (Dibatalkan)</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Nomor Resi (Tracking Number)</label>
                            <input type="text"
                                   name="tracking_number"
                                   value="{{ $shipment->tracking_number }}"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label">Lokasi Terkini (Opsional)</label>
                            <input type="text"
                                   name="location"
                                   value="{{ old('location') }}"
                                   placeholder="Contoh: Surabaya Hub, Waru Drop Point..."
                                   class="form-input text-xs">
                        </div>

                        <div>
                            <label class="form-label">Catatan Pembaruan (Opsional)</label>
                            <textarea name="notes"
                                      rows="2"
                                      placeholder="Tambahkan catatan untuk riwayat internal..."
                                      class="form-textarea text-xs">{{ $shipment->notes }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-full">
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Tambah Log Perjalanan --}}
<div id="logModal" class="modal-backdrop hidden">
    <div class="modal-panel">
        <div class="modal-header">
            <div class="modal-title">Tambah Log Perjalanan</div>
            <button type="button" class="btn-icon" onclick="closeLogModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form action="{{ route('admin.shipments.logs.store', $shipment) }}" method="POST">
            @csrf
            <div class="modal-body">
                <div>
                    <label class="form-label">Status Checkpoint</label>
                    <input type="text"
                           name="status"
                           placeholder="Contoh: picked_up, in_transit, drop_point..."
                           required
                           class="form-input">
                </div>

                <div>
                    <label class="form-label">Lokasi Checkpoint</label>
                    <input type="text"
                           name="location"
                           placeholder="Contoh: Waru Hub, Wonokromo..."
                           required
                           class="form-input">
                </div>

                <div>
                    <label class="form-label">Deskripsi Kejadian</label>
                    <textarea name="description"
                              rows="3"
                              placeholder="Detail peristiwa atau kondisi paket..."
                              required
                              class="form-textarea"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeLogModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Log</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLogModal() {
    const modal = document.getElementById('logModal');
    if (modal) modal.classList.remove('hidden');
}

function closeLogModal() {
    const modal = document.getElementById('logModal');
    if (modal) modal.classList.add('hidden');
}
</script>
@endsection
