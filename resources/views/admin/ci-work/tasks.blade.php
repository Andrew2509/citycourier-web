@extends('layouts.admin')

@section('content')
<div class="mb-6">
    <div class="flex items-center gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Manajemen Tugas Pengiriman</h1>
            <p class="text-sm text-gray-500">Pantau dan kelola seluruh tugas pengiriman kurir</p>
        </div>
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            LIVE DISPATCH
        </span>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Sedang Dikerjakan</p>
                <p class="text-2xl font-bold text-blue-600">{{ $tasks->filter(fn($t) => in_array($t->status, ['assigned','picking_up','delivering']))->count() }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-blue-600">pending_actions</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Selesai Hari Ini</p>
                <p class="text-2xl font-bold text-[#059669]">{{ $tasks->filter(fn($t) => $t->status === 'delivered')->count() }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">check_circle</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Menunggu Bukti Foto</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $tasks->filter(fn($t) => !$t->delivery_photo && $t->status === 'delivering')->count() }}</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-yellow-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-yellow-600">add_a_photo</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Rata-rata Durasi</p>
                <p class="text-2xl font-bold text-[#059669]">{{ $avgDuration }} mnt</p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#059669]">timer</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="flex border-b border-gray-100">
        <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}" class="px-5 py-3.5 text-sm font-medium transition-colors {{ !request('status') ? 'text-[#059669] border-b-2 border-[#059669]' : 'text-gray-500 hover:text-gray-700' }}">
            Semua Tugas
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="px-5 py-3.5 text-sm font-medium transition-colors {{ request('status') === 'active' ? 'text-[#059669] border-b-2 border-[#059669]' : 'text-gray-500 hover:text-gray-700' }}">
            Sedang Dikerjakan
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" class="px-5 py-3.5 text-sm font-medium transition-colors {{ request('status') === 'completed' ? 'text-[#059669] border-b-2 border-[#059669]' : 'text-gray-500 hover:text-gray-700' }}">
            Selesai
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'issue']) }}" class="px-5 py-3.5 text-sm font-medium transition-colors {{ request('status') === 'issue' ? 'text-[#059669] border-b-2 border-[#059669]' : 'text-gray-500 hover:text-gray-700' }}">
            Kendala Lapangan
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                    <th class="text-left px-5 py-3 font-semibold">No. Resi</th>
                    <th class="text-left px-5 py-3 font-semibold">Kurir</th>
                    <th class="text-left px-5 py-3 font-semibold">Lokasi Jemput</th>
                    <th class="text-left px-5 py-3 font-semibold">Lokasi Tujuan</th>
                    <th class="text-left px-5 py-3 font-semibold">Status Tugas</th>
                    <th class="text-left px-5 py-3 font-semibold">Bukti Foto</th>
                    <th class="text-left px-5 py-3 font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tasks as $task)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-4 font-medium text-gray-800">{{ $task->shipment->tracking_number ?? '-' }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#059669] text-sm">person</span>
                            </div>
                            <span class="text-gray-600">{{ $task->courier->user->name ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4 text-gray-600 text-xs max-w-[160px] truncate" title="{{ $task->shipment->sender_address ?? '' }}">{{ $task->shipment->sender_address ?? '-' }}</td>
                    <td class="px-5 py-4 text-gray-600 text-xs max-w-[160px] truncate" title="{{ $task->shipment->receiver_address ?? '' }}">{{ $task->shipment->receiver_address ?? '-' }}</td>
                    <td class="px-5 py-4">
                        @if($task->status === 'assigned')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Ditugaskan
                            </span>
                        @elseif($task->status === 'picking_up')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                <span class="material-symbols-outlined text-sm">local_shipping</span>
                                Jemput Barang
                            </span>
                        @elseif($task->status === 'delivering')
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                <span class="material-symbols-outlined text-sm">route</span>
                                Dalam Perjalanan
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
                        @if($task->delivery_photo)
                            <a href="{{ asset('storage/' . $task->delivery_photo) }}" target="_blank" class="inline-flex items-center gap-1 text-[#059669] hover:text-emerald-700 text-xs font-medium">
                                <span class="material-symbols-outlined text-sm">image</span>
                                Lihat
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                <span class="material-symbols-outlined text-sm">hourglass_empty</span>
                                Belum Ada
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-2">
                            <a href="#" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-50 text-[#059669] hover:bg-emerald-100 transition-colors">
                                <span class="material-symbols-outlined text-sm">map</span>
                                Detail Rute
                            </a>
                            <a href="#" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors">
                                <span class="material-symbols-outlined text-sm">phone</span>
                                Hubungi Kurir
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-gray-300 text-5xl block mb-2">assignment</span>
                        <p class="text-gray-400">Tidak ada tugas ditemukan</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-5 border-t border-gray-100">
        {{ $tasks->withQueryString()->links() }}
    </div>
</div>
@endsection
