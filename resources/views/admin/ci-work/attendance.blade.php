@extends('layouts.admin')

@section('title', 'Presensi Kurir')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Presensi Kurir</h1>
        <p class="text-sm text-slate-400 mt-1">Daftar kurir yang sedang aktif dan siap menerima pesanan</p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">fingerprint</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Monitoring Kehadiran</h4>
                    <p class="text-xs text-slate-400">Real-time</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kurir</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Telepon</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Lokasi</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($couriers as $courier)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm">
                                        {{ strtoupper(substr($courier->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white" style="background: {{ $courier->is_active ? '#4CAF50' : '#94A3B8' }}"></span>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-700">{{ $courier->user->name ?? '-' }}</div>
                                    <div class="text-xs text-slate-400">ID: {{ $courier->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-sm text-slate-600">{{ $courier->phone }}</td>
                        <td class="py-3.5 px-5">
                            @if($courier->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-success/10 text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success animate-pulse"></span>
                                    Online
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Offline
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-5">
                            @if($courier->latitude && $courier->longitude)
                                <a href="https://www.google.com/maps?q={{ $courier->latitude }},{{ $courier->longitude }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-primary bg-primary/5 hover:bg-primary/10 transition-all">
                                    <span class="material-symbols-outlined text-[14px]">map</span>
                                    Lihat Map
                                </a>
                            @else
                                <span class="text-xs text-slate-400">Tidak tersedia</span>
                            @endif
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <a href="{{ route('admin.couriers') }}?search={{ $courier->user->name }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-500 bg-slate-100 hover:bg-slate-200 transition-all">
                                <span class="material-symbols-outlined text-[14px]">person</span>
                                Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">group</span>
                                <h3 class="text-base font-semibold text-slate-600">Belum ada kurir terdaftar</h3>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($couriers->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $couriers->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
