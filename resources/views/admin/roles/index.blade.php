@extends('layouts.admin')

@section('title', 'Manajemen Role & Hak Akses')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Manajemen Role &amp; Hak Akses</h1>
                <span class="badge badge-gray text-xs">
                    {{ $roles->count() }} Role Terdaftar
                </span>
            </div>
            <p class="page-subtitle">Kelola hierarki hak akses dan izin menu untuk setiap jabatan pengguna sistem.</p>
        </div>

        <div class="page-actions">
            @canPerm('roles.create')
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">add</span>
                <span>Tambah Role Baru</span>
            </a>
            @endcanPerm
        </div>
    </div>

    {{-- Tabs Navigasi Sub-Modul --}}
    <div class="card p-3 sm:p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="filter-tabs">
                <a href="{{ route('admin.users.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">group</span>
                    <span>Daftar Pengguna</span>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="filter-tab is-active">
                    <span class="material-symbols-outlined text-[16px]">security</span>
                    <span>Role &amp; Jabatan</span>
                    <span class="filter-count">{{ $roles->count() }}</span>
                </a>
                <a href="{{ route('admin.permissions.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">key</span>
                    <span>Permissions</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Tabel Role --}}
    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Role</th>
                        <th>Jumlah Permission</th>
                        <th>Jumlah Pengguna</th>
                        <th>Dibuat Pada</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                    @php
                        $rn = strtolower($role->name);
                        $badgeClass = match($rn) {
                            'super-admin', 'super admin' => 'badge-red',
                            'admin'                      => 'badge-orange',
                            'courier', 'kurir'           => 'badge-blue',
                            'customer', 'pelanggan'      => 'badge-gray',
                            default                      => 'badge-indigo',
                        };
                        $isProtected = in_array($rn, ['super-admin', 'admin', 'courier', 'customer']);
                    @endphp
                    <tr>
                        {{-- Nama Role --}}
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-700">
                                    <span class="material-symbols-outlined text-[18px]">security</span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[13px] font-semibold text-slate-900">{{ ucfirst($role->name) }}</span>
                                        <span class="badge {{ $badgeClass }} text-[11px]">{{ $role->name }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        ID: #{{ $role->id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Jumlah Permission --}}
                        <td class="whitespace-nowrap">
                            <span class="badge badge-gray font-mono text-xs">
                                <span class="material-symbols-outlined text-[14px] text-slate-400">key</span>
                                {{ $role->permissions_count ?? $role->permissions->count() }} permissions
                            </span>
                        </td>

                        {{-- Jumlah User --}}
                        <td class="whitespace-nowrap font-mono text-xs text-slate-700">
                            {{ $role->users_count ?? $role->users->count() }} user
                        </td>

                        {{-- Dibuat --}}
                        <td class="whitespace-nowrap text-xs text-slate-500 font-mono">
                            {{ $role->created_at ? $role->created_at->format('d/m/Y') : '-' }}
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @canPerm('roles.edit')
                                <a href="{{ route('admin.roles.edit', $role) }}"
                                   class="btn-icon"
                                   title="Edit Role & Permissions">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                @endcanPerm

                                @canPerm('roles.delete')
                                @if(!$isProtected)
                                <form action="{{ route('admin.roles.destroy', $role) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Hapus role {{ $role->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Hapus Role">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endif
                                @endcanPerm
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-12 text-slate-400">
                            <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">security</span>
                            <div class="text-sm font-medium text-slate-600">Belum ada role terdaftar</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
