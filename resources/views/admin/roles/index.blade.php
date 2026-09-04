@extends('layouts.admin')

@section('title', 'Manajemen Role')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Role</h1>
            <p class="text-sm text-slate-400 mt-1">Kelola Role pengguna</p>
        </div>
        <a href="{{ route('admin.roles.create') }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Role
        </a>
    </div>

    <!-- Roles Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">admin_panel_settings</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Daftar Role</h4>
                    <p class="text-xs text-slate-400">Total: {{ $roles->total() }} role</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">ID</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Nama Role</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($roles as $role)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-5 text-sm text-slate-500 font-mono">{{ $role->id }}</td>
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-info/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-info text-sm">shield</span>
                                </div>
                                <span class="text-sm font-semibold text-slate-700">{{ $role->name }}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.roles.edit', $role) }}" class="p-2 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" class="inline" onsubmit="return confirm('Yakin hapus role ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-error hover:bg-error/5 transition-all" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($roles->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $roles->firstItem() }}-{{ $roles->lastItem() }} dari {{ $roles->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $roles->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
