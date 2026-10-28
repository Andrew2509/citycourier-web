@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-orange-50 text-brand-700 border border-orange-200">
                    <span class="material-symbols-outlined text-[14px]">inventory_2</span>
                    Pesanan Konsumen
                </span>
            </div>
            <h1 class="font-display text-2xl font-bold text-slate-900 mt-1">Daftar Seluruh Pesanan</h1>
            <p class="text-sm text-slate-500 mt-0.5">
                Pantau pesanan masuk, status penugasan kurir, dan rute pengantaran pelanggan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-mono text-xs font-semibold shadow-sm">
                Total: <strong class="text-brand-600">{{ number_format($orders->total()) }}</strong> Pesanan
            </span>
        </div>
    </div>

    {{-- Status Filter Pills --}}
    @php
        $filters = [
            'all'        => ['label' => 'Semua',       'count' => $statusCounts['all'] ?? 0, 'icon' => 'inventory_2'],
            'pending'    => ['label' => 'Pending',     'count' => $statusCounts['pending'] ?? 0, 'icon' => 'schedule'],
            'assigned'   => ['label' => 'Ditugaskan',  'count' => $statusCounts['assigned'] ?? 0, 'icon' => 'two_wheeler'],
            'delivered'  => ['label' => 'Selesai',     'count' => $statusCounts['delivered'] ?? 0, 'icon' => 'task_alt'],
            'cancelled'  => ['label' => 'Dibatalkan',  'count' => $statusCounts['cancelled'] ?? 0, 'icon' => 'cancel'],
        ];
        $activeStatus = request('status', 'all');
    @endphp

    <!-- Filter Pills & Search Bar -->
    <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-2 lg:pb-0 sidebar-scroll">
            @foreach($filters as $key => $filter)
            <a href="{{ route('admin.orders', $key === 'all' ? [] : ['status' => $key]) }}"
               class="px-3 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0
                      {{ $activeStatus === $key ? 'bg-brand-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                <span class="material-symbols-outlined text-[16px]">{{ $filter['icon'] }}</span>
                <span>{{ $filter['label'] }}</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $activeStatus === $key ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $filter['count'] }}</span>
            </a>
            @endforeach
        </div>

        <!-- Search Input -->
        <div class="w-full lg:w-80 shrink-0">
            <form method="GET" action="{{ route('admin.orders') }}" class="relative">
                @if($activeStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $activeStatus }}">
                @endif
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px] pointer-events-none">search</span>
                <input type="text"
                       name="search"
                       value="{{ request('search') }}"
                       placeholder="Cari resi, nama, atau HP..."
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
                    <span class="material-symbols-outlined text-[20px]">local_mall</span>
                </div>
                <div>
                    <h2 class="font-display text-base font-bold text-slate-900">Data Transaksi Pesanan</h2>
                    <span class="text-xs text-slate-500">Menampilkan <strong class="text-slate-800">{{ $orders->count() }}</strong> data di halaman ini</span>
                </div>
            </div>
            <button class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors" title="Muat Ulang" onclick="location.reload()" type="button">
                <span class="material-symbols-outlined text-[20px]">refresh</span>
            </button>
        </div>

        <!-- Responsive Table Container -->
        @if($orders->count() > 0)
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 font-mono">
                        <th class="py-3 px-4">Nomor Resi</th>
                        <th class="py-3 px-4">Pelanggan</th>
                        <th class="py-3 px-4">Titik Jemput &amp; Antar</th>
                        <th class="py-3 px-4">Kurir Bertugas</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Tarif</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($orders as $order)
                    @php
                        $statusStyles = match($order->status) {
                            'pending'    => ['label' => 'Pending',    'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                            'assigned'   => ['label' => 'Ditugaskan', 'bg' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                            'picking_up' => ['label' => 'Penjemputan','bg' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                            'delivering' => ['label' => 'Diantar',    'bg' => 'bg-orange-50 text-brand-700 border-orange-200', 'dot' => 'bg-brand-500'],
                            'delivered'  => ['label' => 'Selesai',    'bg' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                            'cancelled'  => ['label' => 'Batal',      'bg' => 'bg-red-50 text-red-700 border-red-200', 'dot' => 'bg-red-500'],
                            default      => ['label' => ucfirst($order->status), 'bg' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                        };
                        $courierName = $order->courier?->user?->name ?? null;
                    @endphp
                    <tr class="hover:bg-slate-50/60 transition-colors">
                        <!-- Nomor Resi -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <a href="{{ route('admin.orders.detail', $order->id) }}" class="font-mono text-xs font-bold text-brand-600 hover:underline block">
                                {{ $order->tracking_number ?? $order->order_number }}
                            </a>
                            <span class="text-[11px] text-slate-400 font-mono block mt-0.5">
                                {{ $order->created_at ? $order->created_at->format('d M Y, H:i') : '-' }}
                            </span>
                        </td>

                        <!-- Pelanggan -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 font-bold text-xs flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr($order->customer_name ?? 'C', 0, 1)) }}
                                </div>
                                <div>
                                    <span class="font-semibold text-slate-900 block text-xs">{{ $order->customer_name ?? 'Pelanggan' }}</span>
                                    <span class="text-[11px] text-slate-400 font-mono">{{ $order->customer_phone ?? '-' }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Rute Alamat -->
                        <td class="py-3.5 px-4 max-w-xs">
                            <div class="space-y-1 text-xs">
                                <div class="truncate text-slate-700">
                                    <span class="text-slate-400 font-bold uppercase text-[10px]">Pickup:</span>
                                    <span class="font-medium">{{ $order->pickup_address ?? '-' }}</span>
                                </div>
                                <div class="truncate text-slate-700">
                                    <span class="text-slate-400 font-bold uppercase text-[10px]">Dropoff:</span>
                                    <span class="font-medium">{{ $order->delivery_address ?? '-' }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Kurir -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            @if($courierName)
                            <div class="flex items-center gap-1.5 text-xs text-slate-700 font-medium">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600">two_wheeler</span>
                                <span>{{ $courierName }}</span>
                            </div>
                            @else
                            <span class="text-xs text-slate-400 italic">Menunggu kurir</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $statusStyles['bg'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $statusStyles['dot'] }}"></span>
                                {{ $statusStyles['label'] }}
                            </span>
                        </td>

                        <!-- Tarif -->
                        <td class="py-3.5 px-4 whitespace-nowrap">
                            <span class="font-mono font-bold text-slate-900 text-xs">
                                Rp {{ number_format($order->price, 0, ',', '.') }}
                            </span>
                        </td>

                        <!-- Aksi -->
                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                @if($order->tracking_number)
                                <a href="{{ route('tracking.show', $order->tracking_number) }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="p-1.5 text-slate-500 hover:text-brand-600 hover:bg-orange-50 rounded-lg transition-colors border border-transparent hover:border-orange-200"
                                   title="Buka Live Tracking Web">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                                @endif
                                <a href="{{ route('admin.orders.detail', $order->id) }}"
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

        <!-- Pagination Footer -->
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan <strong>{{ $orders->firstItem() ?? 0 }}</strong> - <strong>{{ $orders->lastItem() ?? 0 }}</strong> dari <strong>{{ $orders->total() }}</strong> pesanan
            </div>
            <div>
                {{ $orders->links() }}
            </div>
        </div>
        @else
        <div class="py-16 text-center text-slate-400">
            <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">inventory_2</span>
            <p class="text-sm font-medium text-slate-600">Tidak ada pesanan ditemukan</p>
            <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter status Anda.</p>
        </div>
        @endif
    </div>
</div>
@endsection
