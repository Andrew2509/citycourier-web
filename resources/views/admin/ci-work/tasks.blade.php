@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">assignment</span>
                <span>City-Work Dispatch</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Tugas Pengiriman</h1>
            <p class="font-body-md text-body-md text-secondary">Pantau dan kelola seluruh tugas pengiriman kurir</p>
        </div>
        <span class="inline-flex items-center gap-space-2xs px-space-xs py-space-2xs rounded-full font-label-sm text-label-sm bg-red-50 text-red-700 font-bold animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            LIVE DISPATCH
        </span>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Sedang Dikerjakan</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $tasks->filter(fn($t) => in_array($t->status, ['assigned','picking_up','delivering']))->count() }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed-variant shrink-0">
                    <span class="material-symbols-outlined text-[22px]">pending_actions</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Selesai Hari Ini</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $tasks->filter(fn($t) => $t->status === 'delivered')->count() }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Menunggu Bukti Foto</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $tasks->filter(fn($t) => !$t->delivery_photo && $t->status === 'delivering')->count() }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">add_a_photo</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Rata-rata Durasi</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $avgDuration }} mnt</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                    <span class="material-symbols-outlined text-[22px]">timer</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex border-b border-surface-container-high overflow-x-auto">
            <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}" class="px-space-lg py-3.5 text-sm font-medium transition-colors whitespace-nowrap {{ !request('status') ? 'text-primary border-b-2 border-primary font-bold' : 'text-secondary hover:text-on-surface' }}">
                Semua Tugas
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="px-space-lg py-3.5 text-sm font-medium transition-colors whitespace-nowrap {{ request('status') === 'active' ? 'text-primary border-b-2 border-primary font-bold' : 'text-secondary hover:text-on-surface' }}">
                Sedang Dikerjakan
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" class="px-space-lg py-3.5 text-sm font-medium transition-colors whitespace-nowrap {{ request('status') === 'completed' ? 'text-primary border-b-2 border-primary font-bold' : 'text-secondary hover:text-on-surface' }}">
                Selesai
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'issue']) }}" class="px-space-lg py-3.5 text-sm font-medium transition-colors whitespace-nowrap {{ request('status') === 'issue' ? 'text-primary border-b-2 border-primary font-bold' : 'text-secondary hover:text-on-surface' }}">
                Kendala Lapangan
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">No. Resi</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Lokasi Jemput</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Lokasi Tujuan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status Tugas</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Bukti Foto</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($tasks as $task)
                    <tr class="hover:bg-surface transition-colors group">
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="font-data-mono font-bold text-primary">{{ $task->shipment->tracking_number ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <div class="w-7 h-7 rounded-full bg-primary-container text-on-primary flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($task->courier->user->name ?? 'K', 0, 1)) }}
                                </div>
                                <span class="font-medium text-on-surface">{{ $task->courier->user->name ?? '-' }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 max-w-[160px]">
                            <span class="text-xs text-secondary line-clamp-1" title="{{ $task->shipment->sender_address ?? '' }}">{{ $task->shipment->sender_address ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-4 max-w-[160px]">
                            <span class="text-xs text-secondary line-clamp-1" title="{{ $task->shipment->receiver_address ?? '' }}">{{ $task->shipment->receiver_address ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            @if($task->status === 'assigned')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-secondary-container text-on-secondary-fixed-variant font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    Ditugaskan
                                </span>
                            @elseif($task->status === 'picking_up')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-blue-50 text-blue-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    Jemput Barang
                                </span>
                            @elseif($task->status === 'delivering')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-amber-50 text-amber-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Dalam Perjalanan
                                </span>
                            @elseif($task->status === 'delivered')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-emerald-50 text-emerald-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                    Selesai
                                </span>
                            @else
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-surface-container text-secondary font-label-sm text-xs font-bold">
                                    {{ $task->status }}
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            @if($task->delivery_photo)
                                <a href="{{ asset('storage/' . $task->delivery_photo) }}" target="_blank" class="inline-flex items-center gap-space-2xs text-primary hover:text-primary/80 text-xs font-medium">
                                    <span class="material-symbols-outlined text-[16px]">image</span>
                                    Lihat
                                </a>
                            @else
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-amber-50 text-amber-700 font-label-sm text-xs font-bold">
                                    <span class="material-symbols-outlined text-[14px]">hourglass_empty</span>
                                    Belum Ada
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <div class="inline-flex items-center justify-end gap-space-xs">
                                <a href="#" class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-surface-container-low transition-colors" title="Detail Rute">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                                <a href="#" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Hubungi Kurir">
                                    <span class="material-symbols-outlined text-[18px]">phone</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">assignment</span>
                                <span class="font-body-sm text-body-sm">Tidak ada tugas ditemukan</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $tasks->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
