@extends('layouts.admin')

@section('title', 'Manajemen Tugas')
@section('page-title', 'Manajemen Tugas')
@section('page-subtitle', 'Pantau tugas aktif dan antrean pengiriman')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-tasks"></i>
            Monitoring Tugas Aktif
        </div>
    </div>
    <div class="card-body">
        <div style="text-align:center; padding: 50px 20px;">
            <div style="font-size: 48px; color: var(--accent-info); margin-bottom: 20px;">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <h3 style="margin-bottom: 10px;">Manajemen Tugas</h3>
            <p style="color: var(--text-muted);">Daftar tugas yang sedang dikerjakan kurir akan muncul di sini.</p>
        </div>
    </div>
</div>
@endsection
