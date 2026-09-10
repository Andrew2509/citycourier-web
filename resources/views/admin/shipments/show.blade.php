@extends('layouts.admin')

@section('title', 'Detail Pengiriman #' . ($shipment->tracking_number ?? $shipment->shipment_number))

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.shipments.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Detail Pengiriman</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">{{ $shipment->tracking_number ?? $shipment->shipment_number }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Left: Details --}}
        <div class="lg:col-span-2 flex flex-col gap-space-xl">
            {{-- Route Card --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high flex items-center justify-between">
                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">route</span>
                        Rute Pengiriman
                    </h4>
                    <span class="font-label-sm text-label-sm text-secondary">{{ $shipment->package_weight }} kg</span>
                </div>
                <div class="p-space-xl">
                    <div class="flex gap-space-lg">
                        {{-- Indicator --}}
                        <div class="flex flex-col items-center pt-1">
                            <div class="w-3 h-3 rounded-full bg-primary"></div>
                            <div class="w-0.5 flex-1 bg-surface-container-high my-1"></div>
                            <div class="w-3 h-3 rounded-full bg-secondary bg-surface-container-high border-2 border-surface-container-high"></div>
                        </div>
                        {{-- Addresses --}}
                        <div class="flex-1 flex flex-col gap-space-lg">
                            <div>
                                <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">PENGIRIM</div>
                                <div class="font-body-sm text-body-sm text-on-surface font-semibold">{{ $shipment->sender_name }}</div>
                                <div class="font-body-sm text-body-sm text-secondary">{{ $shipment->sender_phone }}</div>
                                <div class="font-body-sm text-body-sm text-secondary mt-space-2xs">{{ $shipment->sender_address }}</div>
                                @if($shipment->origin_name)
                                    <span class="inline-flex items-center gap-space-2xs mt-space-sm px-space-sm py-space-2xs rounded-full font-label-sm text-label-sm font-semibold bg-primary-container/10 text-primary">
                                        <span class="material-symbols-outlined text-[12px]">location_on</span>
                                        {{ $shipment->origin_name }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">PENERIMA</div>
                                <div class="font-body-sm text-body-sm text-on-surface font-semibold">{{ $shipment->receiver_name }}</div>
                                <div class="font-body-sm text-body-sm text-secondary">{{ $shipment->receiver_phone }}</div>
                                <div class="font-body-sm text-body-sm text-secondary mt-space-2xs">{{ $shipment->receiver_address }}</div>
                                @if($shipment->destination_name)
                                    <span class="inline-flex items-center gap-space-2xs mt-space-sm px-space-sm py-space-2xs rounded-full font-label-sm text-label-sm font-semibold bg-surface-container-high text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[12px]">location_on</span>
                                        {{ $shipment->destination_name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Package & Courier --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">inventory_2</span>
                        Info Paket & Kurir
                    </h4>
                </div>
                <div class="p-space-xl">
                    <div class="grid grid-cols-2 gap-space-lg mb-space-lg">
                        <div>
                            <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">DESKRIPSI PAKET</div>
                            <div class="font-body-sm text-body-sm text-on-surface">{{ $shipment->package_description ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">BERAT</div>
                            <div class="font-body-sm text-body-sm text-on-surface font-semibold">{{ $shipment->package_weight }} kg</div>
                        </div>
                        <div>
                            <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">EKSPEDISI</div>
                            <div class="font-headline-lg text-headline-lg text-primary font-bold">{{ strtoupper($shipment->courier_code ?? '—') }}</div>
                            <div class="font-body-sm text-body-sm text-secondary">{{ $shipment->courier_name }} · {{ $shipment->courier_service }}</div>
                        </div>
                        <div>
                            <div class="font-label-sm text-label-sm text-secondary tracking-wider mb-space-2xs">EST. PENGIRIMAN</div>
                            <div class="font-body-sm text-body-sm text-on-surface font-semibold">{{ $shipment->etd ? $shipment->etd . ' Hari' : '—' }}</div>
                        </div>
                    </div>

                    <div class="border-t border-surface-container-high pt-space-md flex flex-col gap-space-sm">
                        <div class="flex justify-between font-body-sm text-body-sm text-secondary">
                            <span>Ongkos Kirim</span>
                            <span>Rp {{ number_format($shipment->shipping_cost, 0, ',', '.') }}</span>
                        </div>
                        @if($shipment->insurance)
                        <div class="flex justify-between font-body-sm text-body-sm text-secondary">
                            <span>Asuransi</span>
                            <span>Rp 2.500</span>
                        </div>
                        @endif
                        @if($shipment->wood_packing)
                        <div class="flex justify-between font-body-sm text-body-sm text-secondary">
                            <span>Packing Kayu</span>
                            <span>Rp 50.000</span>
                        </div>
                        @endif
                        <div class="flex justify-between font-headline-lg text-headline-lg pt-space-md border-t border-surface-container-high">
                            <span class="text-on-surface font-semibold">Total</span>
                            <span class="text-primary font-bold">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tracking History --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high flex items-center justify-between">
                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">history</span>
                        Riwayat Pelacakan
                    </h4>
                    <button type="button" onclick="document.getElementById('addLogModal').classList.remove('hidden')" class="px-space-sm py-space-2xs rounded-lg font-label-sm text-label-sm font-semibold bg-primary-container text-on-primary hover:bg-primary transition-all flex items-center gap-space-2xs">
                        <span class="material-symbols-outlined text-[14px]">add</span>
                        Tambah Log
                    </button>
                </div>
                <div class="p-space-xl">
                    @if($shipment->logs->count() > 0)
                        <div class="flex flex-col gap-space-lg">
                            @foreach($shipment->logs as $log)
                            <div class="flex gap-space-md">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full {{ $log->status === 'delivered' ? 'bg-emerald-500' : 'bg-primary' }} z-10"></div>
                                    @if(!$loop->last)
                                        <div class="w-0.5 flex-1 bg-surface-container-high"></div>
                                    @endif
                                </div>
                                <div class="pb-space-lg flex-1">
                                    <div class="flex justify-between items-start">
                                        <div class="font-body-sm text-body-sm text-on-surface font-semibold">{{ $log->location }}</div>
                                        <div class="font-label-sm text-label-sm text-secondary">{{ $log->created_at->format('d M, H:i') }}</div>
                                    </div>
                                    <div class="font-body-sm text-body-sm text-secondary mt-space-2xs">{{ $log->description }}</div>
                                    <div class="font-label-sm text-label-sm text-primary mt-space-2xs font-bold uppercase tracking-wider">{{ str_replace('_', ' ', $log->status) }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-space-xl text-secondary">
                            <span class="material-symbols-outlined text-[40px] mb-space-sm">inbox</span>
                            <p class="font-body-sm text-body-sm">Belum ada riwayat pelacakan.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Status --}}
        <div class="flex flex-col gap-space-xl">
            {{-- Current Status --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-xl text-center">
                <span class="inline-flex items-center px-space-md py-space-sm rounded-full font-label-md text-label-md font-bold
                    {{ match($shipment->status_color ?? 'default') {
                        'success' => 'bg-emerald-100 text-emerald-700',
                        'primary', 'info' => 'bg-primary-container/10 text-primary',
                        'warning' => 'bg-amber-100 text-amber-700',
                        'danger', 'error' => 'bg-red-100 text-red-700',
                        default => 'bg-surface-container-high text-on-surface-variant'
                    } }}">
                    {{ $shipment->status_label }}
                </span>
                <div class="mt-space-md font-data-mono text-body-sm text-on-surface font-semibold bg-surface px-space-md py-space-sm rounded-lg">
                    {{ $shipment->tracking_number ?? 'BELUM ADA RESI' }}
                </div>
                <p class="font-label-sm text-label-sm text-secondary mt-space-2xs">Nomor Resi</p>

                @if($shipment->tracking_number)
                <div class="mt-space-md p-space-md bg-surface-container-lowest rounded-lg border border-surface-container-high inline-block">
                    {!! \Milon\Barcode\Facades\DNS2DFacade::getBarcodeHTML($shipment->tracking_number, 'QRCODE', 5, 5) !!}
                    <div class="mt-space-sm font-data-mono text-label-sm text-on-surface-variant font-semibold tracking-wider">{{ $shipment->tracking_number }}</div>
                </div>
                @endif

                <div class="mt-space-md font-label-sm text-label-sm text-secondary space-y-space-2xs">
                    <p>ID: <strong class="text-on-surface">{{ $shipment->shipment_number }}</strong></p>
                    <p>Dibuat: {{ $shipment->created_at->format('d M Y H:i') }}</p>
                </div>
            </div>

            {{-- Update Status --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">edit</span>
                        Update Status
                    </h4>
                </div>
                <div class="p-space-xl">
                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-space-md">
                            <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">STATUS PENGIRIMAN</label>
                            <select name="status" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary">
                                <option value="pending" {{ $shipment->status === 'pending' ? 'selected' : '' }}>⏳ Menunggu</option>
                                <option value="confirmed" {{ $shipment->status === 'confirmed' ? 'selected' : '' }}>✅ Dikonfirmasi</option>
                                <option value="picked_up" {{ $shipment->status === 'picked_up' ? 'selected' : '' }}>📦 Paket Diambil</option>
                                <option value="in_transit" {{ $shipment->status === 'in_transit' ? 'selected' : '' }}>🚚 Dalam Perjalanan</option>
                                <option value="delivered" {{ $shipment->status === 'delivered' ? 'selected' : '' }}>✅ Terkirim</option>
                                <option value="cancelled" {{ $shipment->status === 'cancelled' ? 'selected' : '' }}>❌ Dibatalkan</option>
                            </select>
                        </div>

                        <div class="mb-space-md">
                            <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">NOMOR RESI</label>
                            <input type="text" name="tracking_number" value="{{ $shipment->tracking_number }}" placeholder="Nomor resi..."
                                   class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm font-data-mono focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary">
                        </div>

                        <div class="mb-space-md">
                            <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">CATATAN INTERNAL</label>
                            <textarea name="notes" rows="3" placeholder="Catatan untuk tim..."
                                      class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary resize-none">{{ $shipment->notes }}</textarea>
                        </div>

                        <button type="submit" class="w-full py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all flex items-center justify-center gap-space-xs">
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Log Modal --}}
<div id="addLogModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-surface-container-lowest rounded-xl w-full max-w-md shadow-2xl mx-space-md">
        <div class="px-space-xl py-space-md border-b border-surface-container-high flex items-center justify-between">
            <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Tambah Riwayat Baru</h4>
            <button onclick="document.getElementById('addLogModal').classList.add('hidden')" class="p-space-2xs rounded-lg text-secondary hover:text-on-surface hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.shipments.logs.store', $shipment) }}" method="POST">
                @csrf
                <div class="mb-space-md">
                    <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">STATUS</label>
                    <input type="text" name="status" placeholder="Contoh: transit, delivery" required
                           class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary">
                </div>
                <div class="mb-space-md">
                    <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">LOKASI</label>
                    <input type="text" name="location" placeholder="Contoh: Jakarta Hub" required
                           class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary">
                </div>
                <div class="mb-space-lg">
                    <label class="block font-label-sm text-label-sm text-secondary font-semibold mb-space-xs">DESKRIPSI</label>
                    <textarea name="description" rows="2" placeholder="Detail kejadian..." required
                              class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary resize-none"></textarea>
                </div>
                <button type="submit" class="w-full py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
                    Simpan Log
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
