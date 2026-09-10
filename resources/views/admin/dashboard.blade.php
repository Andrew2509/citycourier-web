@extends('layouts.admin')

@section('content')
<div class="flex flex-col gap-6">

    <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
            <span class="material-symbols-outlined text-primary text-[22px]">dashboard</span>
        </div>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Dashboard Utama</h1>
            <p class="text-sm text-gray-500">Ringkasan operasional Kota Courier secara real-time</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="bg-white rounded-xl border border-gray-200 border-l-[3px] border-l-primary p-5 transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10">
                    <span class="material-symbols-outlined text-primary text-[20px]">shopping_bag</span>
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pesanan</span>
            </div>
            <p class="text-3xl font-bold font-mono text-gray-900">{{ number_format($stats['total_orders']) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $stats['pending_orders'] }} menunggu · {{ $stats['delivering_orders'] }} dikirim · {{ $stats['completed_orders'] }} selesai
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 border-l-[3px] border-l-primary p-5 transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10">
                    <span class="material-symbols-outlined text-primary text-[20px]">payments</span>
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pendapatan</span>
            </div>
            <p class="text-3xl font-bold font-mono text-gray-900">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-gray-500">
                Dari {{ $stats['completed_orders'] }} pengiriman selesai
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 border-l-[3px] border-l-primary p-5 transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10">
                    <span class="material-symbols-outlined text-primary text-[20px]">group</span>
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Kurir Aktif</span>
            </div>
            <p class="text-3xl font-bold font-mono text-gray-900">{{ $stats['active_couriers'] }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $stats['verified_couriers'] }} terverifikasi · {{ $stats['unverified_couriers'] }} belum diverifikasi
            </p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 border-l-[3px] border-l-primary p-5 transition-shadow hover:shadow-md">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10">
                    <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
                </div>
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Pengiriman Aktif</span>
            </div>
            <p class="text-3xl font-bold font-mono text-gray-900">{{ $stats['delivering_orders'] }}</p>
            <p class="mt-1 text-xs text-gray-500">
                {{ $stats['pending_orders'] }} menunggu diambil
            </p>
        </div>

    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-gray-400 text-[20px]">receipt_long</span>
                <h2 class="text-base font-semibold text-gray-900">Pesanan Terbaru</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">No. Resi</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pelanggan</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kurir</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        <th class="text-right px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Harga</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentOrders as $order)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-3.5 font-mono text-xs font-medium text-gray-900">{{ $order->tracking_number }}</td>
                        <td class="px-6 py-3.5 text-gray-700">{{ $order->customer_name }}</td>
                        <td class="px-6 py-3.5 text-gray-700">{{ $order->courier->user->name ?? '-' }}</td>
                        <td class="px-6 py-3.5">
                            @php
                                $statusStyles = [
                                    'pending'    => 'bg-amber-50 text-amber-700 border border-amber-200',
                                    'assigned'   => 'bg-sky-50 text-sky-700 border border-sky-200',
                                    'picking_up' => 'bg-blue-50 text-blue-700 border border-blue-200',
                                    'delivering' => 'bg-primary/10 text-primary border border-primary/20',
                                    'delivered'  => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                    'cancelled'  => 'bg-red-50 text-red-700 border border-red-200',
                                ];
                                $statusLabels = [
                                    'pending'    => 'Menunggu',
                                    'assigned'   => 'Ditugaskan',
                                    'picking_up' => 'Diambil',
                                    'delivering' => 'Dikirim',
                                    'delivered'  => 'Selesai',
                                    'cancelled'  => 'Dibatalkan',
                                ];
                                $style = $statusStyles[$order->status] ?? 'bg-gray-50 text-gray-600 border border-gray-200';
                                $label = $statusLabels[$order->status] ?? $order->status;
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $style }}">
                                {{ $label }}
                            </span>
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-xs font-medium text-gray-900">
                            Rp {{ number_format($order->price, 0, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">
                            Belum ada pesanan terbaru.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-gray-400 text-[20px]">motorcycle</span>
                <h2 class="text-base font-semibold text-gray-900">Kurir Aktif</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/60">
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Telepon</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($activeCouriers as $courier)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-3.5 font-medium text-gray-900">{{ $courier->user->name }}</td>
                        <td class="px-6 py-3.5 text-gray-700 font-mono text-xs">{{ $courier->user->phone ?? '-' }}</td>
                        <td class="px-6 py-3.5">
                            @if ($courier->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Online
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Offline
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-400">
                            Belum ada kurir aktif.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection