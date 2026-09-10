@extends('layouts.admin')

@section('title', 'Tambah Permission Baru')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.permissions.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-space-md">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[22px] text-primary">vpn_key</span>
            </div>
            <div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Tambah Permission Baru</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Buat permission baru untuk mengontrol hak akses fitur</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden max-w-2xl">
        <div class="px-space-xl py-space-md border-b border-surface-container-high">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">key</span>
                Form Permission
            </h2>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.permissions.store') }}" method="POST">
                @csrf

                <div class="mb-space-lg">
                    <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Nama Permission</label>
                    <input type="text" name="name"
                           class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('name') border-red-500 @enderror"
                           value="{{ old('name') }}"
                           placeholder="Contoh: manage-users"
                           required>
                    <p class="font-label-sm text-label-sm text-secondary mt-space-xs">Gunakan format slug (huruf kecil, pisahkan spasi dengan tanda strip).</p>
                    @error('name')
                        <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-space-md pt-space-md border-t border-surface-container-high">
                    <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">check</span>
                        Simpan
                    </button>
                    <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container font-label-md text-label-md font-semibold transition-all">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
