@extends('layouts.admin')

@section('title', 'Detail Pengiriman #' . $shipment->shipment_number)
@section('page-title', 'Detail Pengiriman')
@section('page-subtitle', $shipment->shipment_number)

@section('content')
    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:16px; padding:12px 16px; background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius:10px; color:#10b981;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start;">

        {{-- Left: Shipment Details --}}
        <div style="display: flex; flex-direction: column; gap: 20px;">

            {{-- Route Card --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-route"></i> Rute Pengiriman</div>
                    <span style="font-size:13px; color: var(--text-muted);">{{ $shipment->package_weight }} kg</span>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 16px; align-items: stretch;">
                        {{-- Indicator line --}}
                        <div style="display:flex; flex-direction:column; align-items:center; padding-top:4px;">
                            <div style="width:10px; height:10px; border-radius:50%; background: var(--accent-primary-light);"></div>
                            <div style="width:2px; flex:1; background: var(--border-color); margin: 4px 0;"></div>
                            <div style="width:10px; height:10px; border-radius:50%; background: #6b7280; border: 2px solid var(--border-color);"></div>
                        </div>
                        {{-- Addresses --}}
                        <div style="flex:1; display:flex; flex-direction:column; gap:12px;">
                            <div>
                                <div style="font-size:10px; font-weight:700; color: var(--text-muted); letter-spacing:1px; margin-bottom:4px;">PENGIRIM</div>
                                <div style="font-size:14px; font-weight:700;">{{ $shipment->sender_name }}</div>
                                <div style="font-size:12px; color: var(--text-muted);">{{ $shipment->sender_phone }}</div>
                                <div style="font-size:12px; color: var(--text-muted); margin-top:2px;">{{ $shipment->sender_address }}</div>
                                @if($shipment->origin_name)
                                    <span style="font-size:11px; background: rgba(236,91,19,0.1); color: var(--accent-primary-light); padding:2px 8px; border-radius:20px; display:inline-block; margin-top:4px;">
                                        <i class="fas fa-map-pin" style="font-size:9px;"></i> {{ $shipment->origin_name }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <div style="font-size:10px; font-weight:700; color: var(--text-muted); letter-spacing:1px; margin-bottom:4px;">PENERIMA</div>
                                <div style="font-size:14px; font-weight:700;">{{ $shipment->receiver_name }}</div>
                                <div style="font-size:12px; color: var(--text-muted);">{{ $shipment->receiver_phone }}</div>
                                <div style="font-size:12px; color: var(--text-muted); margin-top:2px;">{{ $shipment->receiver_address }}</div>
                                @if($shipment->destination_name)
                                    <span style="font-size:11px; background: rgba(107,114,128,0.1); color: #6b7280; padding:2px 8px; border-radius:20px; display:inline-block; margin-top:4px;">
                                        <i class="fas fa-map-pin" style="font-size:9px;"></i> {{ $shipment->destination_name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Package & Courier --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-box"></i> Info Paket & Kurir</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <div style="font-size:10px; font-weight:700; color:var(--text-muted); letter-spacing:1px;">DESKRIPSI PAKET</div>
                            <div style="margin-top:4px; font-size:14px;">{{ $shipment->package_description ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; font-weight:700; color:var(--text-muted); letter-spacing:1px;">BERAT</div>
                            <div style="margin-top:4px; font-size:14px; font-weight:700;">{{ $shipment->package_weight }} kg</div>
                        </div>
                        <div>
                            <div style="font-size:10px; font-weight:700; color:var(--text-muted); letter-spacing:1px;">EKSPEDISI</div>
                            <div style="margin-top:4px; font-size:16px; font-weight:900; color: var(--accent-primary-light);">{{ strtoupper($shipment->courier_code ?? '—') }}</div>
                            <div style="font-size:12px; color:var(--text-muted);">{{ $shipment->courier_name }} · {{ $shipment->courier_service }}</div>
                        </div>
                        <div>
                            <div style="font-size:10px; font-weight:700; color:var(--text-muted); letter-spacing:1px;">EST. PENGIRIMAN</div>
                            <div style="margin-top:4px; font-size:14px; font-weight:600;">{{ $shipment->etd ? $shipment->etd . ' Hari' : '—' }}</div>
                        </div>
                    </div>
                    <hr style="margin: 16px 0; border-color: var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-muted);">
                        <span>Ongkos Kirim</span>
                        <span>Rp {{ number_format($shipment->shipping_cost, 0, ',', '.') }}</span>
                    </div>
                    @if($shipment->insurance)
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-muted); margin-top:6px;">
                        <span>Asuransi Pengiriman</span>
                        <span>Rp 2.500</span>
                    </div>
                    @endif
                    @if($shipment->wood_packing)
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-muted); margin-top:6px;">
                        <span>Packing Kayu</span>
                        <span>Rp 50.000</span>
                    </div>
                    @endif
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:16px; font-weight:900; margin-top:10px; padding-top:10px; border-top:1px solid var(--border-color);">
                        <span>Total</span>
                        <span style="color: var(--accent-primary-light);">Rp {{ number_format($shipment->total_cost, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right: Status Update --}}
        <div style="display: flex; flex-direction: column; gap: 20px;">

            {{-- Current Status --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-info-circle"></i> Status Saat Ini</div>
                </div>
                <div class="card-body" style="text-align:center; padding: 24px;">
                    <span class="badge badge-{{ $shipment->status_color }}" style="font-size:14px; padding: 8px 20px;">
                        {{ $shipment->status_label }}
                    </span>
                    <div style="margin-top:12px; font-size:12px; color: var(--text-muted);">
                        No. Pengiriman: <strong>{{ $shipment->shipment_number }}</strong>
                    </div>
                    @if($shipment->tracking_number)
                        <div style="margin-top:8px; font-size:13px; font-weight:700; font-family:monospace; background: var(--bg-card); padding: 8px 12px; border-radius:8px; border: 1px solid var(--border-color);">
                            {{ $shipment->tracking_number }}
                        </div>
                        <div style="font-size:11px; color: var(--text-muted); margin-top:4px;">Nomor Resi</div>
                    @endif
                    <div style="margin-top:12px; font-size:12px; color: var(--text-muted);">
                        Dibuat: {{ $shipment->created_at->format('d M Y H:i') }}
                    </div>
                </div>
            </div>

            {{-- Update Status Form --}}
            <div class="glass-card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-edit"></i> Update Status</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.shipments.update', $shipment) }}">
                        @csrf
                        @method('PATCH')

                        <div style="margin-bottom:16px;">
                            <label style="font-size:12px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:6px;">STATUS PENGIRIMAN</label>
                            <select name="status" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-card); color:var(--text-primary); font-size:14px;">
                                <option value="pending"    {{ $shipment->status === 'pending'    ? 'selected' : '' }}>⏳ Menunggu</option>
                                <option value="confirmed"  {{ $shipment->status === 'confirmed'  ? 'selected' : '' }}>✅ Dikonfirmasi</option>
                                <option value="picked_up"  {{ $shipment->status === 'picked_up'  ? 'selected' : '' }}>📦 Paket Diambil</option>
                                <option value="in_transit" {{ $shipment->status === 'in_transit' ? 'selected' : '' }}>🚚 Dalam Perjalanan</option>
                                <option value="delivered"  {{ $shipment->status === 'delivered'  ? 'selected' : '' }}>✅ Terkirim</option>
                                <option value="cancelled"  {{ $shipment->status === 'cancelled'  ? 'selected' : '' }}>❌ Dibatalkan</option>
                            </select>
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="font-size:12px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:6px;">NOMOR RESI EKSPEDISI</label>
                            <input type="text" name="tracking_number"
                                   value="{{ $shipment->tracking_number }}"
                                   placeholder="Masukkan nomor resi..."
                                   style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-card); color:var(--text-primary); font-size:14px; font-family:monospace; box-sizing:border-box;">
                        </div>

                        <div style="margin-bottom:16px;">
                            <label style="font-size:12px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:6px;">CATATAN INTERNAL</label>
                            <textarea name="notes" rows="3"
                                      placeholder="Catatan untuk tim internal..."
                                      style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border-color); background:var(--bg-card); color:var(--text-primary); font-size:14px; resize:vertical; box-sizing:border-box;">{{ $shipment->notes }}</textarea>
                        </div>

                        <button type="submit" style="width:100%; padding:12px; border-radius:8px; background: var(--accent-primary-light); color:white; font-weight:700; border:none; cursor:pointer; font-size:14px;">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            {{-- Back Button --}}
            <a href="{{ route('admin.shipments.index') }}"
               style="display:block; text-align:center; padding:10px; border-radius:8px; border:1px solid var(--border-color); color:var(--text-muted); font-size:13px; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>

        </div>
    </div>
@endsection
