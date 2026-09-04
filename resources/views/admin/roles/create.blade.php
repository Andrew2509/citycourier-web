@extends('layouts.admin')

@section('title', 'Tambah Role')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.roles.index') }}" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Tambah Role</h1>
            <p class="text-sm text-slate-400 mt-1">Buat role baru untuk pengguna</p>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-2xl border border-surface-border p-6 max-w-2xl">
        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf

            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Role</label>
                <input type="text" name="name"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('name') border-error @enderror"
                       value="{{ old('name') }}"
                       placeholder="Masukkan nama role"
                       required>
                @error('name')
                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-3">Permissions</label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($permissions as $permission)
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:border-primary/30 hover:bg-primary/5 transition-all cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                               class="w-4 h-4 rounded text-primary focus:ring-primary/20">
                        <span class="text-sm text-slate-600">{{ $permission->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all">
                    Simpan
                </button>
                <a href="{{ route('admin.roles.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
