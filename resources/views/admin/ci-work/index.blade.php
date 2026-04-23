@extends('layouts.admin')

@section('title', 'Ci-Work Dashboard')
@section('page-title', 'Ci-Work Dashboard')
@section('page-subtitle', 'Monitoring operasional kurir real-time')

@section('content')
    {{-- Stats Cards --}}
    <div class="stats-grid">
        <div class="stat-card info">
            <div class="stat-card-header">
                <div class="stat-icon info">
                    <i class="fas fa-motorcycle"></i>
                </div>
                <div class="badge badge-active">Live</div>
            </div>
            <div class="stat-value">{{ $stats['online_couriers'] }}</div>
            <div class="stat-label">Kurir Online</div>
        </div>

        <div class="stat-card primary">
            <div class="stat-card-header">
                <div class="stat-icon primary">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            <div class="stat-value">{{ $stats['active_tasks'] }}</div>
            <div class="stat-label">Tugas Berjalan</div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <div class="stat-icon success">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>
            <div class="stat-value">{{ $stats['completed_today'] }}</div>
            <div class="stat-label">Selesai Hari Ini</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <div class="stat-icon warning">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="stat-value">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</div>
            <div class="stat-label">Omzet Hari Ini</div>
        </div>
    </div>

    <div class="dashboard-grid">
        {{-- Recent Active Tasks --}}
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-running"></i>
                    Tugas Aktif Terkini
                </div>
                <a href="{{ route('admin.ci-work.tasks') }}" class="btn btn-ghost btn-sm">
                    Lihat Semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor Resi</th>
                            <th>Kurir</th>
                            <th>Status</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTasks as $task)
                            <tr>
                                <td><span style="font-weight:600;">{{ $task->order_number }}</span></td>
                                <td>{{ $task->courier->user->name ?? 'Unassigned' }}</td>
                                <td>
                                    <span class="badge badge-{{ $task->status }}">
                                        {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                    </span>
                                </td>
                                <td>{{ $task->updated_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 30px; color: var(--text-muted);">
                                    Tidak ada tugas aktif saat ini
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quick Links --}}
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-link"></i>
                    Tautan Cepat
                </div>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <a href="{{ route('admin.ci-work.attendance') }}" class="btn btn-ghost" style="justify-content: flex-start;">
                        <i class="fas fa-user-clock"></i> Cek Presensi Kurir
                    </a>
                    <a href="{{ route('admin.ci-work.tasks') }}" class="btn btn-ghost" style="justify-content: flex-start;">
                        <i class="fas fa-clipboard-list"></i> Pantau Antrean Tugas
                    </a>
                    <a href="{{ route('admin.ci-work.finance') }}" class="btn btn-ghost" style="justify-content: flex-start;">
                        <i class="fas fa-money-check-alt"></i> Verifikasi Setoran
                    </a>
                    <a href="{{ route('admin.couriers') }}" class="btn btn-ghost" style="justify-content: flex-start;">
                        <i class="fas fa-user-plus"></i> Verifikasi Kurir Baru
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
