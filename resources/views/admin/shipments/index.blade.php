@extends('layouts.admin')

@section('title', 'Manajemen Pengiriman')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Pengiriman</h1>
            <p class="text-sm text-slate-400 mt-1">Pantau request pengiriman dari aplikasi City Courier</p>
        </div>
        @if($shipments->total() > 0)
        <form action="{{ route('admin.shipments.destroy-all') }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus SEMUA data pengiriman? Tindakan ini tidak dapat dibatalkan!');">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-error bg-error/10 hover:bg-error/20 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
                Hapus Semua
            </button>
        </form>
        @endif
    </div>

    <!-- Filter Tabs -->
    <div class="bg-white rounded-2xl p-4 border border-surface-border">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
            <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 w-full lg:w-auto">
                <a href="{{ route('admin.shipments.index') }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ !request('status') ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Semua ({{ $statusCounts['all'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'pending']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'pending' ? 'bg-warning text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Menunggu ({{ $statusCounts['pending'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'confirmed']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'confirmed' ? 'bg-info text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Dikonfirmasi ({{ $statusCounts['confirmed'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'picked_up']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'picked_up' ? 'bg-purple-500 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Diambil ({{ $statusCounts['picked_up'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'in_transit']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'in_transit' ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}>
                    Dalam Perjalanan ({{ $statusCounts['in_transit'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'delivered']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'delivered' ? 'bg-success text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Terkirim ({{ $statusCounts['delivered'] }})
                </a>
                <a href="{{ route('admin.shipments.index', ['status' => 'cancelled']) }}" class="px-3 py-2 rounded-lg text-xs font-medium whitespace-nowrap transition-all {{ request('status') === 'cancelled' ? 'bg-error text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:text-slate-700' }}">
                    Batal ({{ $statusCounts['cancelled'] }})
                </a>
            </div>

            <form method="GET" action="{{ route('admin.shipments.index') }}" class="flex items-center gap-2">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                    <input type="text" name="search" class="bg-slate-50 border border-slate-200 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary w-64" placeholder="Cari pengiriman..." value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Shipments Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">local_shipping</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">Daftar Pengiriman</h4>
                    <p class="text-xs text-slate-400">Total: {{ $shipments->total() }} pengiriman</p>
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
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Ekspedisi</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shipments as $shipment)
                    <tr class="hover:bg-slate-50 transition-all cursor-pointer" onclick="window.location='{{ route('admin.shipments.show', $shipment) }}'">
                        <td class="py-3.5 px-5">
                            <span class="font-mono text-sm font-bold text-primary">{{ $shipment->tracking_number ?? $shipment->shipment_number }}</span>
                        </td>
                        <td class="py-3.5 px-5">
                            <div class="text-sm font-semibold text-slate-700">{{ $shipment->customer_name }}</div>
                            <div class="text-xs text-slate-400">{{ $shipment->customer_phone }}</div>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-500 max-w-[200px] truncate">{{ $shipment->sender_address }}</td>
                        <td class="py-3.5 px-5 text-xs text-slate-500 max-w-[200px] truncate">{{ $shipment->receiver_address }}</td>
                        <td class="py-3.5 px-5">
                            <div class="text-sm font-semibold text-slate-700">{{ strtoupper($shipment->courier_code ?? '-') }}</div>
                            <div class="text-[10px] text-slate-400">{{ $shipment->courier_service ?? '' }} · {{ $shipment->package_weight }}kg</div>
                        </td>
                        <td class="py-3.5 px-5 text-sm font-semibold text-success">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold
                                {{ match($shipment->status_color ?? 'default') {
                                    'success' => 'bg-success/10 text-success',
                                    'primary', 'info' => 'bg-primary/10 text-primary',
                                    'warning' => 'bg-warning/10 text-warning',
                                    'danger', 'error' => 'bg-error/10 text-error',
                                    'purple' => 'bg-purple-100 text-purple-600',
                                    default => 'bg-slate-100 text-slate-500'
                                } }}">
                                {{ $shipment->status_label }}
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-xs text-slate-400 whitespace-nowrap">
                            {{ $shipment->created_at->format('d M Y') }}
                            <br>
                            <span class="text-[10px]">{{ $shipment->created_at->format('H:i') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">local_shipping</span>
                                <h3 class="text-base font-semibold text-slate-600">Belum ada pengiriman</h3>
                                <p class="text-sm text-slate-400 mt-1">Request pengiriman dari aplikasi akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($shipments->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $shipments->firstItem() }}-{{ $shipments->lastItem() }} dari {{ $shipments->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $shipments->withQueryString()->links('pagination.custom') }}
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
