@extends('layouts.admin')

@section('title', 'Keuangan & Setoran')
@section('page-title', 'Keuangan & Setoran')
@section('page-subtitle', 'Ringkasan penghasilan kurir dan manajemen penarikan dana')

@section('content')
<div class="dashboard-grid">
    {{-- Courier Earnings Table --}}
    <div class="glass-card" style="grid-column: span 12;">
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

    {{-- Withdrawal Requests Table --}}
    <div class="glass-card" style="grid-column: span 12;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-hand-holding-usd"></i>
                Permintaan Penarikan Dana
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Kurir</th>
                        <th>Jumlah</th>
                        <th>Rekening</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($withdrawals as $withdrawal)
                        <tr>
                            <td>{{ $withdrawal->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $withdrawal->courier->user->name ?? '-' }}</td>
                            <td class="currency" style="font-weight: 700;">
                                Rp {{ number_format($withdrawal->amount, 0, ',', '.') }}
                            </td>
                            <td>
                                <div style="font-size: 12px;">
                                    <strong>{{ $withdrawal->bank_name }}</strong><br>
                                    {{ $withdrawal->account_number }}<br>
                                    a.n {{ $withdrawal->account_name }}
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-{{ $withdrawal->status }}">
                                    {{ ucfirst($withdrawal->status) }}
                                </span>
                            </td>
                            <td>
                                @if($withdrawal->status === 'pending')
                                    <div style="display: flex; gap: 4px;">
                                        <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-primary btn-sm" title="Setujui">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Tolak" style="color: var(--accent-danger);">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                @elseif($withdrawal->status === 'approved')
                                    <form action="{{ route('admin.ci-work.finance.withdrawal.update', $withdrawal->id) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-money-bill-wave"></i> Selesaikan
                                        </button>
                                    </form>
                                @else
                                    <span style="color: var(--text-muted); font-size: 11px;">
                                        {{ $withdrawal->processed_at ? 'Diproses: ' . $withdrawal->processed_at->format('d/m/Y') : '-' }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">
                                Belum ada permintaan penarikan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($withdrawals->hasPages())
            <div class="card-footer" style="padding: 15px 24px; border-top: 1px solid var(--border-glass);">
                {{ $withdrawals->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection
