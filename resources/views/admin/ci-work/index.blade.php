@extends('layouts.admin')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Dashboard Kerja</h1>
        <p class="text-sm text-gray-500">Monitoring operasional harian City-Work</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Kurir Online</p>
                <p class="text-2xl font-bold text-[#059669]">{{ $stats['online_couriers'] }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">person</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Tugas Aktif</p>
                <p class="text-2xl font-bold text-[#059669]">{{ $stats['active_tasks'] }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">assignment</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Selesai Hari Ini</p>
                <p class="text-2xl font-bold text-[#059669]">{{ $stats['completed_today'] }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">check_circle</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Pendapatan Hari Ini</p>
                <p class="text-2xl font-bold text-[#059669]">Rp {{ number_format($stats['total_earnings_today'], 0, ',', '.') }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">payments</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Tugas Terbaru</h2>
            <p class="text-sm text-gray-500">Daftar tugas pengiriman terkini</p>
        </div>
        <a href="{{ route('admin.ci-work.tasks') }}" class="text-sm font-medium text-[#059669] hover:text-emerald-700 flex items-center gap-1">
            Lihat Semua
            <span class="material-symbols-outlined text-base">arrow_forward</span>
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-semibold">Resi</th>
                    <th class="text-left px-5 py-3 font-semibold">Kurir Pickup</th>
                    <th class="text-left px-5 py-3 font-semibold">Tujuan</th>
                    <th class="text-left px-5 py-3 font-semibold">Status</th>
                    <th class="text-left px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentTasks as $task)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4 font-medium text-gray-800">{{ $task->shipment->tracking_number ?? '-' }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $task->courier->user->name ?? '-' }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $task->shipment->receiver_address ?? '-' }}</td>
                    <td class="px-5 py-4">
                        @if($task->status === 'assigned')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Ditugaskan
                            </span>
                        @elseif($task->status === 'picking_up')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                <span class="material-symbols-outlined text-sm">local_shipping</span>
                                Jemput
                            </span>
                        @elseif($task->status === 'delivering')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                <span class="material-symbols-outlined text-sm">route</span>
                                Diantar
                            </span>
                        @elseif($task->status === 'delivered')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Selesai
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                {{ $task->status }}
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <a href="{{ route('admin.ci-work.tasks') }}" class="text-[#059669] hover:text-emerald-700 font-medium text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">visibility</span>
                            Detail
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-gray-300 text-5xl block mb-2">inbox</span>
                        <p class="text-gray-400">Belum ada tugas terbaru</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
