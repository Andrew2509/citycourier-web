@extends('layouts.admin')

@section('title', 'Tambah Permission')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.permissions.index') }}" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Tambah Permission</h1>
            <p class="text-sm text-slate-400 mt-1">Buat permission baru</p>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-2xl border border-surface-border p-6 max-w-2xl">
        <form action="{{ route('admin.permissions.store') }}" method="POST">
            @csrf

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Permission</label>
                <input type="text" name="name"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('name') border-error @enderror"
                       value="{{ old('name') }}"
                       placeholder="Contoh: manage-users"
                       required>
                @error('name')
                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all">
                    Simpan
                </button>
                <a href="{{ route('admin.permissions.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
