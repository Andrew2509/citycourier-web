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
            <p class="page-subtitle">Pusat orkestrasi integrasi gateway WhatsApp Fonnte, peta geolokasi, pembayaran digital (DANA), dan tarif ongkir.</p>
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
                        {{-- Pertahankan provider yang dipilih agar simpan cepat tidak mengubahnya --}}
                        <input type="hidden" name="whatsapp_provider" value="{{ $whatsapp['provider'] ?? 'fonnte' }}">

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

        {{-- KARTU 3: DANA WIDGET BINDING (Metode Pencairan Aktif) --}}
        <div class="card flex flex-col justify-between border-sky-200">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-sky-50 text-sky-700">
                            <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="card-title">DANA Widget Binding (Pencairan Kurir)</div>
                            <div class="card-subtitle">Metode pencairan aktif: penghubungan akun &amp; disbursement DANA</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-green">Aktif</span>
                        <span class="badge {{ ($dana['mode'] ?? 'mock') === 'production' ? 'badge-green' : (($dana['mode'] ?? 'mock') === 'sandbox' ? 'badge-indigo' : 'badge-amber') }}">
                            {{ strtoupper($dana['mode'] ?? 'MOCK') }}
                        </span>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Menghubungkan akun DANA kurir (Widget Binding / Deeplink Binding) dan mencairkan saldo pendapatan ke akun DANA kurir.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.dana.update') }}" class="space-y-3 pt-1">
                        @csrf

                        {{-- Dipertahankan agar simpan cepat tidak mengubah callback URL yang terdaftar di DANA --}}
                        <input type="hidden" name="dana_callback_url" value="{{ $dana['callback_url'] ?? url('/api/courier/dana/callback') }}">

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Mode Lingkungan</label>
                                <select name="dana_mode" class="form-select text-xs">
                                    <option value="mock" {{ ($dana['mode'] ?? '') === 'mock' ? 'selected' : '' }}>Mock / Simulasi</option>
                                    <option value="sandbox" {{ ($dana['mode'] ?? '') === 'sandbox' ? 'selected' : '' }}>Sandbox DANA</option>
                                    <option value="production" {{ ($dana['mode'] ?? '') === 'production' ? 'selected' : '' }}>Production Live</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="text"
                                       name="dana_api_base_url"
                                       value="{{ $dana['api_base_url'] ?? 'https://api.sandbox.dana.id' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Client ID</label>
                                <input type="text"
                                       name="dana_client_id"
                                       value="{{ $dana['client_id'] ?? '' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Merchant ID</label>
                                <input type="text"
                                       name="dana_merchant_id"
                                       value="{{ $dana['merchant_id'] ?? '' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.dana') }}" class="text-xs text-sky-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap DANA (private key, dsb.) &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan DANA
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 4: OVO SNAP (Arsip — nonaktif) --}}
        <div class="card flex flex-col justify-between border-purple-200">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-purple-50 text-purple-700">
                            <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="card-title">OVO SNAP (Arsip &mdash; Nonaktif)</div>
                            <div class="card-subtitle">Disimpan untuk riwayat transaksi; pencairan baru memakai DANA</div>
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

        {{-- KARTU: iPaymu Payout (Disbursement Pencairan Kurir) --}}
        <div class="card flex flex-col justify-between border-slate-200">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-orange-50 text-orange-700">
                            <span class="material-symbols-outlined text-[20px]">payments</span>
                        </div>
                        <div>
                            <div class="card-title">iPaymu Payout (Pencairan Kurir)</div>
                            <div class="card-subtitle">Disbursement saldo kurir ke akun DANA via iPaymu</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="badge {{ ($ipaymu['payout_enabled'] ?? false) ? 'badge-green' : 'badge-amber' }}">
                            {{ ($ipaymu['payout_enabled'] ?? false) ? 'Payout Aktif' : 'Payout Nonaktif' }}
                        </span>
                        <span class="badge {{ str_contains(($ipaymu['base_url'] ?? ''), 'sandbox') ? 'badge-indigo' : 'badge-green' }}">
                            {{ str_contains(($ipaymu['base_url'] ?? ''), 'sandbox') ? 'SANDBOX' : 'PRODUCTION' }}
                        </span>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Gateway pencairan saldo kurir ke DANA (tanpa binding: kurir memasukkan nomor tujuan saat penarikan). Status penarikan hanya "Berhasil" bila dikonfirmasi resmi oleh iPaymu.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.ipaymu.update') }}" class="space-y-3 pt-1">
                        @csrf

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="text"
                                       name="ipaymu_base_url"
                                       value="{{ $ipaymu['base_url'] ?? 'https://sandbox.ipaymu.com' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Virtual Account (VA)</label>
                                <input type="text"
                                       name="ipaymu_va"
                                       value="{{ $ipaymu['va'] ?? '' }}"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">API Key</label>
                            <input type="password"
                                   name="ipaymu_api_key"
                                   value="{{ $ipaymu['api_key'] ?? '' }}"
                                   autocomplete="off"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label text-xs">Email Buyer (fallback)</label>
                            <input type="email"
                                   name="ipaymu_buyer_email"
                                   value="{{ $ipaymu['buyer_email'] ?? '' }}"
                                   placeholder="cs@domain-anda.com"
                                   class="form-input font-mono text-xs">
                            <p class="text-[11px] text-slate-500 leading-relaxed mt-1">
                                Dipakai bila email pembeli tidak layak (kosong/domain tanpa email/hasil generate).
                                Isi dengan inbox yang aktif dan terdaftar di iPaymu agar transaksi tidak ditolak
                                sebagai "Suspicious buyer".
                            </p>
                        </div>

                        {{-- Nilai sensitif dipertahankan agar simpan cepat tidak menghapus secret tersimpan --}}
                        <input type="hidden" name="ipaymu_merchant_id" value="{{ $ipaymu['merchant_id'] ?? '' }}">
                        <input type="hidden" name="ipaymu_secret_key" value="{{ $ipaymu['secret_key'] ?? '' }}">
                        <input type="hidden" name="ipaymu_webhook_secret" value="{{ $ipaymu['webhook_secret'] ?? '' }}">
                        <label class="text-xs flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="ipaymu_payout_enabled" value="1"
                                   {{ ($ipaymu['payout_enabled'] ?? false) ? 'checked' : '' }}
                                   class="h-3.5 w-3.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span class="text-slate-600">Aktifkan Payout (setelah iPaymu konfirmasi aktivasi merchant)</span>
                        </label>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('admin.settings.ipaymu') }}" class="text-xs text-orange-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap iPaymu (secret, webhook, dsb.) &rarr;
                            </a>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan iPaymu
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
                        {{-- Field wajib yang tidak tampil di kartu, dipertahankan dari nilai tersimpan --}}
                        <input type="hidden" name="rajaongkir_provider" value="{{ $rajaongkir['provider'] ?? 'rajaongkir' }}">
                        @if(!empty($rajaongkir_sandbox))
                            <input type="hidden" name="rajaongkir_sandbox" value="1">
                        @endif

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
