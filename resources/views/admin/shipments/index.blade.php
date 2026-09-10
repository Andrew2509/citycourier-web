@extends('layouts.admin')

@section('title', 'Manajemen Pengiriman')

@section('content')
<div class="flex flex-col w-full gap-space-lg">
    <!-- Header Section -->
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                <span>Pengiriman Real-time</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Manajemen Pengiriman</h1>
            <p class="font-body-md text-body-md text-secondary max-w-2xl">
                Pantau status pengiriman paket dari pickup hingga delivered secara real-time.
            </p>
        </div>
    </div>

    {{-- Filter Controls --}}
    @php
        $filters = [
            'all' => ['label' => 'Semua', 'icon' => 'inventory_2'],
            'pending' => ['label' => 'Pending', 'icon' => 'schedule'],
            'confirmed' => ['label' => 'Confirmed', 'icon' => 'check_circle'],
            'picked_up' => ['label' => 'Picked Up', 'icon' => 'package_2'],
            'in_transit' => ['label' => 'In Transit', 'icon' => 'local_shipping'],
            'delivered' => ['label' => 'Delivered', 'icon' => 'task_alt'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => 'cancel'],
        ];
        $currentFilter = request('status', 'all');
    @endphp

    <!-- Filter Controls & Action Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-space-md p-space-md rounded-xl bg-surface-container-lowest shadow-sm">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-space-xs overflow-x-auto pb-space-xs lg:pb-0">
            @foreach($filters as $key => $filter)
                @if(isset($statusCounts[$key]))
                <a href="{{ route('admin.shipments.index', array_merge(request()->except(['status', 'page']), $key !== 'all' ? ['status' => $key] : [])) }}"
                   class="px-space-md py-space-xs rounded-lg font-label-md text-label-md font-semibold transition-all flex items-center gap-space-xs shrink-0 {{ $currentFilter === $key ? 'bg-primary-container text-on-primary shadow-sm' : 'text-secondary hover:bg-surface-container-low hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-[16px]">{{ $filter['icon'] }}</span>
                    <span>{{ $filter['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[11px] font-bold {{ $currentFilter === $key ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container text-secondary' }}">{{ $statusCounts[$key] }}</span>
                </a>
                @endif
            @endforeach
        </div>
        <!-- Search -->
        <div class="flex flex-wrap sm:flex-nowrap items-center gap-space-sm">
            <form method="GET" action="{{ route('admin.shipments.index') }}" class="relative flex-1 sm:w-80">
                @if($currentFilter !== 'all')
                    <input type="hidden" name="status" value="{{ $currentFilter }}">
                @endif
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-secondary text-[18px] pointer-events-none">search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nomor, nama, atau alamat..."
                       class="w-full bg-surface pl-9 pr-8 py-2 rounded-lg font-body-sm text-body-sm text-on-surface placeholder:text-secondary focus:outline-none focus:bg-surface-container-lowest transition-all shadow-sm" />
            </form>
        </div>
    </div>

    <!-- Primary Card Container: Shipment Table -->
    <div class="flex flex-col w-full bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <!-- Card Header -->
        <div class="flex items-center justify-between px-space-xl py-space-md bg-surface-container-lowest">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Daftar Pengiriman</h2>
                    <span class="font-label-sm text-label-sm text-secondary">Menampilkan <span class="font-bold text-on-surface">{{ $shipments->total() }}</span> pengiriman</span>
                </div>
            </div>
        </div>

        <!-- Responsive Table -->
        @if($shipments->count() > 0)
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">No. Pengiriman</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Pengirim / Penerima</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Asal / Tujuan</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Harga</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-none">
                    @foreach($shipments as $shipment)
                    @php
                        $statusMap = [
                            'pending' => ['label' => 'Pending', 'style' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500'],
                            'confirmed' => ['label' => 'Confirmed', 'style' => 'bg-blue-50 text-blue-700', 'dot' => 'bg-blue-500'],
                            'picked_up' => ['label' => 'Picked Up', 'style' => 'bg-indigo-50 text-indigo-700', 'dot' => 'bg-indigo-500'],
                            'in_transit' => ['label' => 'In Transit', 'style' => 'bg-pink-50 text-pink-700', 'dot' => 'bg-pink-500'],
                            'delivered' => ['label' => 'Delivered', 'style' => 'bg-emerald-50 text-emerald-700', 'dot' => 'bg-emerald-600'],
                            'cancelled' => ['label' => 'Cancelled', 'style' => 'bg-red-50 text-red-700', 'dot' => 'bg-red-500'],
                        ];
                        $status = $statusMap[$shipment->status] ?? ['label' => $shipment->status, 'style' => 'bg-surface-container text-secondary', 'dot' => 'bg-gray-400'];
                    @endphp
                    <tr class="group hover:bg-surface transition-colors cursor-pointer">
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-space-xs">
                                <span class="font-data-mono font-bold text-primary tracking-wide">#{{ $shipment->shipment_number }}</span>
                            </div>
                            @if($shipment->tracking_number)
                            <span class="font-label-sm text-label-sm text-secondary font-data-mono">{{ $shipment->tracking_number }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-label-sm text-[10px] uppercase font-bold text-secondary">Dari</span>
                                    <span class="font-body-sm text-on-surface font-medium">{{ $shipment->sender_name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="font-label-sm text-[10px] uppercase font-bold text-secondary">Ke</span>
                                    <span class="font-body-sm text-on-surface font-medium">{{ $shipment->receiver_name }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full {{ $status['style'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>
                                <span class="font-label-sm text-xs font-bold">{{ $status['label'] }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 max-w-xs">
                            <div class="flex flex-col">
                                <span class="text-xs text-secondary line-clamp-1">{{ Str::limit($shipment->sender_address, 40) }}</span>
                                <span class="text-xs text-secondary line-clamp-1">{{ Str::limit($shipment->receiver_address, 40) }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            <span class="font-data-mono font-bold text-emerald-600 text-sm">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</span>
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('admin.shipments.show', $shipment->id) }}" class="p-1.5 rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Lihat Detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                <form action="{{ route('admin.shipments.destroy', $shipment->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Yakin ingin menghapus pengiriman #{{ $shipment->shipment_number }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors" title="Hapus">
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

        <!-- Pagination Footer -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-space-sm px-space-xl py-3.5 bg-surface-container-lowest">
            <span class="font-body-sm text-body-sm text-secondary">
                Menampilkan <span class="font-semibold text-on-surface">{{ $shipments->firstItem() }} - {{ $shipments->lastItem() }}</span> dari <span class="font-semibold text-on-surface">{{ $shipments->total() }}</span> pengiriman
            </span>
            <div class="flex items-center gap-1">
                {{ $shipments->withQueryString()->links('pagination::tailwind') }}
            </div>
        </div>
        @else
        <!-- Empty State -->
        <div class="py-space-2xl px-space-md flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 rounded-full bg-surface-container-high flex items-center justify-center text-secondary mb-space-md shadow-inner">
                <span class="material-symbols-outlined text-[32px] text-secondary">local_shipping</span>
            </div>
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Tidak ada pengiriman ditemukan</h3>
            <p class="font-body-sm text-body-sm text-secondary max-w-sm mt-space-xs">
                @if(request('search') || $currentFilter !== 'all')
                    Tidak ada hasil yang cocok dengan pencarian atau filter Anda.
                @else
                    Belum ada data pengiriman yang tersedia.
                @endif
            </p>
        </div>
        @endif
    </div>
</div>
@endsection
