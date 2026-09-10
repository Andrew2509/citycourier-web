@extends('layouts.admin')

@section('title', 'Tambah Role Baru')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.roles.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-space-md">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[22px] text-primary">add_moderator</span>
            </div>
            <div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Tambah Role Baru</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Buat role baru dan tetapkan permission untuk hak akses</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden max-w-3xl">
        <div class="px-space-xl py-space-md border-b border-surface-container-high">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                Form Role
            </h2>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.roles.store') }}" method="POST">
                @csrf

                {{-- Name --}}
                <div class="mb-space-lg">
                    <label for="name" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                        Nama Role <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('name') border-red-500 @enderror"
                        placeholder="Masukkan nama role (contoh: editor)">
                    @error('name')
                        <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Permissions --}}
                <div class="mb-space-lg">
                    <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Permission</label>
                    <p class="mb-space-md font-label-sm text-label-sm text-secondary">Pilih hak akses yang akan diberikan kepada role ini.</p>

                    @error('permissions')
                        <p class="mb-space-sm text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="grid grid-cols-1 gap-space-sm sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($permissions as $permission)
                            <label class="flex cursor-pointer items-center gap-space-sm rounded-lg border border-surface-container-high bg-surface p-space-md transition hover:border-primary-container hover:bg-primary-container/5">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                    {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-surface-container-high text-primary focus:ring-primary">
                                <div>
                                    <span class="block font-body-sm text-body-sm text-on-surface font-medium">{{ $permission->name }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    @if ($permissions->isEmpty())
                        <div class="rounded-lg border border-dashed border-surface-container-high bg-surface p-space-2xl text-center">
                            <span class="material-symbols-outlined mx-auto mb-space-sm block text-[36px] text-secondary">key_off</span>
                            <p class="font-body-sm text-body-sm text-secondary">Belum ada permission tersedia</p>
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-space-md pt-space-md border-t border-surface-container-high">
                    <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan Role
                    </button>
                    <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container font-label-md text-label-md font-semibold transition-all">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
