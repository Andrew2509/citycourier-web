@extends('layouts.admin')

@section('title', 'Edit Permission - ' . $permission->name)

@section('content')
<div class="space-y-5 max-w-xl">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.permissions.index') }}" class="back-link" title="Kembali ke Daftar Permission">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Edit Permission</h1>
                <p class="page-subtitle font-mono">ID: #{{ $permission->id }} &bull; {{ $permission->name }}</p>
            </div>
        </div>
    </div>

    {{-- Form Edit Permission --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Perbarui Kunci Permission</div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.permissions.update', $permission) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="form-label">
                        Nama Permission (Format: modul.aksi) <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           id="name"
                           value="{{ old('name', $permission->name) }}"
                           required
                           class="form-input font-mono text-xs @error('name') form-invalid @enderror">
                    <p class="form-help">Pastikan kode wewenang sinkron dengan referensi kode di controller atau view.</p>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.permissions.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
