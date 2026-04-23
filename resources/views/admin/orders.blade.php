@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')
@section('page-title', 'Manajemen Pesanan')
@section('page-subtitle', 'Pantau dan kelola seluruh pesanan pengiriman')

@section('content')
    {{-- Filter Tabs --}}
    <div class="glass-card" style="margin-bottom: 20px;">
        <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div class="filter-tabs">
                <a href="{{ route('admin.orders') }}"
                   class="filter-tab {{ !request('status') ? 'active' : '' }}">
                    Semua <span class="count">({{ $statusCounts['all'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'pending']) }}"
                   class="filter-tab {{ request('status') === 'pending' ? 'active' : '' }}">
                    <i class="fas fa-clock"></i> Pending <span class="count">({{ $statusCounts['pending'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'assigned']) }}"
                   class="filter-tab {{ request('status') === 'assigned' ? 'active' : '' }}">
                    <i class="fas fa-user-check"></i> Assigned <span class="count">({{ $statusCounts['assigned'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'picking_up']) }}"
                   class="filter-tab {{ request('status') === 'picking_up' ? 'active' : '' }}">
                    <i class="fas fa-hand-paper"></i> Picking Up <span class="count">({{ $statusCounts['picking_up'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'delivering']) }}"
                   class="filter-tab {{ request('status') === 'delivering' ? 'active' : '' }}">
                    <i class="fas fa-truck"></i> Delivering <span class="count">({{ $statusCounts['delivering'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'delivered']) }}"
                   class="filter-tab {{ request('status') === 'delivered' ? 'active' : '' }}">
                    <i class="fas fa-check-circle"></i> Selesai <span class="count">({{ $statusCounts['delivered'] }})</span>
                </a>
                <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}"
                   class="filter-tab {{ request('status') === 'cancelled' ? 'active' : '' }}">
                    <i class="fas fa-times-circle"></i> Batal <span class="count">({{ $statusCounts['cancelled'] }})</span>
                </a>
            </div>

            <form method="GET" action="{{ route('admin.orders') }}" class="search-bar">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" class="search-input"
                           placeholder="Cari order, nama, telepon..."
                           value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="glass-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-box"></i>
                Daftar Pesanan
            </div>
            <span style="font-size: 13px; color: var(--text-muted);">
                Total: {{ $orders->total() }} pesanan
            </span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Pengirim (Pickup)</th>
                        <th>Penerima (Tujuan)</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr onclick="window.location='{{ route('admin.orders.detail', $order) }}'" style="cursor:pointer;">
                            <td>
                                <span style="font-weight:700; color: var(--accent-primary-light); font-size: 13px;">
                                    {{ $order->order_number }}
                                </span>
                            </td>
                            <td>
                                <div>
                                    <div class="user-name">{{ $order->customer_name }}</div>
                                    <div class="user-email">{{ $order->customer_phone }}</div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 250px;">
                                    {{ $order->pickup_address }}
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 250px;">
                                    {{ $order->delivery_address }}
                                </div>
                            </td>
                            <td class="currency" style="font-weight: 600; color: var(--accent-success);">
                                Rp {{ number_format($order->price, 0, ',', '.') }}
                            </td>
                            <td>
                                <span class="badge badge-{{ $order->status }}">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-muted); white-space:nowrap;">
                                {{ $order->created_at->format('d M Y') }}
                                <br>
                                <span style="font-size:11px;">{{ $order->created_at->format('H:i') }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <h3>Belum ada pesanan</h3>
                                    <p>Pesanan baru dari aplikasi akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan {{ $orders->firstItem() }}-{{ $orders->lastItem() }} dari {{ $orders->total() }}
                </div>
                <div class="pagination-links">
                    {{ $orders->withQueryString()->links('pagination.custom') }}
                </div>
            </div>
        @endif
    </div>
@endsection
