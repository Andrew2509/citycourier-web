@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">group</span>
                <span>Sistem & Keamanan</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen User</h1>
            <p class="font-body-md text-body-md text-secondary">Kelola kredensial akun pengguna dan hak akses</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="h-9 px-space-md rounded-lg bg-primary-container hover:bg-primary text-on-primary shadow-sm flex items-center gap-space-xs font-label-md text-label-md font-semibold transition-colors">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Tambah Pengguna Baru</span>
        </a>
    </div>

    <!-- Stat Cards -->
    @php
        $totalUsers = $users->total();
        $adminCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'admin'))->count();
        $courierCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'courier'))->count();
        $customerCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'customer'))->count();
    @endphp

    <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Total Pengguna</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $totalUsers }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                    <span class="material-symbols-outlined text-[22px]">group</span>
                </div>
            </div>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Admin</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $adminCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed-variant shrink-0">
                    <span class="material-symbols-outlined text-[22px]">admin_panel_settings</span>
                </div>
            </div>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Kurir</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $courierCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">delivery_dining</span>
                </div>
            </div>
        </div>
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Pelanggan</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $customerCount }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">person</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Pengguna</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Email</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Telepon</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Role</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Bergabung</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse ($users as $user)
                    <tr class="hover:bg-surface transition-colors group">
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-space-sm">
                                @if ($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-full object-cover">
                                @else
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-container text-on-primary font-bold text-sm">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                @endif
                                <span class="font-medium text-on-surface">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 text-secondary">{{ $user->email }}</td>
                        <td class="py-4 px-4 text-secondary">{{ $user->phone ?? '-' }}</td>
                        <td class="py-4 px-4">
                            @php
                                $roleColors = [
                                    'admin' => 'bg-emerald-50 text-emerald-700',
                                    'courier' => 'bg-blue-50 text-blue-700',
                                    'customer' => 'bg-surface-container text-secondary',
                                ];
                                $roleName = $user->roles->first()?->name ?? 'customer';
                                $roleColor = $roleColors[$roleName] ?? 'bg-surface-container text-secondary';
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $roleColor }}">
                                {{ ucfirst($roleName) }}
                            </span>
                        </td>
                        <td class="py-4 px-4 text-secondary">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="py-4 px-4 text-right">
                            <div class="inline-flex items-center justify-end gap-space-xs">
                                <a href="{{ route('admin.users.edit', $user->id) }}" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
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
                        <td colspan="6" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">group</span>
                                <span class="font-body-sm text-body-sm">Belum ada pengguna terdaftar</span>
                                <span class="text-xs text-secondary">Klik "Tambah Pengguna Baru" untuk menambahkan pengguna pertama.</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $users->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
