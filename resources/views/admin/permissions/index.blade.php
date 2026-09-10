@extends('layouts.admin')

@section('title', 'Manajemen Permission')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10">
                <span class="material-symbols-outlined text-[26px] text-primary">vpn_key</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Manajemen Permission</h1>
                <p class="text-sm text-slate-400 mt-0.5">Kelola hak akses granular untuk setiap fitur sistem</p>
            </div>
        </div>
        <a href="{{ route('admin.permissions.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white shadow-md shadow-primary/20 hover:shadow-lg hover:shadow-primary/30 transition-all">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Permission
        </a>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Permission</th>
                        <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Kode</th>
                        <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Dibuat</th>
                        <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($permissions as $permission)
                        <tr class="transition hover:bg-slate-50/50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px] text-primary">key</span>
                                    </div>
                                    <span class="font-semibold text-slate-700">{{ $permission->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-lg bg-slate-100 border border-slate-200 px-2.5 py-1 text-xs font-mono text-slate-500">
                                    {{ Str::slug($permission->name) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-500">{{ $permission->created_at->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.permissions.edit', $permission) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-primary-50 hover:text-primary-700">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus permission ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 shadow-sm transition hover:bg-red-50">
                                            <span class="material-symbols-outlined text-[15px]">delete</span>
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <span class="material-symbols-outlined mx-auto mb-3 block text-[48px] text-slate-300">vpn_key</span>
                                <p class="text-sm font-medium text-slate-500">Belum ada permission terdaftar</p>
                                <p class="mt-1 text-xs text-slate-400">Klik tombol "Tambah Permission" untuk membuat permission baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($permissions->hasPages())
            <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-3 flex items-center justify-between gap-4 flex-wrap">
                <span class="text-xs text-slate-400">Menampilkan {{ $permissions->firstItem() }}–{{ $permissions->lastItem() }} dari {{ $permissions->total() }}</span>
                <div class="flex items-center gap-1.5">
                    {{ $permissions->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection