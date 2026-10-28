@extends('layouts.admin')

@section('title', 'Daftar Pengiriman')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Daftar Pengiriman</h1>
            <p class="page-subtitle">Kelola seluruh resi pengiriman operasional kurir instan City Courier.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.shipments.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>Tambah Pengiriman Baru</span>
            </a>
            <span class="badge badge-gray text-xs">
                Total: <strong class="text-slate-900 ml-1">{{ $shipments->total() }}</strong> pengiriman
            </span>
        </div>
    </div>

    {{-- Filter & Pencarian --}}
    <div class="card p-3 sm:p-4">
        <form method="GET" action="{{ route('admin.shipments.index') }}" class="flex flex-col lg:flex-row lg:items-center gap-3">
            {{-- Tabs Filter Status --}}
            <div class="filter-tabs">
                @php
                    $curStatus = request('status', '');
                @endphp
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => ''])) }}"
                   class="filter-tab {{ $curStatus === '' ? 'is-active' : '' }}">
                    <span>Semua</span>
                </a>
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => 'pending'])) }}"
                   class="filter-tab {{ $curStatus === 'pending' ? 'is-active' : '' }}">
                    <span>Pending</span>
                </a>
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => 'picked_up'])) }}"
                   class="filter-tab {{ $curStatus === 'picked_up' ? 'is-active' : '' }}">
                    <span>Picked Up</span>
                </a>
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => 'in_transit'])) }}"
                   class="filter-tab {{ $curStatus === 'in_transit' ? 'is-active' : '' }}">
                    <span>In Transit</span>
                </a>
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => 'delivered'])) }}"
                   class="filter-tab {{ $curStatus === 'delivered' ? 'is-active' : '' }}">
                    <span>Delivered</span>
                </a>
                <a href="{{ route('admin.shipments.index', array_merge(request()->except('status', 'page'), ['status' => 'cancelled'])) }}"
                   class="filter-tab {{ $curStatus === 'cancelled' ? 'is-active' : '' }}">
                    <span>Cancelled</span>
                </a>
            </div>

            {{-- Kolom Cari --}}
            <div class="lg:ml-auto flex items-center gap-2">
                <div class="search-box w-full sm:w-72">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari nomor pengiriman, resi..."
                           class="form-input">
                </div>
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <button type="submit" class="btn btn-secondary">
                    Cari
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.shipments.index') }}" class="btn btn-ghost" title="Reset filter">
                        <span class="material-symbols-outlined">restart_alt</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Pengiriman --}}
    <div class="card overflow-hidden">
        @if($shipments->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. Pengiriman &amp; Resi</th>
                        <th>Waktu</th>
                        <th>Pengirim &amp; Penerima</th>
                        <th>Layanan &amp; Kendaraan</th>
                        <th>Status</th>
                        <th>Total Biaya</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($shipments as $shipment)
                    @php
                        $stKey = strtolower($shipment->status ?? 'pending');
                        $badgeClass = match($stKey) {
                            'pending', 'draft'            => 'badge-amber',
                            'confirmed', 'assigned'       => 'badge-blue',
                            'picked_up', 'in_transit'     => 'badge-indigo',
                            'delivered', 'completed'      => 'badge-green',
                            'cancelled', 'rejected'       => 'badge-red',
                            default                       => 'badge-gray',
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

                        $vehicle = strtolower($shipment->vehicle_type ?? $shipment->courier_service ?? 'motor');
                        $isMobil = str_contains($vehicle, 'car') || str_contains($vehicle, 'mobil');
                        $trackingNum = $shipment->tracking_number ?? $shipment->shipment_number;
                    @endphp
                    <tr>
                        {{-- Nomor Pengiriman & Resi --}}
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('admin.shipments.show', $shipment) }}"
                                   class="font-mono text-[13px] font-semibold text-brand-700 hover:text-brand-800 hover:underline">
                                    #{{ $shipment->shipment_number }}
                                </a>
                                @if($shipment->tracking_number)
                                <button type="button"
                                        onclick="copyShipmentResi('{{ $shipment->tracking_number }}')"
                                        class="p-1 text-slate-400 hover:text-slate-700 rounded transition-colors"
                                        title="Salin Resi">
                                    <span class="material-symbols-outlined text-[15px]">content_copy</span>
                                </button>
                                @endif
                            </div>
                            @if($shipment->tracking_number)
                            <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                Resi: {{ $shipment->tracking_number }}
                            </div>
                            @endif
                        </td>

                        {{-- Waktu --}}
                        <td class="whitespace-nowrap text-xs text-slate-500">
                            <div>{{ $shipment->created_at ? $shipment->created_at->format('d M Y') : '-' }}</div>
                            <div class="text-slate-400">{{ $shipment->created_at ? $shipment->created_at->format('H:i') : '' }} WIB</div>
                        </td>

                        {{-- Pengirim & Penerima --}}
                        <td class="max-w-[200px]">
                            <div class="truncate text-xs">
                                <span class="text-slate-400 font-medium">Dari:</span>
                                <strong class="text-slate-800">{{ $shipment->sender_name ?? '-' }}</strong>
                            </div>
                            <div class="truncate text-xs mt-0.5">
                                <span class="text-slate-400 font-medium">Ke:</span>
                                <strong class="text-slate-800">{{ $shipment->receiver_name ?? '-' }}</strong>
                            </div>
                        </td>

                        {{-- Layanan & Kendaraan --}}
                        <td class="whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-700 font-medium">
                                <span class="material-symbols-outlined text-[16px] text-slate-500">
                                    {{ $isMobil ? 'directions_car' : 'two_wheeler' }}
                                </span>
                                <span>{{ $isMobil ? 'Mobil' : 'Motor' }}</span>
                            </span>
                            <div class="text-[11px] text-slate-400">
                                {{ $shipment->courier_service ?? 'Instant' }}
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="whitespace-nowrap">
                            <span class="badge {{ $badgeClass }}">
                                <span class="badge-dot"></span>
                                {{ $statusLabel }}
                            </span>
                        </td>

                        {{-- Total Biaya --}}
                        <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-900">
                            Rp {{ number_format($shipment->total_cost ?? 0, 0, ',', '.') }}
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('tracking.show', $trackingNum) }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn-icon"
                                   title="Buka Live Tracking Web">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>

                                <a href="{{ route('admin.shipments.show', $shipment) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Lihat Detail Pengiriman">
                                    Detail
                                </a>

                                @canPerm('shipments.delete')
                                <form action="{{ route('admin.shipments.destroy', $shipment->id) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Yakin ingin menghapus pengiriman #{{ $shipment->shipment_number }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon btn-icon-danger"
                                            title="Hapus Pengiriman">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endcanPerm
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="card-footer">
            <div>
                Menampilkan <strong>{{ $shipments->firstItem() ?? 0 }}</strong> - <strong>{{ $shipments->lastItem() ?? 0 }}</strong> dari <strong>{{ $shipments->total() }}</strong> data
            </div>
            <div>
                {{ $shipments->withQueryString()->links() }}
            </div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">local_shipping</span>
            <div class="empty-title">Tidak ada data pengiriman ditemukan</div>
            <div class="empty-text">
                @if(request('search'))
                    Tidak ditemukan pengiriman dengan kata kunci "{{ request('search') }}".
                @else
                    Belum ada pengiriman yang sesuai dengan filter yang dipilih.
                @endif
            </div>
            @if(request('search') || request('status'))
                <a href="{{ route('admin.shipments.index') }}" class="btn btn-sm btn-secondary mt-3">
                    Reset Filter Pencarian
                </a>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- Toast Clipboard --}}
<div id="shipmentToast" class="fixed bottom-5 right-5 z-50 hidden items-center gap-2 rounded-md bg-navy-900 px-3.5 py-2.5 text-xs font-medium text-white shadow-xl transition-all">
    <span class="material-symbols-outlined text-brand-400 text-[18px]">check_circle</span>
    <span id="shipmentToastMessage">Nomor resi berhasil disalin!</span>
</div>

<script>
function copyShipmentResi(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        const toast = document.getElementById('shipmentToast');
        const toastMsg = document.getElementById('shipmentToastMessage');
        if (!toast || !toastMsg) return;
        toastMsg.textContent = 'Nomor resi ' + text + ' berhasil disalin!';
        toast.classList.remove('hidden');
        toast.classList.add('flex');
        setTimeout(function() {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        }, 2500);
    });
}
</script>
@endsection
