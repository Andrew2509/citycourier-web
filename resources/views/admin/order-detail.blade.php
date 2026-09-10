@extends('layouts.admin')

@section('title', 'Detail Pesanan')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.orders') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-space-sm">
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Detail Pesanan</h1>
                @php
                    $statusColors = [
                        'created' => 'bg-surface-container-high text-on-surface-variant',
                        'confirmed' => 'bg-blue-100 text-blue-700',
                        'assigned' => 'bg-indigo-100 text-indigo-700',
                        'picked_up' => 'bg-amber-100 text-amber-700',
                        'in_transit' => 'bg-purple-100 text-purple-700',
                        'delivered' => 'bg-emerald-100 text-emerald-700',
                        'cancelled' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                <span class="inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-sm text-label-sm font-semibold {{ $statusColors[$order->status] ?? 'bg-surface-container-high text-on-surface-variant' }}">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                </span>
            </div>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">No. Resi: <span class="font-data-mono text-primary font-semibold">{{ $order->tracking_number ?? $order->order_number }}</span></p>
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Left Column: Order Info --}}
        <div class="lg:col-span-2 flex flex-col gap-space-xl">
            {{-- Order Information --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">package_2</span>
                        Informasi Pesanan
                    </h3>
                </div>
                <div class="p-space-xl">
                    <div class="grid grid-cols-2 gap-space-lg">
                        <div class="flex flex-col gap-space-lg">
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Nomor Resi</p>
                                <p class="font-data-mono text-headline-lg text-primary font-bold">{{ $order->tracking_number ?? $order->order_number }}</p>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">No. Ref Internal</p>
                                <p class="font-body-sm text-body-sm text-on-surface font-medium">{{ $order->order_number }}</p>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Deskripsi Paket</p>
                                <p class="font-body-sm text-body-sm text-on-surface">{{ $order->package_description ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-space-lg">
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Berat</p>
                                <p class="font-body-sm text-body-sm text-on-surface font-medium">{{ $order->package_weight }} kg</p>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Harga</p>
                                <p class="font-headline-lg text-headline-lg text-emerald-600 font-bold">Rp {{ number_format($order->price, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Catatan</p>
                                <p class="font-body-sm text-body-sm text-on-surface">{{ $order->notes ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Addresses --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">location_on</span>
                        Alamat Pengiriman
                    </h3>
                </div>
                <div class="p-space-xl">
                    <div class="flex flex-col gap-space-md">
                        {{-- Pickup --}}
                        <div class="flex items-start gap-space-md p-space-lg rounded-xl bg-blue-50 border border-blue-100">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-blue-600 text-[18px]">upload</span>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-blue-600 uppercase tracking-wider font-semibold mb-space-2xs">Lokasi Jemput (Pickup)</p>
                                <p class="font-body-sm text-body-sm text-on-surface">{{ $order->pickup_address }}</p>
                            </div>
                        </div>
                        {{-- Delivery --}}
                        <div class="flex items-start gap-space-md p-space-lg rounded-xl bg-emerald-50 border border-emerald-100">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-emerald-600 text-[18px]">download</span>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-emerald-600 uppercase tracking-wider font-semibold mb-space-2xs">Tujuan Pengiriman</p>
                                <p class="font-body-sm text-body-sm text-on-surface">{{ $order->delivery_address }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">timeline</span>
                        Timeline Pengiriman
                    </h3>
                </div>
                <div class="p-space-xl">
                    <div class="flex flex-col gap-space-lg">
                        <div class="flex items-center gap-space-md">
                            <div class="w-10 h-10 rounded-full bg-primary-container/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-[18px]">add_circle</span>
                            </div>
                            <div class="flex-1">
                                <p class="font-body-sm text-body-sm text-on-surface font-semibold">Pesanan Dibuat</p>
                                <p class="font-label-sm text-label-sm text-secondary">{{ $order->created_at->format('d M Y, H:i') }}</p>
                            </div>
                        </div>
                        @if($order->picked_up_at)
                            <div class="flex items-center gap-space-md">
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-amber-600 text-[18px]">inventory_2</span>
                                </div>
                                <div class="flex-1">
                                    <p class="font-body-sm text-body-sm text-on-surface font-semibold">Paket Diambil Kurir</p>
                                    <p class="font-label-sm text-label-sm text-secondary">{{ $order->picked_up_at->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                        @if($order->delivered_at)
                            <div class="flex items-center gap-space-md">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-emerald-600 text-[18px]">check_circle</span>
                                </div>
                                <div class="flex-1">
                                    <p class="font-body-sm text-body-sm text-emerald-600 font-semibold">Paket Berhasil Dikirim</p>
                                    <p class="font-label-sm text-label-sm text-secondary">{{ $order->delivered_at->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Customer & Courier --}}
        <div class="flex flex-col gap-space-xl">
            {{-- Customer --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">person</span>
                        Informasi Customer
                    </h3>
                </div>
                <div class="p-space-xl">
                    <div class="flex flex-col gap-space-md">
                        <div>
                            <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Nama</p>
                            <p class="font-body-sm text-body-sm text-on-surface font-medium">{{ $order->customer_name }}</p>
                        </div>
                        <div>
                            <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Telepon</p>
                            <p class="font-body-sm text-body-sm text-on-surface font-medium">{{ $order->customer_phone }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Courier --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">delivery_dining</span>
                        Kurir
                    </h3>
                </div>
                <div class="p-space-xl">
                    @if($order->courier && $order->courier->user)
                        <div class="flex items-center gap-space-md mb-space-md">
                            <div class="w-12 h-12 rounded-full bg-primary-container text-on-primary flex items-center justify-center font-bold text-lg shadow-sm">
                                {{ strtoupper(substr($order->courier->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-body-md text-body-md text-on-surface font-semibold">{{ $order->courier->user->name }}</p>
                                <p class="font-body-sm text-body-sm text-secondary">{{ $order->courier->phone }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-space-md pt-space-md border-t border-surface-container-high">
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Kendaraan</p>
                                <span class="inline-flex items-center px-space-sm py-space-2xs rounded-full font-label-sm text-label-sm font-semibold bg-primary-container/10 text-primary">
                                    {{ ucfirst($order->courier->vehicle_type) }}
                                </span>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-secondary uppercase tracking-wider mb-space-2xs">Plat Nomor</p>
                                <p class="font-data-mono text-body-sm text-on-surface font-semibold">{{ $order->courier->vehicle_plate ?? '-' }}</p>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-space-xl">
                            <div class="w-14 h-14 rounded-full bg-surface-container-high flex items-center justify-center mx-auto mb-space-md">
                                <span class="material-symbols-outlined text-secondary text-[28px]">person_off</span>
                            </div>
                            <p class="font-body-sm text-body-sm text-secondary">Belum ada kurir yang ditugaskan</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">bolt</span>
                        Aksi Cepat
                    </h3>
                </div>
                <div class="p-space-xl flex flex-col gap-space-sm">
                    <button class="w-full flex items-center gap-space-md px-space-md py-space-sm rounded-lg bg-surface-container-low hover:bg-surface-container-high transition-colors text-left">
                        <span class="material-symbols-outlined text-primary text-[18px]">print</span>
                        <span class="font-body-sm text-body-sm text-on-surface font-medium">Cetak Resi</span>
                    </button>
                    <button class="w-full flex items-center gap-space-md px-space-md py-space-sm rounded-lg bg-surface-container-low hover:bg-surface-container-high transition-colors text-left">
                        <span class="material-symbols-outlined text-primary text-[18px]">share</span>
                        <span class="font-body-sm text-body-sm text-on-surface font-medium">Bagikan Status</span>
                    </button>
                    <button class="w-full flex items-center gap-space-md px-space-md py-space-sm rounded-lg bg-surface-container-low hover:bg-surface-container-high transition-colors text-left">
                        <span class="material-symbols-outlined text-primary text-[18px]">map</span>
                        <span class="font-body-sm text-body-sm text-on-surface font-medium">Lacak di Peta</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
