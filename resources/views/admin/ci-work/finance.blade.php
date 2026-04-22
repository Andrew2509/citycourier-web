@extends('layouts.admin')

@section('title', 'Keuangan & Setoran')
@section('page-title', 'Keuangan & Setoran')
@section('page-subtitle', 'Kelola penghasilan dan setoran tunai kurir')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-wallet"></i>
            Data Keuangan Kurir
        </div>
    </div>
    <div class="card-body">
        <div style="text-align:center; padding: 50px 20px;">
            <div style="font-size: 48px; color: var(--accent-warning); margin-bottom: 20px;">
                <i class="fas fa-money-check-alt"></i>
            </div>
            <h3 style="margin-bottom: 10px;">Keuangan & Setoran</h3>
            <p style="color: var(--text-muted);">Manajemen saldo dan verifikasi setoran kurir akan diproses di sini.</p>
        </div>
    </div>
</div>
@endsection
