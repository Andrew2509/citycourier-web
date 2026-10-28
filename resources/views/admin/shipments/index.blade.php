@extends('layouts.admin')

@section('title', 'Manajemen Pengiriman')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-brand-700 border border-orange-200">
                    <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                    Logistik Instan
                </span>
            </div>
            <h1 class="font-display text-2xl font-bold text-slate-900 mt-1">Manajemen Pengiriman</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Pantau seluruh pengiriman barang dari penjemputan hingga tiba di penerima.
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
            'in_transit' => ['label' => 'In Transit', 'icon' => 'near_me'],
            'delivered' => ['label' => 'Delivered', 'icon' => 'task_alt'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => 'cancel'],
        ];
        $currentFilter = request('status', 'all');
    @endphp

    <!-- Filter Pills & Search Bar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-2 lg:pb-0 sidebar-scroll">
            @foreach($filters as $key => $filter)
                @if(isset($statusCounts[$key]))
                <a href="{{ route('admin.shipments.index', array_merge(request()->except(['status', 'page']), $key !== 'all' ? ['status' => $key] : [])) }}"
                   class="px-3 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0 {{ $currentFilter === $key ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    <span class="material-symbols-outlined text-[16px]">{{ $filter['icon'] }}</span>
                    <span>{{ $filter['label'] }}</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $currentFilter === $key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $statusCounts[$key] }}</span>
                </a>
                @endif
            @endforeach
        </div>

        <!-- Search Box -->
        <div class="w-full lg:w-80 shrink-0">
            <form method="GET" action="{{ route('admin.shipments.index') }}" class="relative">
                @if($currentFilter !== 'all')
                    <input type="hidden" name="status" value="{{ $currentFilter }}">
                @endif
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">search</span>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari resi, nama, atau alamat..."
                       class="w-full bg-slate-50 border border-slate-200/80 pl-10 pr-4 py-2 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 focus:bg-white transition-all shadow-sm" />
            </form>
        </div>
    </div>

    <!-- Table Container -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
        <!-- Card Header -->
        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-orange-50 text-brand-600 border border-orange-100 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                </div>
                <div>
                    <h2 class="font-display text-base font-bold text-slate-900">Daftar Pengiriman Real-time</h2>
                    <span class="text-xs text-slate-500">Menampilkan total <strong class="text-slate-800">{{ $shipments->total() }}</strong> pengiriman</span>
                </div>
            </div>
        </div>

        <!-- Responsive Table -->
        @if($shipments->count() > 0)
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 font-mono">
                        <th class="py-3 px-4">No. Resi</th>
                        <th class="py-3 px-4">Rute &amp; Pihak Terlibat</th>
                        <th class="py-3 px-4">Kendaraan</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Biaya</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($shipments as $shipment)
                    @php
                        $statusMap = [
                            'pending'    => ['label' => 'Pending',    'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                            'confirmed'  => ['label' => 'Confirmed',  'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                            'picked_up'  => ['label' => 'Picked Up',  'bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                            'in_transit' => ['label' => 'In Transit', 'bg' => 'bg-orange-50 text-brand-700 border-orange-200', 'dot' => 'bg-brand-500'],
                            'delivered'  => ['label' => 'Delivered',  'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                            'cancelled'  => ['label' => 'Cancelled',  'bg' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500'],
                        ];
                        $st = $statusMap[$shipment->status] ?? ['label' => ucfirst($shipment->status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'];
                        $isMobil = str_contains(strtolower($shipment->vehicle ?? ''), 'mobil') || str_contains(strtolower($shipment->courier_name ?? ''), 'mobil');
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <!-- Nomor Resi -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <a href="{{ route('admin.shipments.show', $shipment) }}" class="font-mono text-xs font-bold text-brand-600 hover:underline block">
                                {{ $shipment->tracking_number ?? ('#' . $shipment->shipment_number) }}
                            </a>
                            <span class="text-[11px] text-slate-400 font-mono block mt-0.5">
                                {{ $shipment->created_at ? $shipment->created_at->format('d M Y, H:i') : '-' }}
                            </span>
                        </td>

                        <!-- Rute & Kontak -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                    <span class="text-slate-400 text-[10px] font-bold uppercase">Dari:</span>
                                    <span class="font-semibold text-slate-800">{{ $shipment->sender_name }}</span>
                                    <span class="text-slate-400 text-[11px]">({{ Str::limit($shipment->origin_name ?? 'Pickup', 20) }})</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shrink-0"></span>
                                    <span class="text-slate-400 text-[10px] font-bold uppercase">Ke:</span>
                                    <span class="font-semibold text-slate-800">{{ $shipment->receiver_name }}</span>
                                    <span class="text-slate-400 text-[11px]">({{ Str::limit($shipment->destination_name ?? 'Dropoff', 20) }})</span>
                                </div>
                            </div>
                        </td>

                        <!-- Kendaraan -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                <span class="material-symbols-outlined text-[16px] text-slate-500">
                                    {{ $isMobil ? 'directions_car' : 'two_wheeler' }}
                                </span>
                                <span>{{ $isMobil ? 'Mobil' : 'Motor' }}</span>
                            </span>
                        </td>

                        <!-- Status -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $st['bg'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $st['dot'] }}"></span>
                                {{ $st['label'] }}
                            </span>
                        </td>

                        <!-- Total Biaya -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="font-mono font-bold text-slate-900 text-xs">
                                Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}
                            </span>
                        </td>

                        <!-- Action Buttons -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="{{ route('tracking.show', $shipment->tracking_number ?? $shipment->id) }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="p-1.5 text-slate-500 hover:text-brand-600 hover:bg-orange-50 rounded-lg transition-colors border border-transparent hover:border-orange-200"
                                   title="Buka Live Tracking Web">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                                <a href="{{ route('admin.shipments.show', $shipment) }}"
                                   class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200/80 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                                    Detail
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <strong>{{ $shipments->firstItem() ?? 0 }}</strong> - <strong>{{ $shipments->lastItem() ?? 0 }}</strong> dari <strong>{{ $shipments->total() }}</strong> data
            </div>
            <div>
                {{ $shipments->links() }}
            </div>
        </div>

        @else
        <div class="py-16 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">local_shipping</span>
            <p class="text-sm font-medium text-slate-600">Tidak ada data pengiriman ditemukan</p>
            <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter status Anda.</p>
        </div>
        @endif
    </div>
</div>
@endsection
