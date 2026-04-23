@extends('layouts.admin')

@section('title', 'Manajemen Pengiriman')
@section('page-title', 'Manajemen Pengiriman')
@section('page-subtitle', 'Pantau request pengiriman dari aplikasi City Courier')

@section('content')
    {{-- Filter Tabs --}}
    <div class="glass-card" style="margin-bottom: 20px;">
        <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div class="filter-tabs">
                <a href="{{ route('admin.shipments.index') }}"
                   class="filter-tab {{ !request('status') ? 'active' : '' }}">
                    Semua <span class="count">({{ $statusCounts['all'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'pending']) }}"
                   class="filter-tab {{ request('status') === 'pending' ? 'active' : '' }}">
                    <i class="fas fa-clock"></i> Menunggu <span class="count">({{ $statusCounts['pending'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'confirmed']) }}"
                   class="filter-tab {{ request('status') === 'confirmed' ? 'active' : '' }}">
                    <i class="fas fa-check"></i> Dikonfirmasi <span class="count">({{ $statusCounts['confirmed'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'picked_up']) }}"
                   class="filter-tab {{ request('status') === 'picked_up' ? 'active' : '' }}">
                    <i class="fas fa-hand-paper"></i> Diambil <span class="count">({{ $statusCounts['picked_up'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}"
                   class="filter-tab {{ request('status') === 'in_transit' ? 'active' : '' }}">
                    <i class="fas fa-truck"></i> Dalam Perjalanan <span class="count">({{ $statusCounts['in_transit'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'delivered']) }}"
                   class="filter-tab {{ request('status') === 'delivered' ? 'active' : '' }}">
                    <i class="fas fa-check-circle"></i> Terkirim <span class="count">({{ $statusCounts['delivered'] }})</span>
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'cancelled']) }}"
                   class="filter-tab {{ request('status') === 'cancelled' ? 'active' : '' }}">
                    <i class="fas fa-times-circle"></i> Batal <span class="count">({{ $statusCounts['cancelled'] }})</span>
                </a>
            </div>

            <form method="GET" action="{{ route('admin.shipments.index') }}" class="search-bar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="search-input"
                           placeholder="Cari pengiriman, nama, resi..."
                           value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>
    
    @if(session('success'))
        <div class="glass-card" style="margin-bottom: 20px; border-left: 4px solid #16a34a; background: rgba(34,197,94,0.1);">
            <div class="card-body" style="padding: 12px 20px; color: #16a34a; font-weight: 500;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                {{ session('success') }}
            </div>
        </div>
    @endif

    {{-- Shipments Table --}}
    <div class="glass-card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">
                <i class="fas fa-shipping-fast"></i>
                Daftar Pengiriman
            </div>
            @if($shipments->total() > 0)
                <form action="{{ route('admin.shipments.destroy-all') }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus SEMUA data pengiriman? Semua data pembayaran terkait juga akan terhapus. Tindakan ini tidak dapat dibatalkan!');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-trash-alt" style="margin-right: 5px;"></i> Hapus Semua Data
                    </button>
                </form>
            @endif
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nomor Resi</th>
                        <th>Customer</th>
                        <th>Pengirim (Pickup)</th>
                        <th>Penerima (Tujuan)</th>
                        <th>Ekspedisi</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shipments as $shipment)
                        <tr data-href="{{ route('admin.shipments.show', $shipment) }}" style="cursor:pointer;">
                            <td>
                                <span style="font-weight:700; color: var(--accent-primary-light); font-size: 13px;">
                                    {{ $shipment->tracking_number ?? $shipment->shipment_number }}
                                </span>
                                @if($shipment->tracking_number)
                                    <div style="margin-top:4px; opacity: 0.8;">
                                        {!! \Milon\Barcode\Facades\DNS1DFacade::getBarcodeHTML($shipment->tracking_number, 'C128', 1, 20, 'black', false) !!}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div>
                                    <div class="user-name">{{ $shipment->customer_name }}</div>
                                    <div class="user-email">{{ $shipment->customer_phone }}</div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 250px;">
                                    {{ $shipment->sender_address }}
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 250px;">
                                    {{ $shipment->receiver_address }}
                                </div>
                            </td>
                            <td>
                                <div style="font-size:13px; font-weight:600;">
                                    {{ strtoupper($shipment->courier_code ?? '-') }}
                                </div>
                                <div style="font-size:11px; color: var(--text-muted);">
                                    {{ $shipment->courier_service ?? '' }} · {{ $shipment->package_weight }} kg
                                </div>
                            </td>
                            <td class="currency" style="font-weight: 600; color: var(--accent-success);">
                                Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}
                            </td>
                            <td>
                                <span class="badge badge-{{ $shipment->status_color }}">
                                    {{ $shipment->status_label }}
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-muted); white-space:nowrap;">
                                {{ $shipment->created_at->format('d M Y') }}
                                <br>
                                <span style="font-size:11px;">{{ $shipment->created_at->format('H:i') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-shipping-fast"></i>
                                    <h3>Belum ada pengiriman</h3>
                                    <p>Request pengiriman dari aplikasi Flutter akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($shipments->hasPages())
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan {{ $shipments->firstItem() }}-{{ $shipments->lastItem() }} dari {{ $shipments->total() }}
                </div>
                <div class="pagination-links">
                    {{ $shipments->withQueryString()->links('pagination.custom') }}
                </div>
            </div>
        @endif
    </div>

    <script>
        // Make rows clickable
        document.querySelectorAll('tr[data-href]').forEach(row => {
            row.addEventListener('click', () => {
                window.location.href = row.dataset.href;
            });
        });
    </script>
@endsection
