@extends('layouts.admin')

@section('title', 'Akses Ditolak')

@section('content')
<div class="flex items-center justify-center min-h-[65vh] p-4">
    <div class="card p-8 max-w-md w-full text-center shadow-lg border-slate-200">
        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100">
            <span class="material-symbols-outlined text-[30px]">lock</span>
        </div>
        <h1 class="text-lg font-bold text-slate-900 mb-1.5">403 — Akses Ditolak</h1>
        <p class="text-xs text-slate-500 mb-6 leading-relaxed">
            Akun Anda tidak memiliki hak akses atau perizinan yang memadai untuk membuka halaman ini. Hubungi administrator sistem jika Anda membutuhkan akses operasional.
        </p>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary text-xs h-9 px-4 inline-flex items-center gap-1.5 shadow-sm mx-auto">
            <span class="material-symbols-outlined text-[16px]">dashboard</span>
            <span>Kembali ke Dashboard</span>
        </a>
    </div>
</div>
@endsection
