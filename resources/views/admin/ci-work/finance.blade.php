@extends('layouts.admin')

@section('title', 'Keuangan & Setoran')
@section('page-title', 'Keuangan & Setoran')
@section('page-subtitle', 'Ringkasan penghasilan kurir dan verifikasi keuangan')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-wallet"></i>
            Data Keuangan Kurir
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kurir</th>
                    <th>Tugas Selesai</th>
                    <th>Total Omzet</th>
                    <th>Potongan (10%)</th>
                    <th>Pendapatan Bersih</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($earnings as $earning)
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar" style="background: linear-gradient(135deg, var(--accent-warning), #d97706);">
                                    {{ strtoupper(substr($earning->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="user-name">{{ $earning->user->name ?? '-' }}</div>
                                    <div class="user-email">{{ $earning->phone }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;">{{ $earning->completed_orders }}</td>
                        <td class="currency">Rp {{ number_format($earning->total_earnings ?? 0, 0, ',', '.') }}</td>
                        <td class="currency" style="color: var(--accent-danger);">
                            Rp {{ number_format(($earning->total_earnings ?? 0) * 0.1, 0, ',', '.') }}
                        </td>
                        <td class="currency" style="color: var(--accent-success); font-weight: 700;">
                            Rp {{ number_format(($earning->total_earnings ?? 0) * 0.9, 0, ',', '.') }}
                        </td>
                        <td>
                            <button class="btn btn-ghost btn-sm">
                                <i class="fas fa-history"></i> Riwayat
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">
                            Belum ada data keuangan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($earnings->hasPages())
        <div class="card-footer" style="padding: 15px 24px; border-top: 1px solid var(--border-glass);">
            {{ $earnings->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
