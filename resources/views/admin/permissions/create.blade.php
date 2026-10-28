@extends('layouts.admin')

@section('title', 'Tambah Permission')

@section('content')
<div class="space-y-5 max-w-xl">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.permissions.index') }}" class="back-link" title="Kembali ke Daftar Permission">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Tambah Permission Baru</h1>
                <p class="page-subtitle">Daftarkan identifier wewenang baru ke sistem otorisasi.</p>
            </div>
        </div>
    </div>

    {{-- Form Tambah Permission --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Informasi Permission</div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.permissions.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="form-label">
                        Nama Permission (Format: modul.aksi) <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name') }}"
                           placeholder="Contoh: reports.export, finance.settle"
                           required
                           class="form-input font-mono text-xs @error('name') form-invalid @enderror">
                    <p class="form-help">Gunakan huruf kecil dengan titik pemisah, contoh: <code>orders.edit</code>, <code>couriers.verify</code>.</p>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Simpan Permission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
