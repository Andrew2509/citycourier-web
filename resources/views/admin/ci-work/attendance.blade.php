@extends('layouts.admin')

@section('title', 'Presensi Kurir')
@section('page-title', 'Presensi Kurir')
@section('page-subtitle', 'Monitoring kehadiran dan jam kerja kurir')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-fingerprint"></i>
            Data Kehadiran Kurir
        </div>
    </div>
    <div class="card-body">
        <div style="text-align:center; padding: 50px 20px;">
            <div style="font-size: 48px; color: var(--accent-success); margin-bottom: 20px;">
                <i class="fas fa-clock"></i>
            </div>
            <h3 style="margin-bottom: 10px;">Fitur Presensi</h3>
            <p style="color: var(--text-muted);">Data absensi real-time kurir akan ditampilkan di halaman ini.</p>
        </div>
    </div>
</div>
@endsection
