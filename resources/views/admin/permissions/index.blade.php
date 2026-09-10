@extends('layouts.admin')

@section('title', 'Manajemen Permission')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">lock_person</span>
                <span>Sistem & Keamanan</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Permission</h1>
            <p class="font-body-md text-body-md text-secondary">Kelola hak akses granular untuk setiap fitur sistem</p>
        </div>
        <a href="{{ route('admin.permissions.create') }}" class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            <span>Tambah Permission</span>
        </a>
    </div>

    <!-- Permissions Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Nama Permission</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kode</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Dibuat</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse ($permissions as $permission)
                    <tr class="hover:bg-surface transition-colors group">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                                    <span class="material-symbols-outlined text-[18px]">key</span>
                                </div>
                                <span class="font-semibold text-on-surface">{{ $permission->name }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center rounded-lg bg-surface-container-high px-2.5 py-1 text-xs font-data-mono text-secondary">
                                {{ Str::slug($permission->name) }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-secondary">{{ $permission->created_at->format('d M Y') }}</td>
                        <td class="py-4 px-4 text-right">
                            <div class="inline-flex items-center justify-end gap-space-xs">
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus permission ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">vpn_key</span>
                                <span class="font-body-sm text-body-sm">Belum ada permission terdaftar</span>
                                <span class="text-xs text-secondary">Klik "Tambah Permission" untuk membuat permission baru.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($permissions->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high flex items-center justify-between gap-space-sm">
            <span class="text-xs text-secondary">Menampilkan {{ $permissions->firstItem() }}–{{ $permissions->lastItem() }} dari {{ $permissions->total() }}</span>
            <div class="flex items-center gap-space-xs">
                {{ $permissions->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
