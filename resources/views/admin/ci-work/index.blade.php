@extends('layouts.admin')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">monitoring</span>
                <span>City-Work Operational</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Dashboard Kerja</h1>
            <p class="font-body-md text-body-md text-secondary">Monitoring operasional harian City-Work</p>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-space-md">
        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Kurir Online</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $stats['online_couriers'] }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-primary-fixed flex items-center justify-center text-primary shrink-0">
                    <span class="material-symbols-outlined text-[22px]">person</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Tugas Aktif</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $stats['active_tasks'] }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-secondary-fixed flex items-center justify-center text-on-secondary-fixed-variant shrink-0">
                    <span class="material-symbols-outlined text-[22px]">assignment</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Selesai Hari Ini</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">{{ $stats['completed_today'] }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                </div>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-secondary uppercase tracking-wider font-bold">Pendapatan Hari Ini</span>
                    <span class="font-display-lg text-display-lg text-on-surface font-bold mt-space-xs">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</span>
                </div>
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-700 shrink-0">
                    <span class="material-symbols-outlined text-[22px]">payments</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-space-xl py-space-md border-b border-surface-container-high">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">assignment</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Tugas Terbaru</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Daftar tugas pengiriman terkini</span>
                </div>
            </div>
            <a href="{{ route('admin.ci-work.tasks') }}" class="flex items-center gap-space-2xs text-primary hover:text-primary/80 font-label-md text-label-md font-semibold transition-colors">
                <span>Lihat Semua</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Resi</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Kurir</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Tujuan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @forelse($recentTasks as $task)
                    <tr class="hover:bg-surface transition-colors group">
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="font-data-mono font-bold text-primary">{{ $task->shipment->tracking_number ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="font-medium text-on-surface">{{ $task->courier->user->name ?? '-' }}</span>
                        </td>
                        <td class="py-4 px-4 max-w-xs">
                            <span class="text-sm text-secondary line-clamp-1">{{ $task->shipment->receiver_address ?? '-' }}</span>
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
                                    Jemput
                                </span>
                            @elseif($task->status === 'delivering')
                                <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs rounded-full bg-amber-50 text-amber-700 font-label-sm text-xs font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Diantar
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
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <a href="{{ route('admin.ci-work.tasks') }}" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Lihat Detail">
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-space-2xl px-4 text-center">
                            <div class="flex flex-col items-center gap-space-xs text-secondary">
                                <span class="material-symbols-outlined text-[32px]">inbox</span>
                                <span class="font-body-sm text-body-sm">Belum ada tugas terbaru</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
