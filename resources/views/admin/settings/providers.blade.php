@extends('layouts.admin')

@section('title', 'Integrasi & Provider Eksternal')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Provider &amp; Integrasi Eksternal</h1>
                <span class="badge badge-green">
                    <span class="badge-dot"></span>
                    Gateway Operasional
                </span>
            </div>
            <p class="page-subtitle">Pusat orkestrasi integrasi gateway WhatsApp Fonnte, peta geolokasi, pembayaran digital, dan tarif ongkir.</p>
        </div>
    </div>

    {{-- Grid Kartu Provider --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- KARTU 1: WhatsApp Fonnte API --}}
        <div class="card flex flex-col justify-between">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <span class="material-symbols-outlined text-[20px]">chat</span>
                        </div>
                        <div>
                            <div class="card-title">WhatsApp Gateway (Fonnte)</div>
                            <div class="card-subtitle">Notifikasi order instan, QR code resi, dan live tracking</div>
                        </div>
                    </div>
                    @if(!empty($whatsapp['token']))
                        <span class="badge badge-green">Terhubung</span>
                    @else
                        <span class="badge badge-amber">Belum Aktif</span>
                    @endif
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Mengirim pesan konfirmasi pesanan berhasil ke WhatsApp pelanggan, link live tracking publik kurir, dan kode OTP login pengemudi.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}" class="space-y-3 pt-1">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="form-label text-xs">Fonnte API Token</label>
                            <input type="text"
                                   name="fonnte_token"
                                   value="{{ $whatsapp['token'] }}"
                                   placeholder="Token aktif Fonnte..."
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label text-xs">Nomor WhatsApp Pengirim</label>
                            <input type="text"
                                   name="fonnte_send_number"
                                   value="{{ $whatsapp['send_number'] }}"
                                   placeholder="08123456789"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.whatsapp') }}" class="text-xs text-brand-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan Kredensial
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 2: Peta & Geolocation Routing --}}
        <div class="card flex flex-col justify-between">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                            <span class="material-symbols-outlined text-[20px]">map</span>
                        </div>
                        <div>
                            <div class="card-title">Peta &amp; Geocoding</div>
                            <div class="card-subtitle">Perhitungan jarak KM, rute instan, dan live tracking kurir</div>
                        </div>
                    </div>
                    <span class="badge badge-indigo">
                        {{ strtoupper($map['provider'] ?? 'OSRM') }}
                    </span>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Menghitung jarak penjemputan ke tujuan secara akurat untuk kalkulasi tarif ongkir GoSend model dan visualisasi kurir di peta publik.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.map.update') }}" class="space-y-3 pt-1">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Provider Peta</label>
                                <select name="map_provider" class="form-select text-xs">
                                    <option value="osrm" {{ ($map['provider'] ?? '') === 'osrm' ? 'selected' : '' }}>OpenStreetMap (OSRM Gratis)</option>
                                    <option value="google" {{ ($map['provider'] ?? '') === 'google' ? 'selected' : '' }}>Google Maps Platform</option>
                                    <option value="mapbox" {{ ($map['provider'] ?? '') === 'mapbox' ? 'selected' : '' }}>Mapbox API</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">API Key (Opsional)</label>
                                <input type="text"
                                       name="map_api_key"
                                       value="{{ $map['api_key'] ?? '' }}"
                                       placeholder="AIzaSy..."
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">Routing Base URL</label>
                            <input type="text"
                                   name="map_base_url"
                                   value="{{ $map['base_url'] ?? '' }}"
                                   placeholder="https://router.project-osrm.org"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.map') }}" class="text-xs text-brand-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan Peta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 3: OVO SNAP (Pencairan Dompet Kurir) --}}
        <div class="card flex flex-col justify-between border-purple-200">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-purple-50 text-purple-700">
                            <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="card-title">OVO SNAP (Pencairan Kurir)</div>
                            <div class="card-subtitle">Integrasi disbursement OVO SNAP B2B untuk dompet kurir</div>
                        </div>
                    </div>
                    <span class="badge {{ ($ovo['mode'] ?? 'mock') === 'production' ? 'badge-green' : 'badge-amber' }}">
                        {{ strtoupper($ovo['mode'] ?? 'MOCK') }}
                    </span>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Memproses verifikasi akun kurir (Account Inquiry) dan pencairan saldo pendapatan langsung ke akun OVO kurir via API OVO SNAP.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.ovo.update') }}" class="space-y-3 pt-1">
                        @csrf

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Mode Lingkungan</label>
                                <select name="ovo_mode" class="form-select text-xs">
                                    <option value="mock" {{ ($ovo['mode'] ?? '') === 'mock' ? 'selected' : '' }}>Mock / Simulasi</option>
                                    <option value="sandbox" {{ ($ovo['mode'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox OVO</option>
                                    <option value="production" {{ ($ovo['mode'] ?? '') === 'production' ? 'selected' : '' }}>Production Live</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="text"
                                       name="ovo_api_base_url"
                                       value="{{ $ovo['api_base_url'] ?? 'https://api.ovo.id' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Client ID / Key</label>
                                <input type="text"
                                       name="ovo_client_id"
                                       value="{{ $ovo['client_id'] ?? '' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Partner ID</label>
                                <input type="text"
                                       name="ovo_partner_id"
                                       value="{{ $ovo['partner_id'] ?? '' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.ovo') }}" class="text-xs text-purple-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap OVO &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan OVO
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 4: Tarif RajaOngkir / Ekspedisi --}}
        <div class="card flex flex-col justify-between">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                            <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                        </div>
                        <div>
                            <div class="card-title">Tarif Ekspedisi (RajaOngkir)</div>
                            <div class="card-subtitle">Integrasi referensi tarif reguler antar-kota di Indonesia</div>
                        </div>
                    </div>
                    @if(!empty($rajaongkir['api_key']))
                        <span class="badge badge-green">Terkoneksi</span>
                    @else
                        <span class="badge badge-gray">Standar Lokal</span>
                    @endif
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Data acuan ongkos kirim standar antarkecamatan dan antarkota untuk pengiriman kargo dan pengiriman drop point ke drop point.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.rajaongkir.update') }}" class="space-y-3 pt-1">
                        @csrf
                        @method('PUT')

                        <div>
                            <label class="form-label text-xs">API Key RajaOngkir</label>
                            <input type="text"
                                   name="rajaongkir_api_key"
                                   value="{{ $rajaongkir['api_key'] ?? '' }}"
                                   placeholder="Key akun RajaOngkir..."
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label text-xs">Tipe Akun</label>
                            <select name="rajaongkir_account_type" class="form-select text-xs">
                                <option value="starter" {{ ($rajaongkir['account_type'] ?? '') === 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                                <option value="basic" {{ ($rajaongkir['account_type'] ?? '') === 'basic' ? 'selected' : '' }}>Basic</option>
                                <option value="pro" {{ ($rajaongkir['account_type'] ?? '') === 'pro' ? 'selected' : '' }}>Pro (Mendukung Kecamatan)</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.rajaongkir') }}" class="text-xs text-brand-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan RajaOngkir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
