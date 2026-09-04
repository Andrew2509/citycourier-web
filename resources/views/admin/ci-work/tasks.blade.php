@extends('layouts.admin')

@section('title', 'Manajemen Tugas')
@section('page-title', 'Manajemen Tugas')
@section('page-subtitle', 'Daftar tugas pengiriman yang sedang berjalan dengan bukti foto')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-xl">task</span>
                </div>
                Manajemen Tugas
            </h1>
            <p class="text-sm text-slate-500 mt-1">Daftar tugas pengiriman yang sedang berjalan</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">pending</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Sedang Dikerjakan</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $tasks->where('status', '!=', 'delivered')->count() }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-success/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-success text-2xl">check_circle</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Selesai Hari Ini</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $tasks->where('status', 'delivered')->count() }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-info/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-info text-2xl">photo_camera</span>
                </div>
                <div>
                    <p class="text-sm text-slate-500">Ada Bukti Foto</p>
                    <p class="text-2xl font-bold text-slate-800">{{ $tasks->filter(function($t) { return $t->pickup_photo || $t->delivery_photo; })->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tasks Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h3 class="text-lg font-semibold text-slate-800">Monitoring Tugas Aktif</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">No. Resi</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Kurir</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Lokasi Jemput</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Tujuan</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Bukti Foto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($tasks as $task)
                        <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location='{{ route('admin.orders.detail', $task) }}'">
                            <td class="px-5 py-4">
                                <span class="font-mono text-sm font-semibold text-primary">{{ $task->tracking_number ?? $task->order_number }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                        {{ strtoupper(substr($task->courier->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-800">{{ $task->courier->user->name ?? 'Unassigned' }}</p>
                                        <p class="text-xs text-slate-400">{{ $task->courier->courier_id ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm text-slate-600 max-w-[200px] truncate" title="{{ $task->pickup_address }}">
                                    {{ $task->pickup_address }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <p class="text-sm text-slate-600 max-w-[200px] truncate" title="{{ $task->delivery_address }}">
                                    {{ $task->delivery_address }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                @php
                                    $statusColors = [
                                        'assigned' => 'bg-blue-100 text-blue-700',
                                        'picked_up' => 'bg-amber-100 text-amber-700',
                                        'in_transit' => 'bg-purple-100 text-purple-700',
                                        'delivered' => 'bg-green-100 text-green-700',
                                        'cancelled' => 'bg-red-100 text-red-700',
                                    ];
                                    $statusColor = $statusColors[$task->status] ?? 'bg-slate-100 text-slate-700';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    @if($task->pickup_photo)
                                        <a href="{{ asset('storage/' . $task->pickup_photo) }}" target="_blank" class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all" title="Foto Pickup">
                                            <span class="material-symbols-outlined text-sm">photo_camera</span>
                                        </a>
                                    @endif
                                    @if($task->delivery_photo)
                                        <a href="{{ asset('storage/' . $task->delivery_photo) }}" target="_blank" class="w-8 h-8 rounded-lg bg-success/10 flex items-center justify-center text-success hover:bg-success hover:text-white transition-all" title="Foto Delivery">
                                            <span class="material-symbols-outlined text-sm">add_a_photo</span>
                                        </a>
                                    @endif
                                    @if(!$task->pickup_photo && !$task->delivery_photo)
                                        <span class="text-xs text-slate-400 italic">Belum ada</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-slate-400 text-3xl">inbox</span>
                                    </div>
                                    <p class="text-sm text-slate-500">Tidak ada tugas berjalan saat ini</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $tasks->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
