@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#059669]/10">
                <span class="material-symbols-outlined text-[26px] text-[#059669]">group</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen User</h1>
                <p class="text-sm text-gray-500">Kelola kredensial akun pengguna dan hak akses</p>
            </div>
        </div>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-[#059669] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#047857] focus:outline-none focus:ring-2 focus:ring-[#059669] focus:ring-offset-2">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            Tambah Pengguna Baru
        </a>
    </div>

    {{-- Stat Cards --}}
    @php
        $totalUsers = $users->total();
        $adminCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'admin'))->count();
        $courierCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'courier'))->count();
        $customerCount = $users->getCollection()->filter(fn($u) => $u->roles->contains('name', 'customer'))->count();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#059669]/10">
                    <span class="material-symbols-outlined text-[22px] text-[#059669]">group</span>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Pengguna</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $totalUsers }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#059669]/10">
                    <span class="material-symbols-outlined text-[22px] text-[#059669]">admin_panel_settings</span>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Admin</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $adminCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#059669]/10">
                    <span class="material-symbols-outlined text-[22px] text-[#059669]">delivery_dining</span>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Kurir</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $courierCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-[#059669]/10">
                    <span class="material-symbols-outlined text-[22px] text-[#059669]">person</span>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pelanggan</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $customerCount }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50">
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pengguna</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Telepon</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Bergabung</th>
                        <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($users as $user)
                        <tr class="transition hover:bg-gray-50/50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-full object-cover">
                                    @else
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#059669]/10 text-sm font-semibold text-[#059669]">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $user->phone ?? '-' }}</td>
                            <td class="px-6 py-4">
                                @php
                                    $roleColors = [
                                        'admin' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        'courier' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
                                        'customer' => 'bg-slate-50 text-slate-700 ring-slate-600/20',
                                    ];
                                    $roleName = $user->roles->first()?->name ?? 'customer';
                                    $roleColor = $roleColors[$roleName] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20';
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $roleColor }}">
                                    {{ ucfirst($roleName) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus user ini?')">
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
                            <td colspan="6" class="px-6 py-16 text-center">
                                <span class="material-symbols-outlined mx-auto mb-3 block text-[48px] text-gray-300">group</span>
                                <p class="text-sm font-medium text-gray-500">Belum ada pengguna terdaftar</p>
                                <p class="mt-1 text-xs text-gray-400">Klik tombol "Tambah Pengguna Baru" untuk menambahkan pengguna pertama.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-gray-200 bg-gray-50/50 px-6 py-3">
                {{ $users->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
