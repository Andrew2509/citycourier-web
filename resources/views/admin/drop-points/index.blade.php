@extends('layouts.admin')

@section('title', 'Manajemen Drop Point')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Drop Point</h1>
            <p class="text-sm text-slate-400 mt-1">Kelola lokasi kantor dan agen City Courier</p>
        </div>
        <a href="{{ route('admin.drop-points.create') }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Drop Point
        </a>
    </div>

    <!-- Drop Points Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-xl">pin_drop</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Daftar Drop Point</h4>
                    <p class="text-xs text-slate-400">Total: {{ $dropPoints->total() }} lokasi</p>
                </div>
            </div>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                <input type="text" class="bg-slate-50 border border-slate-200 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary w-64" placeholder="Cari drop point...">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Nama Kantor</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Alamat</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Telepon</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Koordinat</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($dropPoints as $point)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-info/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-info text-xl">apartment</span>
                                </div>
                                <span class="text-sm font-semibold text-slate-700">{{ $point->name }}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-500 truncate max-w-[250px]">{{ $point->address }}</td>
                        <td class="py-3.5 px-5 text-sm text-slate-600">{{ $point->phone ?? '-' }}</td>
                        <td class="py-3.5 px-5">
                            @if($point->latitude && $point->longitude)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-mono bg-slate-100 text-slate-600">
                                    {{ number_format($point->latitude, 4) }}, {{ number_format($point->longitude, 4) }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400 italic">Tidak ada koordinat</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-5">
                            <form action="{{ route('admin.drop-points.toggle-active', $point) }}" method="POST" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold transition-all {{ $point->is_active ? 'bg-success/10 text-success hover:bg-success/20' : 'bg-slate-100 text-slate-400 hover:bg-slate-200' }}">
                                    <span class="material-symbols-outlined text-[12px]">{{ $point->is_active ? 'check_circle' : 'cancel' }}</span>
                                    {{ $point->is_active ? 'Aktif' : 'Non-aktif' }}
                                </button>
                            </form>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.drop-points.edit', $point) }}" class="p-2 rounded-lg text-slate-400 hover:text-info hover:bg-info/5 transition-all" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form action="{{ route('admin.drop-points.destroy', $point) }}" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-error hover:bg-error/5 transition-all" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">pin_drop</span>
                                <h3 class="text-base font-semibold text-slate-600">Belum ada drop point</h3>
                                <p class="text-sm text-slate-400 mt-1">Data drop point akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($dropPoints->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $dropPoints->firstItem() }}-{{ $dropPoints->lastItem() }} dari {{ $dropPoints->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $dropPoints->links() }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
