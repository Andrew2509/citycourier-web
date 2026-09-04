@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Pesanan</h1>
            <p class="text-sm text-slate-400 mt-1">Pantau dan kelola seluruh pesanan pengiriman</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-2xl p-4 border border-surface-border">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            <!-- Status Tabs -->
            <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 w-full lg:w-auto">
                <a href="{{ route('admin.orders') }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ !request('status') ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Semua ({{ $statusCounts['all'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'pending']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'pending' ? 'bg-warning text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Pending ({{ $statusCounts['pending'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'assigned']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'assigned' ? 'bg-info text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Assigned ({{ $statusCounts['assigned'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'picking_up']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'picking_up' ? 'bg-purple-500 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}>
                    Picking Up ({{ $statusCounts['picking_up'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'delivering']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'delivering' ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}>
                    Delivering ({{ $statusCounts['delivering'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'delivered']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'delivered' ? 'bg-success text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Selesai ({{ $statusCounts['delivered'] }})
                </a>
                <a href="{{ route('admin.orders', ['status' => 'cancelled']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'cancelled' ? 'bg-error text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Batal ({{ $statusCounts['cancelled'] }})
                </a>
            </div>

            <!-- Search -->
            <form method="GET" action="{{ route('admin.orders') }}" class="flex items-center gap-2">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                    <input type="text" name="search" class="bg-slate-50 border border-slate-200 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary w-64" placeholder="Cari order, nama, telepon..." value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">shopping_bag</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Daftar Pesanan</h4>
                    <p class="text-xs text-slate-400">Total: {{ $orders->total() }} pesanan</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Nomor Resi</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Customer</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pengirim</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Penerima</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Harga</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                    <tr class="hover:bg-slate-50 transition-all cursor-pointer" onclick="window.location='{{ route('admin.orders.detail', $order) }}'">
                        <td class="py-3.5 px-5">
                            <span class="font-mono text-sm font-bold text-primary">{{ $order->tracking_number ?? $order->order_number }}</span>
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="text-sm font-semibold text-slate-700">{{ $order->customer_name }}</div>
                            <div class="text-xs text-slate-400">{{ $order->customer_phone }}</div>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-500 max-w-[200px] truncate">{{ $order->pickup_address }}</td>
                        <td class="py-3.5 px-5 text-xs text-slate-500 max-w-[200px] truncate">{{ $order->delivery_address }}</td>
                        <td class="py-3.5 px-5 text-sm font-semibold text-success">Rp {{ number_format($order->price, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold
                                {{ match($order->status) {
                                    'delivered' => 'bg-success/10 text-success',
                                    'delivering', 'in_transit' => 'bg-primary/10 text-primary',
                                    'assigned' => 'bg-info/10 text-info',
                                    'picking_up' => 'bg-purple-100 text-purple-600',
                                    'pending' => 'bg-warning/10 text-warning',
                                    'cancelled' => 'bg-error/10 text-error',
                                    default => 'bg-slate-100 text-slate-500'
                                } }}">
                                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-400 whitespace-nowrap">
                            {{ $order->created_at->format('d M Y') }}
                            <br>
                            <span class="text-[10px]">{{ $order->created_at->format('H:i') }}</span>
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <form action="{{ route('admin.orders.destroy', $order) }}" method="POST" class="inline" onclick="event.stopPropagation();" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan {{ addslashes($order->order_number) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-error hover:bg-error/5 transition-all" title="Hapus">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">inventory_2</span>
                                <h3 class="text-base font-semibold text-slate-600">Belum ada pesanan</h3>
                                <p class="text-sm text-slate-400 mt-1">Pesanan baru dari aplikasi akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($orders->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $orders->firstItem() }}-{{ $orders->lastItem() }} dari {{ $orders->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $orders->withQueryString()->links('pagination.custom') }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
