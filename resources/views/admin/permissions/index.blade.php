@extends('layouts.admin')

@section('title', 'Manajemen Permission')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md">
                <span class="material-symbols-outlined text-[16px]">admin_panel_settings</span>
                <span>Sistem &amp; Keamanan</span>
                <span class="text-[12px] opacity-60">/</span>
                <span class="text-on-surface font-semibold">Manajemen Permission</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Manajemen Permission &amp; Hak Akses</h1>
            <p class="font-body-sm text-body-sm text-secondary">Kelola wewenang granular spatie/laravel-permission, guard tokens, dan matriks hak akses antar peran.</p>
        </div>
        <div class="flex items-center gap-space-sm self-start md:self-auto">
            <a href="{{ route('admin.permissions.index') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest text-on-surface hover:bg-surface-container-high font-label-md text-label-md rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">sync</span>
                <span>Sinkronisasi</span>
            </a>
            <a href="{{ route('admin.permissions.create') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Permission</span>
            </a>
        </div>
    </div>

    <!-- KPI Summary Banner -->
    @php
        $totalPermissions = $permissions->total();
        $roleCount = \Spatie\Permission\Models\Role::count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-md">
        <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex items-center justify-between">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Total Permission</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg font-bold text-on-surface">{{ $totalPermissions }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">Hak Akses</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[22px]">vpn_key</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex items-center justify-between">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Role Aktif</span>
                <div class="flex items-baseline gap-space-xs">
                    <span class="font-display-lg text-display-lg font-bold text-on-surface">{{ $roleCount }}</span>
                    <span class="font-label-sm text-label-sm text-secondary">Kelompok</span>
                </div>
            </div>
            <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center text-on-secondary-container">
                <span class="material-symbols-outlined text-[22px]">groups</span>
            </div>
        </div>
        <div class="bg-surface-container-lowest p-space-md rounded-xl shadow-sm flex items-center justify-between">
            <div class="flex flex-col gap-space-2xs">
                <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary font-bold">Metode Otentikasi</span>
                <div class="flex items-center gap-space-xs">
                    <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Sanctum Token</span>
                </div>
                <span class="font-label-sm text-label-sm text-emerald-700 font-semibold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> RBAC Enforcement
                </span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-surface-container-high flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-[22px]">verified_user</span>
            </div>
        </div>
    </div>

    <!-- Primary Workspace with Tabs -->
    <div class="flex flex-col bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <!-- Tab Controls -->
        <div class="flex flex-col md:flex-row md:items-center justify-between p-space-md gap-space-md bg-surface-container-low/50">
            <div class="inline-flex p-1 bg-surface-container-high rounded-xl gap-1">
                <button class="px-space-md py-space-xs rounded-lg font-label-md text-label-md font-semibold transition-all bg-surface-container-lowest text-on-surface shadow-sm flex items-center gap-space-xs" id="tab-btn-list" onclick="switchTab('list')">
                    <span class="material-symbols-outlined text-[16px] text-primary">key</span>
                    <span>Daftar Permission</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-primary-fixed text-on-primary-fixed-variant font-bold">{{ $totalPermissions }}</span>
                </button>
            </div>
            <div class="flex items-center gap-space-sm">
                <div class="relative w-full sm:w-64">
                    <span class="material-symbols-outlined absolute left-space-sm top-1/2 -translate-y-1/2 text-secondary text-[18px]">search</span>
                    <input class="w-full bg-surface-container-lowest pl-9 pr-space-md py-1.5 h-9 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none shadow-sm" id="permission-search" placeholder="Cari izin / modul..." type="text" onkeyup="filterPermTable()"/>
                </div>
            </div>
        </div>

        <!-- Permission Table -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" id="permTable">
                <thead>
                    <tr class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider select-none">
                        <th class="py-space-sm px-space-lg w-16">ID</th>
                        <th class="py-space-sm px-space-md">NAMA PERMISSION</th>
                        <th class="py-space-sm px-space-md">KODE</th>
                        <th class="py-space-sm px-space-md">Dibuat</th>
                        <th class="py-space-sm px-space-lg text-right w-24">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-high font-body-sm text-body-sm text-on-surface">
                    @forelse ($permissions as $permission)
                    <tr class="hover:bg-surface-container-low/70 transition-colors group">
                        <td class="py-space-md px-space-lg font-data-mono text-secondary">{{ $loop->iteration }}</td>
                        <td class="py-space-md px-space-md">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[16px]">key</span>
                                </div>
                                <span class="font-data-mono font-semibold text-on-surface">{{ $permission->name }}</span>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md">
                            <span class="px-space-xs py-space-2xs rounded-lg font-label-sm text-label-sm bg-surface-container-high text-on-surface-variant font-data-mono">{{ Str::slug($permission->name) }}</span>
                        </td>
                        <td class="py-space-md px-space-md text-secondary">{{ $permission->created_at->format('d M Y') }}</td>
                        <td class="py-space-md px-space-lg text-right">
                            <div class="inline-flex items-center gap-space-2xs text-secondary">
                                <a href="{{ route('admin.permissions.edit', $permission) }}" class="p-1 hover:text-primary hover:bg-surface-container-high rounded-lg transition-colors" title="Edit Permission">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus permission ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 hover:text-error hover:bg-error-container/40 rounded-lg transition-colors" title="Hapus Permission">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-space-2xl px-space-lg text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">vpn_key</span>
                                <span class="font-body-sm text-body-sm">Belum ada permission terdaftar</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($permissions->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high flex items-center justify-between">
            <span class="text-xs text-secondary">Menampilkan {{ $permissions->firstItem() }}–{{ $permissions->lastItem() }} dari {{ $permissions->total() }}</span>
            <div>{{ $permissions->links() }}</div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function filterPermTable() {
    const input = document.getElementById('permission-search');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('permTable');
    if (!table) return;
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.indexOf(filter) > -1 ? '' : 'none';
    }
}
</script>
@endpush
@endsection