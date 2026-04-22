@extends('layouts.admin')

@section('title', 'Presensi Kurir')
@section('page-title', 'Presensi Kurir')
@section('page-subtitle', 'Daftar kurir yang sedang aktif dan siap menerima pesanan')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-fingerprint"></i>
            Monitoring Kehadiran Real-time
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kurir</th>
                    <th>Telepon</th>
                    <th>Status</th>
                    <th>Lokasi Terakhir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($couriers as $courier)
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    {{ strtoupper(substr($courier->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="user-name">{{ $courier->user->name ?? '-' }}</div>
                                    <div class="user-email">ID: {{ $courier->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $courier->phone }}</td>
                        <td>
                            @if($courier->is_active)
                                <span class="badge badge-active">
                                    <i class="fas fa-circle" style="font-size:8px;"></i> Online
                                </span>
                            @else
                                <span class="badge badge-inactive">Offline</span>
                            @endif
                        </td>
                        <td>
                            @if($courier->latitude && $courier->longitude)
                                <a href="https://www.google.com/maps?q={{ $courier->latitude }},{{ $courier->longitude }}" 
                                   target="_blank" class="btn btn-ghost btn-sm">
                                    <i class="fas fa-map-marker-alt"></i> Lihat Map
                                </a>
                            @else
                                <span style="color: var(--text-muted); font-size: 12px;">Lokasi tidak tersedia</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.couriers') }}?search={{ $courier->user->name }}" class="btn btn-ghost btn-sm">
                                <i class="fas fa-user-edit"></i> Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 30px; color: var(--text-muted);">
                            Belum ada kurir terdaftar
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($couriers->hasPages())
        <div class="card-footer" style="padding: 15px 24px; border-top: 1px solid var(--border-glass);">
            {{ $couriers->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
