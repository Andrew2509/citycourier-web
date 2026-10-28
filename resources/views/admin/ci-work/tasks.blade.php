@extends('layouts.admin')

@section('title', 'Manajemen Tugas Pengiriman')

@section('content')
<div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-50 text-primary-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary-600 animate-pulse"></span>
                    Pipeline Pengiriman
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Tugas Kurir Lapangan</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Manajemen Tugas Pengiriman</h1>
            <p class="text-xs text-slate-500 mt-0.5">Pantau alokasi tugas, status perjalanan kurir, dan verifikasi bukti serah terima</p>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
            <a href="{{ route('admin.ci-work.index') }}" class="btn btn-secondary text-xs h-9 px-3 gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-slate-500">dashboard</span>
                <span>Cockpit Kerja</span>
            </a>
            <a href="{{ route('admin.ci-work.attendance') }}" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">near_me</span>
                <span>Peta Presensi</span>
            </a>
        </div>
    </div>

    <!-- Stat Cards -->
    @php
        $activeTasks = $tasks->filter(fn($t) => in_array($t->status, ['assigned','picking_up','delivering']))->count();
        $completedTasks = $tasks->filter(fn($t) => $t->status === 'delivered')->count();
        $pendingPhoto = $tasks->filter(fn($t) => !$t->delivery_photo && $t->status === 'delivering')->count();
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Sedang Dikerjakan</span>
                <div class="text-2xl font-bold font-mono text-primary-600 mt-1">{{ $activeTasks }}</div>
                <span class="text-[11px] text-slate-400">Aktif di jalan</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">pending_actions</span>
            </div>
        </div>

        <div class="card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Selesai Hari Ini</span>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $completedTasks }}</div>
                <span class="text-[11px] text-emerald-600 font-semibold">Terkirim ke penerima</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
            </div>
        </div>

        <div class="card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Menunggu Bukti Foto</span>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $pendingPhoto }}</div>
                <span class="text-[11px] text-amber-600 font-semibold">Proof of Delivery</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">add_a_photo</span>
            </div>
        </div>

        <div class="card p-4 flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Rata-rata Durasi</span>
                <div class="text-2xl font-bold font-mono text-slate-900 mt-1">{{ $avgDuration }} <span class="text-xs font-normal text-slate-400">mnt</span></div>
                <span class="text-[11px] text-slate-400">Waktu antar paket</span>
            </div>
            <div class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[20px]">timer</span>
            </div>
        </div>
    </div>

    <!-- Task Table Card -->
    <div class="card overflow-hidden">
        <!-- Filter Tabs -->
        <div class="flex items-center gap-1 px-4 border-b border-slate-200 bg-slate-50/50 overflow-x-auto">
            <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}" class="filter-tab {{ !request('status') ? 'active' : '' }}">
                Semua Tugas
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="filter-tab {{ request('status') === 'active' ? 'active' : '' }}">
                Sedang Dikerjakan
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" class="filter-tab {{ request('status') === 'completed' ? 'active' : '' }}">
                Selesai Terkirim
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No. Resi</th>
                        <th>Kurir</th>
                        <th>Lokasi Jemput</th>
                        <th>Lokasi Tujuan</th>
                        <th>Status</th>
                        <th>Bukti Foto</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                    <tr>
                        <td class="font-mono font-bold text-primary-600">
                            {{ $task->shipment->tracking_number ?? '-' }}
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($task->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <span class="font-medium text-slate-800">{{ $task->courier->user->name ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="max-w-[180px]">
                            <span class="text-xs text-slate-600 line-clamp-1" title="{{ $task->shipment->sender_address ?? '' }}">{{ $task->shipment->sender_address ?? '-' }}</span>
                        </td>
                        <td class="max-w-[180px]">
                            <span class="text-xs text-slate-600 line-clamp-1" title="{{ $task->shipment->receiver_address ?? '' }}">{{ $task->shipment->receiver_address ?? '-' }}</span>
                        </td>
                        <td>
                            @php
                                $taskStyles = [
                                    'assigned' => 'badge-neutral',
                                    'picking_up' => 'badge-primary',
                                    'delivering' => 'badge-warning',
                                    'delivered' => 'badge-success',
                                ];
                                $taskLabels = [
                                    'assigned' => 'Ditugaskan',
                                    'picking_up' => 'Jemput Barang',
                                    'delivering' => 'Dalam Perjalanan',
                                    'delivered' => 'Selesai',
                                ];
                            @endphp
                            <span class="badge {{ $taskStyles[$task->status] ?? 'badge-neutral' }} text-[11px]">
                                {{ $taskLabels[$task->status] ?? $task->status }}
                            </span>
                        </td>
                        <td>
                            @if($task->delivery_photo)
                                <a href="{{ asset('storage/' . $task->delivery_photo) }}" target="_blank" class="inline-flex items-center gap-1 text-primary-600 hover:text-primary-700 text-xs font-semibold">
                                    <span class="material-symbols-outlined text-[15px]">image</span>
                                    <span>Lihat Foto</span>
                                </a>
                            @else
                                <span class="badge badge-neutral text-[10px] text-slate-400">
                                    Belum Ada
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                <a href="{{ route('admin.ci-work.index') }}" class="btn btn-secondary text-xs h-7 px-2 gap-1" title="Pantau di Cockpit">
                                    <span class="material-symbols-outlined text-[14px] text-slate-500">near_me</span>
                                    <span>Lacak</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">assignment_late</span>
                            <p class="text-xs font-semibold text-slate-600">Tidak ada tugas ditemukan</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Tugas yang sedang berjalan akan ditampilkan di tabel ini.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-100">
            {{ $tasks->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
