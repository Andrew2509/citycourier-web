@extends('layouts.admin')

@section('title', 'Detail Pesanan')
@section('page-title', 'Detail Pesanan')
@section('page-subtitle', $order->order_number)

@section('content')
    <div style="margin-bottom: 20px;">
        <a href="{{ route('admin.orders') }}" class="btn btn-ghost btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
        </a>
    </div>

    <div class="detail-grid">
        {{-- Order Info --}}
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-box"></i>
                    Informasi Pesanan
                </div>
                <span class="badge badge-{{ $order->status }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label">No. Order</span>
                    <span class="detail-value" style="color: var(--accent-primary-light);">{{ $order->order_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Deskripsi Paket</span>
                    <span class="detail-value">{{ $order->package_description ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Berat</span>
                    <span class="detail-value">{{ $order->package_weight }} kg</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Harga</span>
                    <span class="detail-value currency">Rp {{ number_format($order->price, 0, ',', '.') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Catatan</span>
                    <span class="detail-value">{{ $order->notes ?? '-' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dibuat</span>
                    <span class="detail-value">{{ $order->created_at->format('d M Y, H:i') }}</span>
                </div>
                @if($order->picked_up_at)
                    <div class="detail-row">
                        <span class="detail-label">Diambil</span>
                        <span class="detail-value">{{ $order->picked_up_at->format('d M Y, H:i') }}</span>
                    </div>
                @endif
                @if($order->delivered_at)
                    <div class="detail-row">
                        <span class="detail-label">Dikirim</span>
                        <span class="detail-value" style="color: var(--accent-success);">{{ $order->delivered_at->format('d M Y, H:i') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Customer & Address Info --}}
        <div style="display: flex; flex-direction: column; gap: 20px;">
            {{-- Customer --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-user"></i>
                        Informasi Customer
                    </div>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <span class="detail-label">Nama</span>
                        <span class="detail-value">{{ $order->customer_name }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Telepon</span>
                        <span class="detail-value">{{ $order->customer_phone }}</span>
                    </div>
                </div>
            </div>

            {{-- Addresses --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Alamat Pengiriman
                    </div>
                </div>
                <div class="card-body">
                    <div class="detail-row" style="flex-direction: column; gap: 4px;">
                        <span class="detail-label"><i class="fas fa-arrow-up" style="color: var(--accent-info);"></i> Pickup</span>
                        <span class="detail-value" style="text-align:left;">{{ $order->pickup_address }}</span>
                    </div>
                    <div class="detail-row" style="flex-direction: column; gap: 4px;">
                        <span class="detail-label"><i class="fas fa-arrow-down" style="color: var(--accent-success);"></i> Tujuan</span>
                        <span class="detail-value" style="text-align:left;">{{ $order->delivery_address }}</span>
                    </div>
                </div>
            </div>

            {{-- Courier --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-motorcycle"></i>
                        Kurir
                    </div>
                </div>
                <div class="card-body">
                    @if($order->courier && $order->courier->user)
                        <div class="user-info" style="margin-bottom: 16px;">
                            <div class="user-avatar" style="width:44px; height:44px; font-size:16px;">
                                {{ strtoupper(substr($order->courier->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="user-name">{{ $order->courier->user->name }}</div>
                                <div class="user-email">{{ $order->courier->phone }}</div>
                            </div>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Kendaraan</span>
                            <span class="detail-value">
                                <span class="badge badge-{{ $order->courier->vehicle_type }}">
                                    {{ ucfirst($order->courier->vehicle_type) }}
                                </span>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Plat</span>
                            <span class="detail-value">{{ $order->courier->vehicle_plate ?? '-' }}</span>
                        </div>
                    @else
                        <div class="empty-state" style="padding: 20px;">
                            <i class="fas fa-user-slash" style="font-size: 24px;"></i>
                            <p>Belum ada kurir yang ditugaskan</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
