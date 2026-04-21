@extends('layouts.admin')

@section('title', 'Manajemen Drop Point')

@section('page-title', 'Manajemen Drop Point')
@section('page-subtitle', 'Kelola lokasi kantor dan agen City Courier')

@section('content')
<div class="card-container">
    <div class="card-header-actions">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Cari drop point...">
        </div>
        <a href="{{ route('admin.drop-points.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span>Tambah Drop Point</span>
        </a>
    </div>

    <div class="glass-card">
        <div class="card-body p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Kantor</th>
                        <th>Alamat</th>
                        <th>Telepon</th>
                        <th>Koordinat</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dropPoints as $point)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon info sm">
                                    <i class="fas fa-building"></i>
                                </div>
                                <span class="fw-600">{{ $point->name }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 250px;">
                                {{ $point->address }}
                            </div>
                        </td>
                        <td>{{ $point->phone ?? '-' }}</td>
                        <td>
                            @if($point->latitude && $point->longitude)
                                <span class="badge badge-info sm">
                                    {{ number_format($point->latitude, 4) }}, {{ number_format($point->longitude, 4) }}
                                </span>
                            @else
                                <span class="text-muted italic">Tidak ada koordinat</span>
                            @endif
                        </td>
                        <td>
                            <form action="{{ route('admin.drop-points.toggle-active', $point) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="badge {{ $point->is_active ? 'badge-active' : 'badge-inactive' }} btn-link" style="border:none; cursor:pointer;">
                                    <i class="fas {{ $point->is_active ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                                    {{ $point->is_active ? 'Aktif' : 'Non-aktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="text-center">
                            <div class="action-btns">
                                <a href="{{ route('admin.drop-points.edit', $point) }}" class="btn-action edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.drop-points.destroy', $point) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action delete" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="empty-state">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <p class="text-secondary">Belum ada data drop point.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($dropPoints->hasPages())
        <div class="card-footer">
            {{ $dropPoints->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
