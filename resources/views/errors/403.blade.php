@extends('layouts.admin')

@section('title', 'Akses Ditolak')

@section('content')
<div class="flex items-center justify-center min-h-[60vh]">
    <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-2xl max-w-md w-full text-center">
        <div class="w-20 h-20 mx-auto mb-space-lg rounded-full bg-error-container/20 flex items-center justify-center">
            <span class="material-symbols-outlined text-[40px] text-error">block</span>
        </div>
        <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold mb-space-sm">403 — Akses Ditolak</h1>
        <p class="font-body-md text-body-md text-secondary mb-space-xl">
            Anda tidak memiliki izin untuk mengakses halaman ini.
            Hubungi administrator jika Anda membutuhkan akses.
        </p>
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-sm rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
            <span class="material-symbols-outlined text-[18px]">dashboard</span>
            Kembali ke Dashboard
        </a>
    </div>
</div>
@endsection
