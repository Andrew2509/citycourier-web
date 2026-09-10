@extends('layouts.admin')

@section('title', 'Manajemen Pengiriman')

@push('styles')
<style>
    :root {
        --primary: #059669;
        --primary-light: #d1fae5;
        --primary-dark: #047857;
    }

    .page-header {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        border-radius: 16px;
        padding: 32px;
        color: white;
        margin-bottom: 24px;
    }

    .page-header .icon-wrapper {
        width: 56px;
        height: 56px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .page-header .icon-wrapper .material-symbols-outlined {
        font-size: 28px;
    }

    .filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 20px;
    }

    .filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 100px;
        border: 1.5px solid #e5e7eb;
        background: white;
        color: #6b7280;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .filter-pill:hover {
        border-color: #059669;
        color: #059669;
        background: #f0fdf4;
    }

    .filter-pill.active {
        background: #059669;
        color: white;
        border-color: #059669;
    }

    .filter-pill .count {
        background: rgba(0, 0, 0, 0.08);
        padding: 1px 8px;
        border-radius: 100px;
        font-size: 12px;
        font-weight: 600;
    }

    .filter-pill.active .count {
        background: rgba(255, 255, 255, 0.25);
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 10px 16px 10px 44px;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
        background: white;
    }

    .search-box input:focus {
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }

    .search-box .material-symbols-outlined {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 20px;
    }

    .card-table {
        background: white;
        border-radius: 16px;
        border: 1px solid #f3f4f6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead th {
        background: #f9fafb;
        padding: 14px 16px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-align: left;
        white-space: nowrap;
        border-bottom: 1px solid #f3f4f6;
    }

    tbody td {
        padding: 14px 16px;
        font-size: 14px;
        color: #374151;
        border-bottom: 1px solid #f9fafb;
        vertical-align: middle;
    }

    tbody tr:hover {
        background: #f9fafb;
    }

    tbody tr:last-child td {
        border-bottom: none;
    }

    .shipment-number {
        font-weight: 600;
        color: #059669;
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
    }

    .tracking-number {
        font-size: 12px;
        color: #9ca3af;
        font-family: 'JetBrains Mono', monospace;
        margin-top: 2px;
    }

    .person-info .name {
        font-weight: 500;
        color: #1f2937;
    }

    .person-info .label {
        font-size: 11px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }

    .badge .material-symbols-outlined {
        font-size: 14px;
    }

    .badge-pending {
        background: #fef3c7;
        color: #d97706;
    }

    .badge-confirmed {
        background: #dbeafe;
        color: #2563eb;
    }

    .badge-picked_up {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .badge-in_transit {
        background: #fce7f3;
        color: #db2777;
    }

    .badge-delivered {
        background: #d1fae5;
        color: #059669;
    }

    .badge-cancelled {
        background: #fee2e2;
        color: #dc2626;
    }

    .address-text {
        font-size: 13px;
        color: #6b7280;
        line-height: 1.4;
    }

    .price-text {
        font-weight: 600;
        color: #1f2937;
        font-size: 14px;
    }

    .action-btns {
        display: flex;
        gap: 6px;
    }

    .btn-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1.5px solid #e5e7eb;
        background: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        color: #6b7280;
        text-decoration: none;
    }

    .btn-icon:hover {
        border-color: #059669;
        color: #059669;
        background: #f0fdf4;
    }

    .btn-icon .material-symbols-outlined {
        font-size: 18px;
    }

    .btn-icon-danger:hover {
        border-color: #dc2626;
        color: #dc2626;
        background: #fef2f2;
    }

    .btn-delete-inline {
        background: none;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        padding: 0;
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        transition: all 0.2s;
    }

    .btn-delete-inline:hover {
        border-color: #dc2626;
        color: #dc2626;
        background: #fef2f2;
    }

    .btn-delete-inline .material-symbols-outlined {
        font-size: 18px;
    }

    .empty-state {
        text-align: center;
        padding: 64px 24px;
    }

    .empty-state .icon-wrapper {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: #f0fdf4;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    .empty-state .icon-wrapper .material-symbols-outlined {
        font-size: 36px;
        color: #059669;
    }

    .empty-state h3 {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .empty-state p {
        font-size: 14px;
        color: #9ca3af;
    }

    .pagination-wrapper {
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid #f3f4f6;
    }

    .pagination-info {
        font-size: 13px;
        color: #9ca3af;
    }

    .pagination {
        display: flex;
        gap: 4px;
    }

    .pagination a,
    .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }

    .pagination a {
        color: #6b7280;
        background: white;
        border: 1.5px solid #e5e7eb;
    }

    .pagination a:hover {
        border-color: #059669;
        color: #059669;
        background: #f0fdf4;
    }

    .pagination .active span {
        background: #059669;
        color: white;
        border: 1.5px solid #059669;
    }

    .pagination .disabled span {
        color: #d1d5db;
        background: #f9fafb;
        border: 1.5px solid #f3f4f6;
        cursor: not-allowed;
    }

    .tooltip-wrapper {
        position: relative;
    }

    .tooltip-wrapper:hover .tooltip-text {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }

    .tooltip-text {
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        background: #1f2937;
        color: white;
        font-size: 12px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 6px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.15s ease;
        pointer-events: none;
        z-index: 10;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="page-header">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-wrapper">
                <span class="material-symbols-outlined">local_shipping</span>
            </div>
            <div>
                <h4 class="mb-1 fw-bold">Manajemen Pengiriman</h4>
                <p class="mb-0 opacity-75" style="font-size: 14px;">Pantau status pengiriman paket dari pickup hingga delivered</p>
            </div>
        </div>
    </div>

    <div class="filter-pills">
        @php
            $filters = [
                'all' => ['label' => 'Semua', 'icon' => 'inventory_2'],
                'pending' => ['label' => 'Pending', 'icon' => 'schedule'],
                'confirmed' => ['label' => 'Confirmed', 'icon' => 'check_circle'],
                'picked_up' => ['label' => 'Picked Up', 'icon' => 'package_2'],
                'in_transit' => ['label' => 'In Transit', 'icon' => 'local_shipping'],
                'delivered' => ['label' => 'Delivered', 'icon' => 'task_alt'],
                'cancelled' => ['label' => 'Cancelled', 'icon' => 'cancel'],
            ];
            $currentFilter = request('status', 'all');
        @endphp

        @foreach($filters as $key => $filter)
            @if(isset($statusCounts[$key]))
                <a href="{{ route('admin.shipments.index', array_merge(request()->except(['status', 'page']), $key !== 'all' ? ['status' => $key] : [])) }}"
                   class="filter-pill {{ $currentFilter === $key ? 'active' : '' }}">
                    <span class="material-symbols-outlined" style="font-size: 16px;">{{ $filter['icon'] }}</span>
                    {{ $filter['label'] }}
                    <span class="count">{{ $statusCounts[$key] }}</span>
                </a>
            @endif
        @endforeach
    </div>

    <div class="card-table">
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom: 1px solid #f3f4f6;">
            <div class="d-flex align-items-center gap-2">
                <span class="material-symbols-outlined" style="color: #059669; font-size: 20px;">list</span>
                <span style="font-size: 14px; font-weight: 600; color: #1f2937;">Daftar Pengiriman</span>
                <span style="font-size: 12px; color: #9ca3af; background: #f3f4f6; padding: 2px 10px; border-radius: 100px;">{{ $shipments->total() }}</span>
            </div>
            <div class="search-box" style="width: 280px;">
                <span class="material-symbols-outlined">search</span>
                <form action="{{ route('admin.shipments.index') }}" method="GET">
                    @if($currentFilter !== 'all')
                        <input type="hidden" name="status" value="{{ $currentFilter }}">
                    @endif
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor, nama, atau alamat...">
                </form>
            </div>
        </div>

        @if($shipments->count() > 0)
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pengiriman</th>
                            <th>Pengirim / Penerima</th>
                            <th>Status</th>
                            <th>Asal / Tujuan</th>
                            <th>Harga</th>
                            <th style="text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shipments as $shipment)
                            <tr>
                                <td>
                                    <div class="shipment-number">#{{ $shipment->shipment_number }}</div>
                                    @if($shipment->tracking_number)
                                        <div class="tracking-number">{{ $shipment->tracking_number }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="mb-1">
                                        <div class="label" style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Dari</div>
                                        <div class="name" style="font-weight: 500; color: #1f2937;">{{ $shipment->sender_name }}</div>
                                    </div>
                                    <div>
                                        <div class="label" style="font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;">Ke</div>
                                        <div class="name" style="font-weight: 500; color: #1f2937;">{{ $shipment->receiver_name }}</div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $statusMap = [
                                            'pending' => ['label' => 'Pending', 'class' => 'badge-pending', 'icon' => 'schedule'],
                                            'confirmed' => ['label' => 'Confirmed', 'class' => 'badge-confirmed', 'icon' => 'check_circle'],
                                            'picked_up' => ['label' => 'Picked Up', 'class' => 'badge-picked_up', 'icon' => 'package_2'],
                                            'in_transit' => ['label' => 'In Transit', 'class' => 'badge-in_transit', 'icon' => 'local_shipping'],
                                            'delivered' => ['label' => 'Delivered', 'class' => 'badge-delivered', 'icon' => 'task_alt'],
                                            'cancelled' => ['label' => 'Cancelled', 'class' => 'badge-cancelled', 'icon' => 'cancel'],
                                        ];
                                        $status = $statusMap[$shipment->status] ?? ['label' => $shipment->status, 'class' => 'badge-pending', 'icon' => 'help'];
                                    @endphp
                                    <span class="badge {{ $status['class'] }}">
                                        <span class="material-symbols-outlined">{{ $status['icon'] }}</span>
                                        {{ $status['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="address-text">
                                        <div style="margin-bottom: 4px;">
                                            <span class="material-symbols-outlined" style="font-size: 14px; color: #059669; vertical-align: middle;">circle</span>
                                            {{ Str::limit($shipment->sender_address, 35) }}
                                        </div>
                                        <div>
                                            <span class="material-symbols-outlined" style="font-size: 14px; color: #dc2626; vertical-align: middle;">location_on</span>
                                            {{ Str::limit($shipment->receiver_address, 35) }}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="price-text">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</div>
                                </td>
                                <td>
                                    <div class="action-btns" style="justify-content: center;">
                                        <div class="tooltip-wrapper">
                                            <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="btn-icon">
                                                <span class="material-symbols-outlined">visibility</span>
                                            </a>
                                            <span class="tooltip-text">Detail</span>
                                        </div>
                                        <div class="tooltip-wrapper">
                                            <form action="{{ route('admin.shipments.destroy', $shipment->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus pengiriman #{{ $shipment->shipment_number }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-delete-inline">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            </form>
                                            <span class="tooltip-text">Hapus</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($shipments->hasPages())
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        Menampilkan {{ $shipments->firstItem() }} - {{ $shipments->lastItem() }} dari {{ $shipments->total() }} pengiriman
                    </div>
                    <div class="pagination">
                        {{ $shipments->withQueryString()->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @endif

        @else
            <div class="empty-state">
                <div class="icon-wrapper">
                    <span class="material-symbols-outlined">local_shipping</span>
                </div>
                <h3>Tidak ada pengiriman ditemukan</h3>
                <p>@if(request('search') || $currentFilter !== 'all')
                    Tidak ada hasil yang cocok dengan pencarian atau filter Anda.
                @else
                    Belum ada data pengiriman yang tersedia.
                @endif</p>
            </div>
        @endif
    </div>
</div>
@endsection
