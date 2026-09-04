@extends('layouts.admin')

@section('title', 'Detail Pengiriman #' . ($shipment->tracking_number ?? $shipment->shipment_number))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.shipments.index') }}" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Detail Pengiriman</h1>
            <p class="text-sm text-slate-400 mt-1">{{ $shipment->tracking_number ?? $shipment->shipment_number }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Details -->
        <div class="lg:col-span-2 space-y-5">
            <!-- Route Card -->
            <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">route</span>
                        Rute Pengiriman
                    </h4>
                    <span class="text-xs text-slate-400">{{ $shipment->package_weight }} kg</span>
                </div>
                <div class="p-5">
                    <div class="flex gap-5">
                        <!-- Indicator -->
                        <div class="flex flex-col items-center pt-1">
                            <div class="w-3 h-3 rounded-full bg-primary"></div>
                            <div class="w-0.5 flex-1 bg-slate-200 my-1"></div>
                            <div class="w-3 h-3 rounded-full bg-slate-400 border-2 border-slate-200"></div>
                        </div>
                        <!-- Addresses -->
                        <div class="flex-1 space-y-5">
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">PENGIRIM</div>
                                <div class="text-sm font-bold text-slate-800">{{ $shipment->sender_name }}</div>
                                <div class="text-xs text-slate-500">{{ $shipment->sender_phone }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $shipment->sender_address }}</div>
                                @if($shipment->origin_name)
                                    <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary">
                                        <span class="material-symbols-outlined text-[10px]">location_on</span>
                                        {{ $shipment->origin_name }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">PENERIMA</div>
                                <div class="text-sm font-bold text-slate-800">{{ $shipment->receiver_name }}</div>
                                <div class="text-xs text-slate-500">{{ $shipment->receiver_phone }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ $shipment->receiver_address }}</div>
                                @if($shipment->destination_name)
                                    <span class="inline-flex items-center gap-1 mt-2 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500">
                                        <span class="material-symbols-outlined text-[10px]">location_on</span>
                                        {{ $shipment->destination_name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package & Courier -->
            <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">inventory_2</span>
                        Info Paket & Kurir
                    </h4>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 gap-5 mb-5">
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">DESKRIPSI PAKET</div>
                            <div class="text-sm text-slate-700">{{ $shipment->package_description ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">BERAT</div>
                            <div class="text-sm font-bold text-slate-700">{{ $shipment->package_weight }} kg</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">EKSPEDISI</div>
                            <div class="text-lg font-black text-primary">{{ strtoupper($shipment->courier_code ?? '—') }}</div>
                            <div class="text-xs text-slate-400">{{ $shipment->courier_name }} · {{ $shipment->courier_service }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 tracking-wider mb-1">EST. PENGIRIMAN</div>
                            <div class="text-sm font-semibold text-slate-700">{{ $shipment->etd ? $shipment->etd . ' Hari' : '—' }}</div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-2">
                        <div class="flex justify-between text-sm text-slate-500">
                            <span>Ongkos Kirim</span>
                            <span>Rp {{ number_format($shipment->shipping_cost, 0, ',', '.') }}</span>
                        </div>
                        @if($shipment->insurance)
                        <div class="flex justify-between text-sm text-slate-500">
                            <span>Asuransi</span>
                            <span>Rp 2.500</span>
                        </div>
                        @endif
                        @if($shipment->wood_packing)
                        <div class="flex justify-between text-sm text-slate-500">
                            <span>Packing Kayu</span>
                            <span>Rp 50.000</span>
                        </div>
                        @endif
                        <div class="flex justify-between text-lg font-black pt-3 border-t border-slate-100">
                            <span class="text-slate-800">Total</span>
                            <span class="text-primary">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking History -->
            <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">history</span>
                        Riwayat Pelacakan
                    </h4>
                    <button type="button" onclick="document.getElementById('addLogModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-primary-dark transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">add</span>
                        Tambah Log
                    </button>
                </div>
                <div class="p-5">
                    @if($shipment->logs->count() > 0)
                        <div class="space-y-4">
                            @foreach($shipment->logs as $log)
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <div class="w-3 h-3 rounded-full {{ $log->status === 'delivered' ? 'bg-success' : 'bg-primary' }} z-10"></div>
                                    @if(!$loop->last)
                                        <div class="w-0.5 flex-1 bg-slate-200"></div>
                                    @endif
                                </div>
                                <div class="pb-6 flex-1">
                                    <div class="flex justify-between items-start">
                                        <div class="text-sm font-bold text-slate-700">{{ $log->location }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $log->created_at->format('d M, H:i') }}</div>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">{{ $log->description }}</div>
                                    <div class="text-[10px] font-bold text-primary mt-1 uppercase tracking-wider">{{ str_replace('_', ' ', $log->status) }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-slate-400">
                            <span class="material-symbols-outlined text-4xl mb-2">inbox</span>
                            <p class="text-sm">Belum ada riwayat pelacakan.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Right: Status -->
        <div class="space-y-5">
            <!-- Current Status -->
            <div class="bg-white rounded-2xl border border-surface-border p-5 text-center">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold
                    {{ match($shipment->status_color ?? 'default') {
                        'success' => 'bg-success/10 text-success',
                        'primary', 'info' => 'bg-primary/10 text-primary',
                        'warning' => 'bg-warning/10 text-warning',
                        'danger', 'error' => 'bg-error/10 text-error',
                        default => 'bg-slate-100 text-slate-500'
                    } }}">
                    {{ $shipment->status_label }}
                </span>
                <div class="mt-3 font-mono text-sm font-bold text-slate-700 bg-slate-50 px-4 py-2 rounded-xl">
                    {{ $shipment->tracking_number ?? 'BELUM ADA RESI' }}
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Nomor Resi</p>

                @if($shipment->tracking_number)
                <div class="mt-4 p-4 bg-white rounded-xl border border-surface-border inline-block">
                    {!! \Milon\Barcode\Facades\DNS2DFacade::getBarcodeHTML($shipment->tracking_number, 'QRCODE', 5, 5) !!}
                    <div class="mt-2 font-mono text-[10px] font-bold text-slate-600 tracking-wider">{{ $shipment->tracking_number }}</div>
                </div>
                @endif

                <div class="mt-4 text-xs text-slate-400 space-y-1">
                    <p>ID: <strong class="text-slate-600">{{ $shipment->shipment_number }}</strong></p>
                    <p>Dibuat: {{ $shipment->created_at->format('d M Y H:i') }}</p>
                </div>
            </div>

            <!-- Update Status -->
            <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">edit</span>
                        Update Status
                    </h4>
                </div>
                <div class="p-5">
                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}">
                        @csrf
                        @method('PATCH')

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-500 mb-2">STATUS PENGIRIMAN</label>
                            <select name="status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <option value="pending" {{ $shipment->status === 'pending' ? 'selected' : '' }}>⏳ Menunggu</option>
                                <option value="confirmed" {{ $shipment->status === 'confirmed' ? 'selected' : '' }}>✅ Dikonfirmasi</option>
                                <option value="picked_up" {{ $shipment->status === 'picked_up' ? 'selected' : '' }}>📦 Paket Diambil</option>
                                <option value="in_transit" {{ $shipment->status === 'in_transit' ? 'selected' : '' }}>🚚 Dalam Perjalanan</option>
                                <option value="delivered" {{ $shipment->status === 'delivered' ? 'selected' : '' }}>✅ Terkirim</option>
                                <option value="cancelled" {{ $shipment->status === 'cancelled' ? 'selected' : '' }}>❌ Dibatalkan</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-500 mb-2">NOMOR RESI</label>
                            <input type="text" name="tracking_number" value="{{ $shipment->tracking_number }}" placeholder="Nomor resi..."
                                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 font-mono focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-slate-500 mb-2">CATATAN INTERNAL</label>
                            <textarea name="notes" rows="3" placeholder="Catatan untuk tim..."
                                      class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none">{{ $shipment->notes }}</textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 rounded-xl text-sm font-bold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Log Modal -->
<div id="addLogModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl mx-4">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h4 class="text-base font-bold text-slate-800">Tambah Riwayat Baru</h4>
            <button onclick="document.getElementById('addLogModal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-5">
            <form action="{{ route('admin.shipments.logs.store', $shipment) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 mb-2">STATUS</label>
                    <input type="text" name="status" placeholder="Contoh: transit, delivery" required
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-500 mb-2">LOKASI</label>
                    <input type="text" name="location" placeholder="Contoh: Jakarta Hub" required
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                </div>
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-slate-500 mb-2">DESKRIPSI</label>
                    <textarea name="description" rows="2" placeholder="Detail kejadian..." required
                              class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"></textarea>
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl text-sm font-bold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all">
                    Simpan Log
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
