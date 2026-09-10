@extends('layouts.admin')

@section('title', 'Manajemen Role')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#059669]/10">
                <span class="material-symbols-outlined text-[26px] text-[#059669]">shield</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Role</h1>
                <p class="text-sm text-gray-500">Konfigurasi peran dan hak akses pengguna</p>
            </div>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#059669] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#047857] focus:outline-none focus:ring-2 focus:ring-[#059669] focus:ring-offset-2">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Role
        </a>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Role</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Jumlah Permission</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Dibuat</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($roles as $role)
                        <tr class="transition hover:bg-gray-50/50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#059669]/10">
                                        <span class="material-symbols-outlined text-[18px] text-[#059669]">badge</span>
                                    </div>
                                    <span class="font-medium text-gray-900">{{ ucfirst($role->name) }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 text-gray-600">
                                    <span class="material-symbols-outlined text-[16px]">key</span>
                                    {{ $role->permissions->count() ?? 0 }} permission
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $role->created_at->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.roles.edit', $role->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus role ini?')">
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
                                <span class="material-symbols-outlined mx-auto mb-3 block text-[48px] text-gray-300">shield</span>
                                <p class="text-sm font-medium text-gray-500">Belum ada role terdaftar</p>
                                <p class="mt-1 text-xs text-gray-400">Klik tombol "Tambah Role" untuk membuat role baru.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
            <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-3">
                {{ $roles->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
