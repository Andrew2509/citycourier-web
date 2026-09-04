@extends('layouts.admin')

@section('title', 'Detail Pesanan')
@section('page-title', 'Detail Pesanan')
@section('page-subtitle', $order->tracking_number ?? $order->order_number)

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.orders') }}" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-xl">receipt_long</span>
                </div>
                Detail Pesanan
            </h1>
            <p class="text-sm text-slate-500 mt-1">No. Resi: <span class="font-mono font-semibold text-primary">{{ $order->tracking_number ?? $order->order_number }}</span></p>
        </div>
        @php
            $statusColors = [
                'created' => 'bg-slate-100 text-slate-700',
                'confirmed' => 'bg-blue-100 text-blue-700',
                'assigned' => 'bg-indigo-100 text-indigo-700',
                'picked_up' => 'bg-amber-100 text-amber-700',
                'in_transit' => 'bg-purple-100 text-purple-700',
                'delivered' => 'bg-green-100 text-green-700',
                'cancelled' => 'bg-red-100 text-red-700',
            ];
        @endphp
        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold {{ $statusColors[$order->status] ?? 'bg-slate-100 text-slate-700' }}">
            {{ ucfirst(str_replace('_', ' ', $order->status)) }}
        </span>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: Order Info --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Order Information --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">package_2</span>
                        Informasi Pesanan
                    </h3>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Nomor Resi</p>
                                <p class="font-mono text-lg font-bold text-primary">{{ $order->tracking_number ?? $order->order_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">No. Ref Internal</p>
                                <p class="text-sm font-medium text-slate-700">{{ $order->order_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Deskripsi Paket</p>
                                <p class="text-sm text-slate-700">{{ $order->package_description ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Berat</p>
                                <p class="text-sm font-medium text-slate-700">{{ $order->package_weight }} kg</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Harga</p>
                                <p class="text-lg font-bold text-success">Rp {{ number_format($order->price, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Catatan</p>
                                <p class="text-sm text-slate-700">{{ $order->notes ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Addresses --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">location_on</span>
                        Alamat Pengiriman
                    </h3>
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        {{-- Pickup --}}
                        <div class="flex items-start gap-4 p-4 rounded-xl bg-blue-50 border border-blue-100">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-blue-600">upload</span>
                            </div>
                            <div>
                                <p class="text-xs text-blue-600 uppercase tracking-wider font-semibold mb-1">Lokasi Jemput (Pickup)</p>
                                <p class="text-sm text-slate-700">{{ $order->pickup_address }}</p>
                            </div>
                        </div>
                        {{-- Delivery --}}
                        <div class="flex items-start gap-4 p-4 rounded-xl bg-green-50 border border-green-100">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-green-600">download</span>
                            </div>
                            <div>
                                <p class="text-xs text-green-600 uppercase tracking-wider font-semibold mb-1">Tujuan Pengiriman</p>
                                <p class="text-sm text-slate-700">{{ $order->delivery_address }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">timeline</span>
                        Timeline Pengiriman
                    </h3>
                </div>
                <div class="p-5">
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-primary text-xl">add_circle</span>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-slate-800">Pesanan Dibuat</p>
                                <p class="text-xs text-slate-400">{{ $order->created_at->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @if($order->picked_up_at)
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-amber-600 text-xl">inventory_2</span>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-slate-800">Paket Diambil Kurir</p>
                                    <p class="text-xs text-slate-400">{{ $order->picked_up_at->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                        @if($order->delivered_at)
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-green-600 text-xl">check_circle</span>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-success">Paket Berhasil Dikirim</p>
                                    <p class="text-xs text-slate-400">{{ $order->delivered_at->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Customer & Courier --}}
        <div class="space-y-6">
            {{-- Customer --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">person</span>
                        Informasi Customer
                    </h3>
                </div>
                <div class="p-5">
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Nama</p>
                            <p class="text-sm font-medium text-slate-800">{{ $order->customer_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Telepon</p>
                            <p class="text-sm font-medium text-slate-800">{{ $order->customer_phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Courier --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">delivery_dining</span>
                        Kurir
                    </h3>
                </div>
                <div class="p-5">
                    @if($order->courier && $order->courier->user)
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-primary/20">
                                {{ strtoupper(substr($order->courier->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-base font-semibold text-slate-800">{{ $order->courier->user->name }}</p>
                                <p class="text-sm text-slate-500">{{ $order->courier->phone }}</p>
                            </div>
                        </div>
                        <div class="space-y-3 pt-4 border-t border-slate-100">
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Kendaraan</p>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                                    {{ ucfirst($order->courier->vehicle_type) }}
                                </span>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Plat Nomor</p>
                                <p class="text-sm font-mono font-semibold text-slate-800">{{ $order->courier->vehicle_plate ?? '-' }}</p>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-3">
                                <span class="material-symbols-outlined text-slate-400 text-3xl">person_off</span>
                            </div>
                            <p class="text-sm text-slate-500">Belum ada kurir yang ditugaskan</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">bolt</span>
                        Aksi Cepat
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors text-left">
                        <span class="material-symbols-outlined text-primary">print</span>
                        <span class="text-sm font-medium text-slate-700">Cetak Resi</span>
                    </button>
                    <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors text-left">
                        <span class="material-symbols-outlined text-primary">share</span>
                        <span class="text-sm font-medium text-slate-700">Bagikan Status</span>
                    </button>
                    <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 hover:bg-slate-100 transition-colors text-left">
                        <span class="material-symbols-outlined text-primary">map</span>
                        <span class="text-sm font-medium text-slate-700">Lacak di Peta</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
