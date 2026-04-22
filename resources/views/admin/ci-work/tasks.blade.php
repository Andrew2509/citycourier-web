@extends('layouts.admin')

@section('title', 'Manajemen Tugas')
@section('page-title', 'Manajemen Tugas')
@section('page-subtitle', 'Daftar tugas pengiriman yang sedang berjalan')

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
                    <th>Pickup</th>
                    <th>Tujuan</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td><span style="font-weight:600;">{{ $task->order_number }}</span></td>
                        <td>{{ $task->courier->user->name ?? 'Unassigned' }}</td>
                        <td>
                            <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $task->pickup_address }}">
                                {{ $task->pickup_address }}
                            </div>
                        </td>
                        <td>
                            <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $task->delivery_address }}">
                                {{ $task->delivery_address }}
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-{{ $task->status }}">
                                {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.orders.detail', $task) }}" class="btn btn-ghost btn-sm">
                                <i class="fas fa-eye"></i> Pantau
                            </a>
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
@endsection
