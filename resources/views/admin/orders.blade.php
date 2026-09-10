@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-emerald-600 text-[22px]">inventory_2</span>
            </div>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Manajemen Pesanan</h1>
                <p class="text-sm text-gray-500">Kelola dan pantau seluruh pesanan masuk</p>
            </div>
        </div>
    </div>

    {{-- Status Filter Pills --}}
    <div class="flex flex-wrap gap-2">
        @php
            $filters = [
                'all'        => ['label' => 'Semua',              'icon' => 'apps'],
                'pending'    => ['label' => 'Pending',            'icon' => 'hourglass_empty'],
                'assigned'   => ['label' => 'Assigned',           'icon' => 'person_add'],
                'picking_up' => ['label' => 'Dalam Pengiriman',  'icon' => 'local_shipping'],
                'delivered'  => ['label' => 'Selesai',            'icon' => 'check_circle'],
                'cancelled'  => ['label' => 'Dibatalkan',         'icon' => 'cancel'],
            ];
            $activeStatus = request('status', 'all');
        @endphp

        @foreach($filters as $key => $filter)
            @php
                $count = $statusCounts[$key] ?? 0;
                $isActive = $activeStatus === $key;
            @endphp
            <a href="{{ route('admin.orders', $key === 'all' ? [] : ['status' => $key]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium transition-all duration-200
                      {{ $isActive
                          ? 'bg-emerald-600 text-white shadow-md shadow-emerald-200'
                          : 'bg-white text-gray-600 border border-gray-200 hover:border-emerald-300 hover:text-emerald-600 hover:bg-emerald-50' }}">
                <span class="material-symbols-outlined text-[18px]">{{ $filter['icon'] }}</span>
                {{ $filter['label'] }}
                <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full text-xs font-semibold
                             {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }}">
                    {{ $count }}
                </span>
            </a>
        @endforeach
    </div>

    {{-- Search --}}
    <div class="relative">
        <form method="GET" action="{{ route('admin.orders') }}">
            @if($activeStatus !== 'all')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-[20px]">search</span>
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Cari berdasarkan nama pelanggan atau nomor resi..."
                   class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400
                          focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-400 transition-all duration-200" />
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        @if($orders->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-200">
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">No. Resi</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pelanggan</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Alamat</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kurir</th>
                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga</th>
                            <th class="text-center px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($orders as $order)
                            @php
                                $statusStyles = match($order->status) {
                                    'pending'    => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'assigned'   => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'picking_up' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                    'delivering' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'delivered'  => 'bg-green-50 text-green-700 border-green-200',
                                    'cancelled'  => 'bg-red-50 text-red-700 border-red-200',
                                    default      => 'bg-gray-50 text-gray-700 border-gray-200',
                                };

                                $statusLabel = match($order->status) {
                                    'pending'    => 'Pending',
                                    'assigned'   => 'Assigned',
                                    'picking_up' => 'Dalam Pengambilan',
                                    'delivering' => 'Mengirim',
                                    'delivered'  => 'Selesai',
                                    'cancelled'  => 'Dibatalkan',
                                    default      => ucfirst($order->status),
                                };

                                $courierName = $order->courier?->user?->name ?? '-';
                            @endphp
                            <tr class="hover:bg-emerald-50/30 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-semibold text-emerald-700">
                                        {{ $order->order_number }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-900">{{ $order->customer_name }}</span>
                                        <span class="text-xs text-gray-500 mt-0.5">
                                            <span class="material-symbols-outlined text-[14px] align-middle mr-0.5">phone</span>
                                            {{ $order->customer_phone }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 max-w-[220px]">
                                    <div class="flex flex-col gap-1.5 text-xs">
                                        <div class="flex items-start gap-1.5">
                                            <span class="material-symbols-outlined text-[14px] text-emerald-500 mt-0.5 shrink-0">upload</span>
                                            <span class="text-gray-600 leading-relaxed">{{ $order->pickup_address }}</span>
                                        </div>
                                        <div class="flex items-start gap-1.5">
                                            <span class="material-symbols-outlined text-[14px] text-red-500 mt-0.5 shrink-0">download</span>
                                            <span class="text-gray-600 leading-relaxed">{{ $order->delivery_address }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($order->courier && $order->courier->user)
                                        <div class="flex items-center gap-2">
                                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                                <span class="material-symbols-outlined text-emerald-600 text-[16px]">person</span>
                                            </div>
                                            <span class="text-sm text-gray-700">{{ $courierName }}</span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-400 italic">
                                            <span class="material-symbols-outlined text-[14px]">person_off</span>
                                            Belum ditugaskan
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $statusStyles }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-sm font-semibold text-gray-900">
                                        Rp {{ number_format($order->price, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <a href="{{ route('admin.orders.detail', $order->id) }}"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-lg
                                                  hover:bg-emerald-100 transition-colors duration-150"
                                           title="Lihat Detail">
                                            <span class="material-symbols-outlined text-[16px]">visibility</span>
                                            Detail
                                        </a>

                                        <form action="{{ route('admin.orders.destroy', $order->id) }}"
                                              method="POST"
                                              class="inline-block"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan {{ $order->order_number }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded-lg
                                                           hover:bg-red-100 transition-colors duration-150"
                                                    title="Hapus Pesanan">
                                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $orders->withQueryString()->links('pagination::tailwind') }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center py-16 px-6">
                <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-emerald-400 text-[32px]">inbox</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Tidak ada pesanan</h3>
                <p class="text-sm text-gray-500 text-center max-w-sm">
                    @if(request('search'))
                        Tidak ditemukan pesanan yang sesuai dengan pencarian "{{ request('search') }}".
                    @elseif(request('status'))
                        Tidak ada pesanan dengan status "{{ $filters[request('status')]['label'] ?? request('status') }}".
                    @else
                        Belum ada pesanan masuk saat ini.
                    @endif
                </p>
                <a href="{{ route('admin.orders') }}"
                   class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-medium rounded-xl
                          hover:bg-emerald-700 shadow-sm shadow-emerald-200 transition-all duration-200">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                    Lihat Semua Pesanan
                </a>
            </div>
        @endif
    </div>

</div>
@endsection
