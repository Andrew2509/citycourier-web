@extends('layouts.admin')

@section('title', 'Manajemen User & Hak Akses')

@section('content')
@php
    $totalUsers = $users->total();
    $collection = $users->getCollection();
    $adminCount = $collection->filter(fn($u) => $u->roles->contains('name', 'admin') || $u->roles->contains('name', 'super-admin'))->count();
    $courierCount = $collection->filter(fn($u) => $u->roles->contains('name', 'courier'))->count();
    $customerCount = $collection->filter(fn($u) => $u->roles->contains('name', 'customer'))->count();
@endphp

<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Manajemen User &amp; Hak Akses</h1>
                <span class="badge badge-gray text-xs">
                    Total: <strong class="text-slate-900 ml-1">{{ $totalUsers }}</strong> akun
                </span>
            </div>
            <p class="page-subtitle">Kelola akun pengguna, peran administrator, kurir armada, dan kredensial akses sistem.</p>
        </div>

        <div class="page-actions">
            @canPerm('users.create')
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <span class="material-symbols-outlined">person_add</span>
                <span>Tambah Pengguna</span>
            </a>
            @endcanPerm
        </div>
    </div>

    {{-- Ringkasan Pengguna (KPIs) --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-3.5">
            <div class="stat-label">Total Pengguna</div>
            <div class="stat-value">{{ $totalUsers }}</div>
            <div class="stat-meta text-emerald-700 font-medium">Terdaftar di sistem</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Administrator</div>
            <div class="stat-value text-brand-700">{{ $adminCount }}</div>
            <div class="stat-meta">Akses penuh admin</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Kurir Armada</div>
            <div class="stat-value text-blue-700">{{ $courierCount }}</div>
            <div class="stat-meta">Akun kurir aktif</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Pelanggan</div>
            <div class="stat-value text-slate-800">{{ $customerCount }}</div>
            <div class="stat-meta">Konsumen terdaftar</div>
        </div>
    </div>

    {{-- Tabs Navigasi Sub-Modul & Pencarian --}}
    <div class="card p-3 sm:p-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="filter-tabs">
                <a href="{{ route('admin.users.index') }}" class="filter-tab is-active">
                    <span class="material-symbols-outlined text-[16px]">group</span>
                    <span>Daftar Pengguna</span>
                    <span class="filter-count">{{ $totalUsers }}</span>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">security</span>
                    <span>Role &amp; Jabatan</span>
                </a>
                <a href="{{ route('admin.permissions.index') }}" class="filter-tab">
                    <span class="material-symbols-outlined text-[16px]">key</span>
                    <span>Permissions</span>
                </a>
            </div>

            <div class="search-box w-full sm:w-72">
                <span class="material-symbols-outlined">search</span>
                <input type="text"
                       id="filter-user-search"
                       onkeyup="filterUserTable()"
                       placeholder="Cari nama, email, hp..."
                       class="form-input">
            </div>
        </div>
    </div>

    {{-- Tabel User --}}
    <div class="card overflow-hidden">
        @if($users->count() > 0)
        <div class="table-wrap">
            <table class="data-table" id="userTable">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Email Akun</th>
                        <th>No. Telepon / WhatsApp</th>
                        <th>Role &amp; Hak Akses</th>
                        <th>Status</th>
                        <th>Terdaftar</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    @php
                        $userRoleNames = $user->roles->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                        $isSuper = in_array('super-admin', $userRoleNames) || in_array('admin', $userRoleNames);
                        $isCourier = in_array('courier', $userRoleNames);
                    @endphp
                    <tr>
                        {{-- Pengguna (Avatar & Nama) --}}
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="avatar bg-brand-50 text-brand-700 font-bold shrink-0">
                                    @if($user->avatar)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar) }}"
                                             alt="{{ $user->name }}"
                                             class="h-full w-full object-cover rounded-full">
                                    @else
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="text-[13px] font-semibold text-slate-900 truncate">
                                        {{ $user->name }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        ID: #{{ $user->id }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Email --}}
                        <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                            {{ $user->email }}
                        </td>

                        {{-- Telepon --}}
                        <td class="whitespace-nowrap font-mono text-xs text-slate-600">
                            {{ $user->phone ?? '-' }}
                        </td>

                        {{-- Roles --}}
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                @php
                                    $rn = strtolower($role->name);
                                    $roleBadge = match($rn) {
                                        'super-admin', 'super admin' => 'badge-red',
                                        'admin'                      => 'badge-orange',
                                        'courier', 'kurir'           => 'badge-blue',
                                        'customer', 'pelanggan'      => 'badge-gray',
                                        default                      => 'badge-indigo',
                                    };
                                @endphp
                                <span class="badge {{ $roleBadge }}">
                                    {{ ucfirst($role->name) }}
                                </span>
                                @empty
                                <span class="badge badge-gray text-[10px]">Tanpa Role</span>
                                @endforelse
                            </div>
                        </td>

                        {{-- Status --}}
                        <td class="whitespace-nowrap">
                            <span class="badge badge-green">
                                <span class="badge-dot"></span>
                                Aktif
                            </span>
                        </td>

                        {{-- Tanggal Bergabung --}}
                        <td class="whitespace-nowrap text-xs text-slate-500 font-mono">
                            {{ $user->created_at ? $user->created_at->format('d/m/Y') : '-' }}
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @canPerm('users.edit')
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="btn-icon"
                                   title="Edit Pengguna">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                @endcanPerm

                                @canPerm('users.delete')
                                @if(auth()->id() !== $user->id)
                                <form action="{{ route('admin.users.destroy', $user) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Hapus akun pengguna {{ $user->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Hapus Akun">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endif
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
                Menampilkan <strong>{{ $users->firstItem() ?? 0 }}</strong> - <strong>{{ $users->lastItem() ?? 0 }}</strong> dari <strong>{{ $users->total() }}</strong> pengguna
            </div>
            <div>
                {{ $users->withQueryString()->links() }}
            </div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">group</span>
            <div class="empty-title">Tidak ada pengguna ditemukan</div>
            <div class="empty-text">Tambahkan pengguna baru untuk memberikan akses sistem.</div>
        </div>
        @endif
    </div>
</div>

<script>
function filterUserTable() {
    const input = document.getElementById('filter-user-search');
    const filter = (input ? input.value : '').toLowerCase().trim();
    const rows = document.querySelectorAll('#userTable tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
</script>
@endsection
