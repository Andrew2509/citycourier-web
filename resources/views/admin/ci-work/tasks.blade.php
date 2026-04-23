@extends('layouts.admin')

@section('title', 'Manajemen Tugas')
@section('page-title', 'Manajemen Tugas')
@section('page-subtitle', 'Daftar tugas pengiriman yang sedang berjalan dengan bukti foto')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-tasks"></i>
            Monitoring Tugas Aktif
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Kurir</th>
                    <th>Pengirim (Pickup)</th>
                    <th>Penerima (Tujuan)</th>
                    <th>Status</th>
                    <th>Bukti Foto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr onclick="window.location='{{ route('admin.orders.detail', $task) }}'" style="cursor:pointer;">
                        <td><span style="font-weight:600;">{{ $task->order_number }}</span></td>
                        <td>
                            <div class="user-name">{{ $task->courier->user->name ?? 'Unassigned' }}</div>
                            <div class="user-email">{{ $task->courier->courier_id ?? '-' }}</div>
                        </td>
                        <td>
                            <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 200px;">
                                {{ $task->pickup_address }}
                            </div>
                        </td>
                        <td>
                            <div style="font-size:12px; color: var(--text-light); line-height: 1.4; max-width: 200px;">
                                {{ $task->delivery_address }}
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-{{ $task->status }}">
                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                @if($task->pickup_photo)
                                    <a href="{{ asset('storage/' . $task->pickup_photo) }}" target="_blank" class="photo-thumb" title="Foto Pickup">
                                        <i class="fas fa-camera"></i> P
                                    </a>
                                @endif
                                @if($task->delivery_photo)
                                    <a href="{{ asset('storage/' . $task->delivery_photo) }}" target="_blank" class="photo-thumb" title="Foto Delivery" style="background: var(--accent-success);">
                                        <i class="fas fa-camera"></i> D
                                    </a>
                                @endif
                                @if(!$task->pickup_photo && !$task->delivery_photo)
                                    <span style="color: var(--text-muted); font-size: 11px;">Belum ada</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">
                            Tidak ada tugas berjalan saat ini
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tasks->hasPages())
        <div class="card-footer" style="padding: 15px 24px; border-top: 1px solid var(--border-glass);">
            {{ $tasks->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>

<style>
    .photo-thumb {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: var(--accent-primary);
        color: white;
        border-radius: 6px;
        text-decoration: none;
        font-size: 10px;
        font-weight: 800;
        transition: transform 0.2s;
    }
    .photo-thumb:hover {
        transform: scale(1.1);
        color: white;
    }
</style>
@endsection
