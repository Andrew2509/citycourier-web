@extends('layouts.admin')

@section('title', 'Manajemen Permission')

@section('content')
@php
    $totalPermissions = $permissions->total();
    $roleCount = \Spatie\Permission\Models\Role::count();
@endphp

<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Manajemen Permission</h1>
                <span class="badge badge-gray text-xs">
                    {{ $totalPermissions }} Permission Terdaftar
                </span>
            </div>
            <p class="page-subtitle">Daftar kunci wewenang sistem granular yang dapat dipasangkan ke setiap role pengguna.</p>
        </div>

        <div class="page-actions">
            @canPerm('permissions.create')
            <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">add</span>
                <span>Tambah Permission</span>
            </a>
            @endcanPerm
        </div>
    </div>

    {{-- Tabs Navigasi Sub-Modul --}}
    <div class="card p-3 sm:p-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="filter-tabs">
                <a href="{{ route('admin.users.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">group</span>
                    <span>Daftar Pengguna</span>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">security</span>
                    <span>Role &amp; Jabatan</span>
                </a>
                <a href="{{ route('admin.permissions.index') }}" class="filter-tab is-active">
                    <span class="material-symbols-outlined text-[16px]">key</span>
                    <span>Permissions</span>
                    <span class="filter-count">{{ $totalPermissions }}</span>
                </a>
            </div>

            <form method="GET" action="{{ route('admin.permissions.index') }}" class="flex items-center gap-2">
                <div class="search-box w-full sm:w-64">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari permission..."
                           class="form-input">
                </div>
                <button type="submit" class="btn btn-secondary">Cari</button>
            </form>
        </div>
    </div>

    {{-- Tabel Permission --}}
    <div class="card overflow-hidden">
        @if($permissions->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama Permission (Key)</th>
                        <th>Guard</th>
                        <th>Digunakan Oleh Role</th>
                        <th>Dibuat Pada</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $perm)
                    @php
                        $parts = explode('.', $perm->name);
                        $module = $parts[0] ?? 'system';
                        $action = $parts[1] ?? 'action';
                    @endphp
                    <tr>
                        {{-- Nama Key --}}
                        <td>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-indigo text-xs font-mono">
                                    {{ $module }}
                                </span>
                                <span class="font-mono text-[13px] font-semibold text-slate-900">
                                    {{ $perm->name }}
                                </span>
                            </div>
                        </td>

                        {{-- Guard --}}
                        <td class="whitespace-nowrap font-mono text-xs text-slate-500">
                            {{ $perm->guard_name }}
                        </td>

                        {{-- Digunakan Oleh Role --}}
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse($perm->roles as $r)
                                    <span class="badge badge-gray text-[11px]">
                                        {{ $r->name }}
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400 italic">Belum dipasangkan</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- Dibuat --}}
                        <td class="whitespace-nowrap font-mono text-xs text-slate-500">
                            {{ $perm->created_at ? $perm->created_at->format('d/m/Y') : '-' }}
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @canPerm('permissions.edit')
                                <a href="{{ route('admin.permissions.edit', $perm) }}"
                                   class="btn-icon"
                                   title="Edit Permission">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                @endcanPerm

                                @canPerm('permissions.delete')
                                <form action="{{ route('admin.permissions.destroy', $perm) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Hapus permission {{ $perm->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Hapus Permission">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endcanPerm
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            <div>
                Menampilkan <strong>{{ $permissions->firstItem() ?? 0 }}</strong> - <strong>{{ $permissions->lastItem() ?? 0 }}</strong> dari <strong>{{ $permissions->total() }}</strong> permission
            </div>
            <div>
                {{ $permissions->withQueryString()->links() }}
            </div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">key</span>
            <div class="empty-title">Tidak ada permission ditemukan</div>
            <div class="empty-text">Buat permission baru untuk memperluas sistem wewenang peran.</div>
        </div>
        @endif
    </div>
</div>
@endsection
