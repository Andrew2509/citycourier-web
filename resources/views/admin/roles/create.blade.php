@extends('layouts.admin')

@section('title', 'Tambah Role Baru')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div>
        <a href="{{ route('admin.roles.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-gray-500 transition hover:text-[#059669]">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Daftar Role
        </a>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#059669]/10">
                <span class="material-symbols-outlined text-[26px] text-[#059669]">add_moderator</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tambah Role Baru</h1>
                <p class="text-sm text-gray-500">Buat role baru dan tetapkan permission untuk hak akses</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('admin.roles.store') }}" method="POST" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf

        {{-- Name --}}
        <div class="mb-6">
            <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700">
                Nama Role <span class="text-red-500">*</span>
            </label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="block w-full max-w-md rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                placeholder="Masukkan nama role (contoh: editor)">
            @error('name')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Permissions --}}
        <div class="mb-6">
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Permission</label>
            <p class="mb-3 text-xs text-gray-400">Pilih hak akses yang akan diberikan kepada role ini.</p>

            @error('permissions')
                <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 transition hover:border-[#059669]/30 hover:bg-[#059669]/5">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                            {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-gray-300 text-[#059669] focus:ring-[#059669]">
                        <div>
                            <span class="block text-sm font-medium text-gray-700">{{ $permission->name }}</span>
                        </div>
                    </label>
                @endforeach
            </div>

            @if ($permissions->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <span class="material-symbols-outlined mx-auto mb-2 block text-[36px] text-gray-300">key_off</span>
                    <p class="text-sm text-gray-400">Belum ada permission tersedia</p>
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 border-t border-gray-200 pt-6">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[#059669] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#047857] focus:outline-none focus:ring-2 focus:ring-[#059669] focus:ring-offset-2">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Simpan Role
            </button>
            <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
