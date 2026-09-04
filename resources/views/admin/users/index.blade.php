@extends('layouts.admin')

@section('title', 'Manajemen User')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen User</h1>
            <p class="text-sm text-slate-400 mt-1">Kelola User dan Assign Role</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            Tambah User
        </a>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">group</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Daftar User</h4>
                    <p class="text-xs text-slate-400">Total: {{ $users->total() }} user</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">User</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Email</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Telepon</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Alamat</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Role(s)</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('assets/images/default-avatar.png') }}"
                                     alt="Avatar"
                                     class="w-10 h-10 rounded-full object-cover border border-surface-border">
                                <div>
                                    <div class="text-sm font-semibold text-slate-700">{{ $user->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-sm text-slate-600">{{ $user->email }}</td>
                        <td class="py-3.5 px-5 text-sm text-slate-600">{{ $user->phone ?? '-' }}</td>
                        <td class="py-3.5 px-5 text-xs text-slate-400 truncate max-w-[200px]">{{ $user->address ?? '-' }}</td>
                        <td class="py-3.5 px-5">
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-info/10 text-info">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.users.edit', $user) }}" class="p-2 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Yakin hapus user ini?');">
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

        <!-- Pagination -->
        @if($users->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $users->firstItem() }}-{{ $users->lastItem() }} dari {{ $users->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $users->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
