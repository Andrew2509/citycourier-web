@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Top Breadcrumb & Page Action Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-secondary font-label-md text-label-md">
                <span>Sistem &amp; Keamanan</span>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface font-semibold">Manajemen User &amp; Hak Akses</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight">Manajemen User &amp; Hak Akses</h1>
            <p class="font-body-md text-body-md text-secondary">Kelola kredensial akun pengguna, status otentikasi staf, kurir armada, dan hierarki perizinan role sistem.</p>
        </div>
        <div class="flex items-center gap-space-sm self-start md:self-auto">
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-surface-container-lowest text-on-surface hover:bg-surface-container-high rounded-lg shadow-sm font-label-md text-label-md transition-all">
                <span class="material-symbols-outlined text-[18px] text-secondary">file_download</span>
                <span>Ekspor Akun</span>
            </a>
            <a href="{{ route('admin.users.create') }}" class="flex items-center gap-space-xs px-space-md py-space-xs h-9 bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-sm transition-all">
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>+ Tambah Pengguna Baru</span>
            </a>
        </div>
    </div>

    <!-- Operational Metrics Bento Overview -->
    @php
        $totalUsers = $users->total();
        $adminCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'admin') || $u->roles->contains('name', 'super-admin'))->count();
        $courierCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'courier'))->count();
        $customerCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'customer'))->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-base">
        <div class="p-space-lg rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-md hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Total Pengguna</span>
                    <span class="font-display-lg text-display-lg text-on-surface mt-space-2xs">{{ $totalUsers }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary-container">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <span class="inline-flex items-center text-label-sm font-label-sm px-space-xs py-space-2xs rounded-lg bg-emerald-50 text-emerald-700">
                    <span class="material-symbols-outlined text-[14px] mr-0.5">verified_user</span> 100% Aktif
                </span>
            </div>
        </div>
        <div class="p-space-lg rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-md hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Admin</span>
                    <span class="font-display-lg text-display-lg text-on-surface mt-space-2xs">{{ $adminCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-secondary-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[22px]">admin_panel_settings</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <span class="inline-flex items-center text-label-sm font-label-sm px-space-xs py-space-2xs rounded-lg bg-primary-fixed text-on-primary-fixed-variant">Root Access</span>
            </div>
        </div>
        <div class="p-space-lg rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-md hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Kurir</span>
                    <span class="font-display-lg text-display-lg text-on-surface mt-space-2xs">{{ $courierCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-surface-container flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[22px]">two_wheeler</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <span class="inline-flex items-center text-label-sm font-label-sm px-space-xs py-space-2xs rounded-lg bg-blue-50 text-blue-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mr-1 animate-ping"></span> On Duty
                </span>
            </div>
        </div>
        <div class="p-space-lg rounded-xl bg-surface-container-lowest shadow-sm flex flex-col justify-between gap-space-md hover:shadow-md transition-all">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-secondary">Pelanggan</span>
                    <span class="font-display-lg text-display-lg text-on-surface mt-space-2xs">{{ $customerCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-surface-container-low flex items-center justify-center text-secondary">
                    <span class="material-symbols-outlined text-[22px]">person</span>
                </div>
            </div>
            <div class="flex items-center gap-space-xs">
                <span class="inline-flex items-center text-label-sm font-label-sm px-space-xs py-space-2xs rounded-lg bg-emerald-50 text-emerald-800">Terverifikasi</span>
            </div>
        </div>
    </div>

    <!-- Primary Work Surface -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Surface Header with Tab Strip -->
        <div class="px-space-xl pt-space-md bg-surface-container-low/50 flex flex-col gap-space-md">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-space-sm">
                    <div class="w-8 h-8 rounded-lg bg-primary-container/10 text-primary-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
                    </div>
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Direktori Kredensial Pengguna</h2>
                        <p class="font-label-sm text-label-sm text-secondary">Sinkronisasi langsung dengan auth token backend API CityCourier</p>
                    </div>
                </div>
            </div>
            <!-- Segmented Tabs -->
            <div class="flex items-center gap-space-xs">
                <a href="{{ route('admin.users.index') }}" class="px-space-md py-space-sm rounded-t-lg bg-surface-container-lowest text-primary font-headline-sm text-headline-sm flex items-center gap-space-xs shadow-[0_-2px_6px_rgba(0,0,0,0.02)]">
                    <span class="material-symbols-outlined text-[18px] text-primary-container">badge</span>
                    <span>Daftar Pengguna</span>
                    <span class="ml-1 px-space-xs py-space-2xs text-[10px] font-data-mono rounded-full bg-primary-fixed text-on-primary-fixed-variant font-bold">{{ $totalUsers }}</span>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="px-space-md py-space-sm rounded-t-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-all font-body-md text-body-md flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[18px]">security</span>
                    <span>Definisi Role &amp; Hak Akses</span>
                </a>
                <a href="{{ route('admin.permissions.index') }}" class="px-space-md py-space-sm rounded-t-lg text-secondary hover:text-on-surface hover:bg-surface-container transition-all font-body-md text-body-md flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[18px]">key</span>
                    <span>Permissions</span>
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="p-space-lg bg-surface-container-lowest flex flex-col md:flex-row items-stretch md:items-center justify-between gap-space-md">
            <div class="relative flex-1 max-w-lg">
                <span class="material-symbols-outlined absolute left-space-md top-1/2 -translate-y-1/2 text-secondary text-[20px] pointer-events-none">search</span>
                <input class="w-full bg-surface-container-low pl-10 pr-space-md py-space-xs h-10 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest shadow-sm transition-all" id="filter-user-search" placeholder="Cari nama pengguna, email, atau nomor HP..." type="text" onkeyup="filterUserTable()"/>
            </div>
            <div class="flex flex-wrap items-center gap-space-sm">
                <button class="p-space-xs text-secondary hover:text-on-surface hover:bg-surface-container-high rounded-lg transition-colors" title="Muat ulang" onclick="location.reload()">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                </button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse" id="userTable">
                <thead>
                    <tr class="bg-surface-container-low text-secondary font-label-sm text-label-sm uppercase tracking-wider">
                        <th class="py-space-sm px-space-lg">User</th>
                        <th class="py-space-sm px-space-md">Email</th>
                        <th class="py-space-sm px-space-md">Telepon</th>
                        <th class="py-space-sm px-space-md">Role Assigned</th>
                        <th class="py-space-sm px-space-md">Status</th>
                        <th class="py-space-sm px-space-md">Bergabung</th>
                        <th class="py-space-sm px-space-lg text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-container-low font-body-md text-body-md text-on-surface">
                    @forelse ($users as $user)
                    @php
                        $roleColors = [
                            'super-admin' => 'bg-blue-50 text-blue-800',
                            'admin' => 'bg-blue-50 text-blue-800',
                            'courier' => 'bg-blue-100 text-blue-900',
                            'customer' => 'bg-emerald-50 text-emerald-800',
                        ];
                        $roleName = $user->roles->first()?->name ?? 'customer';
                        $roleColor = $roleColors[$roleName] ?? 'bg-surface-container text-secondary';
                        $roleIcons = [
                            'super-admin' => 'shield_person',
                            'admin' => 'admin_panel_settings',
                            'courier' => 'directions_bike',
                            'customer' => 'person',
                        ];
                    @endphp
                    <tr class="hover:bg-surface-container-low/60 transition-colors group">
                        <td class="py-space-md px-space-lg">
                            <div class="flex items-center gap-space-md">
                                <div class="relative shrink-0">
                                    @if($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full object-cover shadow-sm">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary-container to-amber-400 text-on-primary font-headline-sm text-headline-sm flex items-center justify-center shadow-sm">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-surface-container-lowest"></span>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="font-headline-sm text-headline-sm text-on-surface font-semibold truncate group-hover:text-primary transition-colors">{{ $user->name }}</span>
                                    <span class="font-label-sm text-label-sm text-secondary font-data-mono">UID: {{ str_pad($user->id, 3, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md">
                            <div class="flex items-center gap-space-xs font-data-mono text-body-sm">
                                <span class="material-symbols-outlined text-[16px] text-secondary">mail</span>
                                <span class="text-on-surface font-medium">{{ $user->email }}</span>
                            </div>
                        </td>
                        <td class="py-space-md px-space-md">
                            <span class="font-data-mono text-body-sm text-secondary">{{ $user->phone ?? '-' }}</span>
                        </td>
                        <td class="py-space-md px-space-md">
                            <span class="inline-flex items-center gap-1 px-space-sm py-space-2xs rounded-lg text-label-sm font-label-sm {{ $roleColor }} font-semibold">
                                <span class="material-symbols-outlined text-[14px]">{{ $roleIcons[$roleName] ?? 'badge' }}</span>
                                {{ $roleName }}
                            </span>
                        </td>
                        <td class="py-space-md px-space-md">
                            <span class="inline-flex items-center gap-1.5 px-space-xs py-space-2xs rounded-full bg-emerald-50 text-emerald-800 text-label-sm font-label-sm font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Aktif
                            </span>
                        </td>
                        <td class="py-space-md px-space-md text-secondary text-sm">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="py-space-md px-space-lg text-right">
                            <div class="flex items-center justify-end gap-space-xs">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="p-space-xs text-secondary hover:text-primary hover:bg-surface-container rounded-lg transition-colors" title="Edit Pengguna">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-space-xs text-secondary hover:text-error hover:bg-error-container/20 rounded-lg transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-space-2xl px-space-lg text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">group</span>
                                <span class="font-body-sm text-body-sm">Belum ada pengguna terdaftar</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if ($users->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high flex items-center justify-between">
            <span class="font-label-sm text-label-sm text-secondary">Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }}</span>
            <div>{{ $users->withQueryString()->links() }}</div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function filterUserTable() {
    const input = document.getElementById('filter-user-search');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('userTable');
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
