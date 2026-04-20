@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Ringkasan aktivitas City Courier')

@section('content')
    {{-- Stats Cards --}}
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-card-header">
                <div class="stat-icon primary">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['total_orders'] }}">0</div>
            <div class="stat-label">Total Pesanan</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <div class="stat-icon success">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['total_revenue'] }}" data-prefix="Rp ">0</div>
            <div class="stat-label">Total Pendapatan</div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <div class="stat-icon info">
                    <i class="fas fa-motorcycle"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['active_couriers'] }}">0</div>
            <div class="stat-label">Kurir Aktif</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['pending_orders'] }}">0</div>
            <div class="stat-label">Pesanan Pending</div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <div class="stat-icon danger">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['delivering_orders'] }}">0</div>
            <div class="stat-label">Sedang Diantar</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['completed_orders'] }}">0</div>
            <div class="stat-label">Pesanan Selesai</div>
        </div>

        <div class="stat-card primary">
            <div class="stat-card-header">
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['total_couriers'] }}">0</div>
            <div class="stat-label">Total Kurir</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <div class="stat-icon warning">
                    <i class="fas fa-user-clock"></i>
                </div>
            </div>
            <div class="stat-value" data-value="{{ $stats['unverified_couriers'] }}">0</div>
            <div class="stat-label">Menunggu Verifikasi</div>
        </div>
    </div>

    {{-- Recent Data Grid --}}
    <div class="dashboard-grid">
        {{-- Recent Orders --}}
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-box"></i>
                    Pesanan Terbaru
                </div>
                <a href="{{ route('admin.orders') }}" class="btn btn-ghost btn-sm">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr data-href="{{ route('admin.orders.detail', $order) }}">
                                <td>
                                    <span style="font-weight:600; color: var(--text-primary);">
                                        {{ $order->order_number }}
                                    </span>
                                </td>
                                <td>{{ $order->customer_name }}</td>
                                <td>
                                    <span class="badge badge-{{ $order->status }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="currency">Rp {{ number_format($order->price, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center; color: var(--text-muted); padding: 30px;">
                                    Belum ada pesanan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Active Couriers --}}
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-motorcycle"></i>
                    Kurir Aktif
                </div>
                <a href="{{ route('admin.couriers') }}" class="btn btn-ghost btn-sm">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kurir</th>
                            <th>Kendaraan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeCouriers as $courier)
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            {{ strtoupper(substr($courier->user->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="user-name">{{ $courier->user->name ?? '-' }}</div>
                                            <div class="user-email">{{ $courier->phone ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $courier->vehicle_type }}">
                                        @if($courier->vehicle_type === 'motor')
                                            <i class="fas fa-motorcycle"></i>
                                        @elseif($courier->vehicle_type === 'mobil')
                                            <i class="fas fa-car"></i>
                                        @else
                                            <i class="fas fa-bicycle"></i>
                                        @endif
                                        {{ ucfirst($courier->vehicle_type) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-active">
                                        <i class="fas fa-circle" style="font-size:6px;"></i> Online
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="text-align:center; color: var(--text-muted); padding: 30px;">
                                    Tidak ada kurir aktif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
