@extends('layouts.admin')

@section('title', 'Manajemen Role')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <span>Sistem &amp; Keamanan</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface font-semibold">Manajemen Role</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Manajemen Role &amp; Hak Akses</h1>
            <p class="font-body-sm text-body-sm text-secondary">Konfigurasi peran dan hierarki perizinan pengguna sistem CityCourier</p>
        </div>
        <div class="flex items-center gap-space-sm self-start md:self-auto">
            <a href="{{ route('admin.roles.create') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Role</span>
            </a>
        </div>
    </div>

    <!-- Roles Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="px-space-xl py-space-md flex items-center justify-between bg-surface-container-low/50">
            <div class="flex items-center gap-space-sm">
                <div class="w-8 h-8 rounded-lg bg-primary-container/10 text-primary-container flex items-center justify-center">
                    <span class="material-symbols-outlined text-[20px]">security</span>
                </div>
                <div>
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Daftar Role</h2>
                    <p class="font-label-sm text-label-sm text-secondary">{{ $roles->count() }} role terdaftar dalam sistem</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-space-sm px-space-lg">Role</th>
                        <th class="py-space-sm px-space-md">Jumlah Permission</th>
                        <th class="py-space-sm px-space-md">Dibuat</th>
                        <th class="py-space-sm px-space-lg text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-high font-body-md text-body-md text-on-surface">
                    @forelse ($roles as $role)
                    @php
                        $roleBadgeColors = [
                            'super-admin' => 'bg-blue-50 text-blue-800',
                            'admin' => 'bg-primary-fixed text-on-primary-fixed-variant',
                            'courier' => 'bg-emerald-50 text-emerald-800',
                            'customer' => 'bg-secondary-container text-on-secondary-container',
                        ];
                    @endphp
                    <tr class="hover:bg-surface-container-low/60 transition-colors group">
                        <td class="py-space-md px-space-lg">
                            <div class="flex items-center gap-space-md">
                                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant shrink-0 shadow-sm">
                                    <span class="material-symbols-outlined text-[20px]">badge</span>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate">{{ ucfirst($role->name) }}</span>
                                    <span class="px-space-xs py-space-2xs rounded-full text-[10px] font-label-sm font-bold {{ $roleBadgeColors[$role->name] ?? 'bg-surface-container text-secondary' }} inline-flex items-center gap-1 w-fit mt-0.5">
                                        <span class="material-symbols-outlined text-[10px]">shield</span>
                                        {{ ucfirst($role->name) }} Role
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md">
                            <span class="inline-flex items-center gap-space-xs text-secondary font-body-sm">
                                <span class="material-symbols-outlined text-[16px]">key</span>
                                {{ $role->permissions->count() ?? 0 }} permission
                            </span>
                        </td>
                        <td class="py-space-md px-space-md text-secondary text-sm">{{ $role->created_at->format('d M Y') }}</td>
                        <td class="py-space-md px-space-lg text-right">
                            <div class="inline-flex items-center justify-end gap-space-xs">
                                <a href="{{ route('admin.roles.edit', $role->id) }}" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-colors" title="Edit Role">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.roles.destroy', $role->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus role ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-space-xs text-secondary hover:text-error hover:bg-error-container/20 rounded-lg transition-colors" title="Hapus Role">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-space-2xl px-space-lg text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">shield</span>
                                <span class="font-body-sm text-body-sm">Belum ada role terdaftar</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high flex items-center justify-between">
            <span class="font-label-sm text-label-sm text-secondary">Menampilkan {{ $roles->firstItem() }}–{{ $roles->lastItem() }} dari {{ $roles->total() }}</span>
            <div>{{ $roles->withQueryString()->links() }}</div>
        </div>
        @endif
    </div>
</div>
@endsection
