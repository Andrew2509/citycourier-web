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
            <p class="page-subtitle">Pusat orkestrasi integrasi gateway Xendit, DOKU, iPaymu, WhatsApp Fonnte, peta geolokasi, pembayaran digital (DANA/OVO), dan tarif ongkir. API key tersimpan terenkripsi di database.</p>
        </div>
    </div>

    {{-- Grid Kartu Provider --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- KARTU 1: WhatsApp Fonnte API --}}
        <div class="card flex flex-col justify-between {{ !($providerStates['whatsapp'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <span class="cc-icon text-[20px]">chat</span>
                        </div>
                        <div>
                            <div class="card-title">WhatsApp Gateway (Fonnte)</div>
                            <div class="card-subtitle">Notifikasi order instan, QR code resi, dan live tracking</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!empty($whatsapp['token']))
                            <span class="badge badge-green">Terhubung</span>
                        @else
                            <span class="badge badge-amber">Belum Aktif</span>
                        @endif
                        @include('admin.settings._provider_toggle', ['provider' => 'whatsapp', 'enabled' => $providerStates['whatsapp'] ?? true])
                    </div>
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
                            <a href="{{ route('admin.settings.whatsapp') }}" class="text-xs text-primary-700 hover:underline font-medium inline-flex items-center gap-1">
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
        <div class="card flex flex-col justify-between {{ !($providerStates['map'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                            <span class="cc-icon text-[20px]">map</span>
                        </div>
                        <div>
                            <div class="card-title">Peta &amp; Geocoding</div>
                            <div class="card-subtitle">Perhitungan jarak KM, rute instan, dan live tracking kurir</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge badge-indigo">
                            {{ strtoupper($map['provider'] ?? 'OSRM') }}
                        </span>
                        @include('admin.settings._provider_toggle', ['provider' => 'map', 'enabled' => $providerStates['map'] ?? true])
                    </div>
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
                            <a href="{{ route('admin.settings.map') }}" class="text-xs text-primary-700 hover:underline font-medium inline-flex items-center gap-1">
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
        <div class="card flex flex-col justify-between border-sky-200 {{ !($providerStates['dana'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-sky-50 text-sky-700">
                            <span class="cc-icon text-[20px]">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="card-title">DANA Widget Binding (Pencairan Kurir)</div>
                            <div class="card-subtitle">Metode pencairan aktif: penghubungan akun &amp; disbursement DANA</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge {{ ($dana['mode'] ?? 'mock') === 'production' ? 'badge-green' : (($dana['mode'] ?? 'mock') === 'sandbox' ? 'badge-indigo' : 'badge-amber') }}">
                            {{ strtoupper($dana['mode'] ?? 'MOCK') }}
                        </span>
                        @include('admin.settings._provider_toggle', ['provider' => 'dana', 'enabled' => $providerStates['dana'] ?? true])
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
        <div class="card flex flex-col justify-between border-purple-200 {{ !($providerStates['ovo'] ?? false) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-purple-50 text-purple-700">
                            <span class="cc-icon text-[20px]">account_balance_wallet</span>
                        </div>
                        <div>
                            <div class="card-title">OVO SNAP (Arsip &mdash; Nonaktif)</div>
                            <div class="card-subtitle">Disimpan untuk riwayat transaksi; pencairan baru memakai DANA</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge {{ ($ovo['mode'] ?? 'mock') === 'production' ? 'badge-green' : 'badge-amber' }}">
                            {{ strtoupper($ovo['mode'] ?? 'MOCK') }}
                        </span>
                        @include('admin.settings._provider_toggle', ['provider' => 'ovo', 'enabled' => $providerStates['ovo'] ?? false])
                    </div>
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

        {{-- KARTU: iPaymu (Checkout VA/QRIS & Pencairan Kurir) --}}
        <div class="card flex flex-col justify-between border-slate-200 {{ !($providerStates['ipaymu'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-orange-50 text-orange-700">
                            <span class="cc-icon text-[20px]">payments</span>
                        </div>
                        <div>
                            <div class="card-title">iPaymu (Checkout VA/QRIS &amp; Payout)</div>
                            <div class="card-subtitle">Pembayaran Checkout (VA &amp; QRIS) + pencairan saldo kurir</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        @include('admin.settings._provider_toggle', ['provider' => 'ipaymu', 'enabled' => $providerStates['ipaymu'] ?? true])
                        <div class="flex items-center gap-1">
                            @if(!empty($ipaymu['configured']))
                                <span class="badge badge-green">Terkonfigurasi</span>
                            @else
                                <span class="badge badge-amber">Belum Diisi</span>
                            @endif
                            <span class="badge {{ str_contains(($ipaymu['base_url'] ?? ''), 'sandbox') ? 'badge-indigo' : 'badge-green' }}">
                                {{ str_contains(($ipaymu['base_url'] ?? ''), 'sandbox') ? 'SANDBOX' : 'PRODUCTION' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Toggle <strong>ON/OFF</strong> di atas mengontrol tampil/tidaknya <strong>Virtual Account &amp; QRIS</strong> di Checkout sekaligus pencairan kurir ke DANA. Pastikan <strong>VA</strong> &amp; <strong>API Key</strong> terisi agar saluran Checkout muncul.
                    </p>

                    <form method="POST" action="{{ route('admin.settings.ipaymu.update') }}" id="ipaymuForm" class="space-y-3 pt-1">
                        @csrf

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="text"
                                       name="ipaymu_base_url"
                                       value="{{ $ipaymu['base_url'] ?? 'https://sandbox.ipaymu.com' }}"
                                       placeholder="https://sandbox.ipaymu.com"
                                       class="form-input font-mono text-xs">
                                <p class="text-[10px] text-slate-400 mt-1">Sandbox: sandbox.ipaymu.com · Prod: my.ipaymu.com</p>
                            </div>
                            <div>
                                <label class="form-label text-xs">Virtual Account (VA)</label>
                                <input type="text"
                                       name="ipaymu_va"
                                       value="{{ $ipaymu['va'] ?? '' }}"
                                       placeholder="VA Merchant iPaymu"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">API Key</label>
                            <input type="password"
                                   name="ipaymu_api_key"
                                   value="{{ $ipaymu['api_key'] ?? '' }}"
                                   autocomplete="off"
                                   placeholder="API Key iPaymu..."
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

                        <div id="ipaymuTestResult" class="hidden rounded-md p-2.5 text-xs"></div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                            <a href="{{ route('admin.settings.ipaymu') }}" class="text-xs text-orange-700 hover:underline font-medium inline-flex items-center gap-1">
                                Konfigurasi Lengkap &rarr;
                            </a>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        onclick="testPaymentGateway('ipaymu', '{{ route('admin.settings.ipaymu.test') }}', 'ipaymuForm', 'ipaymuTestResult')"
                                        class="btn btn-sm btn-secondary">
                                    Test Koneksi &amp; Channel
                                </button>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Simpan iPaymu
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 4: Tarif RajaOngkir / Ekspedisi --}}
        <div class="card flex flex-col justify-between {{ !($providerStates['rajaongkir'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-amber-50 text-amber-700">
                            <span class="cc-icon text-[20px]">local_shipping</span>
                        </div>
                        <div>
                            <div class="card-title">Tarif Ekspedisi (RajaOngkir)</div>
                            <div class="card-subtitle">Integrasi referensi tarif reguler antar-kota di Indonesia</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!empty($rajaongkir['api_key']))
                            <span class="badge badge-green">Terkoneksi</span>
                        @else
                            <span class="badge badge-gray">Standar Lokal</span>
                        @endif
                        @include('admin.settings._provider_toggle', ['provider' => 'rajaongkir', 'enabled' => $providerStates['rajaongkir'] ?? true])
                    </div>
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
                            <a href="{{ route('admin.settings.rajaongkir') }}" class="text-xs text-primary-700 hover:underline font-medium inline-flex items-center gap-1">
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

        {{-- KARTU 5: Xendit Payment & Payouts v2/v3 --}}
        <div class="card flex flex-col justify-between border-indigo-200 {{ !($providerStates['xendit'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-indigo-50 text-indigo-700">
                            <span class="cc-icon text-[20px]">payments</span>
                        </div>
                        <div>
                            <div class="card-title">Xendit Gateway (Money-In &amp; Payouts)</div>
                            <div class="card-subtitle">Top-Up VA/QRIS/E-Wallet v3 &amp; Auto-Payout Kurir v2</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        @include('admin.settings._provider_toggle', ['provider' => 'xendit', 'enabled' => $providerStates['xendit'] ?? true])
                        <div class="flex items-center gap-1">
                            @if(!empty($xendit['configured']))
                                <span class="badge badge-green">Terkonfigurasi</span>
                            @else
                                <span class="badge badge-amber">Belum Diisi</span>
                            @endif
                            <span class="badge {{ ($xendit['is_sandbox'] ?? true) ? 'badge-indigo' : 'badge-green' }}">
                                {{ ($xendit['is_sandbox'] ?? true) ? 'SANDBOX' : 'PRODUCTION' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Masukkan API key Xendit langsung di sini tanpa perlu mengedit file <code class="font-mono">.env</code>. Secret Key dan Callback Token dienkripsi otomatis di database.
                    </p>

                    <div class="rounded-md bg-slate-50 p-2.5 text-[11px] text-slate-600 space-y-1 font-mono border border-slate-200">
                        <div>Webhook Payment: <span class="text-slate-900 select-all">{{ $xendit['payment_webhook_url'] ?? url('/api/v1/webhooks/xendit/payment') }}</span></div>
                        <div>Webhook Payout: <span class="text-slate-900 select-all">{{ $xendit['payout_webhook_url'] ?? url('/api/v1/webhooks/xendit/payout') }}</span></div>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.xendit.update') }}" id="xenditForm" class="space-y-3 pt-1">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="url"
                                       name="xendit_base_url"
                                       value="{{ $xendit['base_url'] ?? 'https://api.xendit.co' }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Public Key</label>
                                <input type="text"
                                       name="xendit_public_key"
                                       value="{{ $xendit['public_key'] ?? '' }}"
                                       placeholder="xnd_public_development_..."
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">
                                Secret API Key
                                @if(!empty($xendit['secret_masked']))
                                    <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $xendit['secret_masked'] }}</code>)</span>
                                @endif
                            </label>
                            <input type="password"
                                   name="xendit_secret_key"
                                   value=""
                                   placeholder="{{ !empty($xendit['secret_masked']) ? 'Biarkan kosong untuk mempertahankan key tersimpan' : 'xnd_development_... atau xnd_production_...' }}"
                                   autocomplete="new-password"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div>
                            <label class="form-label text-xs">
                                Webhook Callback Token (<code class="font-mono">x-callback-token</code>)
                                @if(!empty($xendit['callback_masked']))
                                    <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $xendit['callback_masked'] }}</code>)</span>
                                @endif
                            </label>
                            <input type="password"
                                   name="xendit_callback_token"
                                   value=""
                                   placeholder="{{ !empty($xendit['callback_masked']) ? 'Biarkan kosong untuk mempertahankan token tersimpan' : 'Token verifikasi webhook Xendit...' }}"
                                   autocomplete="new-password"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <label class="text-xs flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="activate_xendit" value="1"
                                       {{ ($xendit['is_active_payment'] ?? false) ? 'checked' : '' }}
                                       class="h-3.5 w-3.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-slate-600">Jadikan Xendit provider aktif (Payment &amp; Payout)</span>
                            </label>
                            <label class="text-xs flex items-center gap-1.5 cursor-pointer text-red-600">
                                <input type="checkbox" name="clear_xendit_secrets" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                <span>Hapus Secret Tersimpan</span>
                            </label>
                        </div>

                        <div id="xenditTestResult" class="hidden rounded-md p-2.5 text-xs"></div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button"
                                    onclick="testPaymentGateway('xendit', '{{ route('admin.settings.xendit.test') }}', 'xenditForm', 'xenditTestResult')"
                                    class="btn btn-sm btn-secondary">
                                Test Koneksi Xendit
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan Xendit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 6: DOKU Jokul & Kirim DOKU Gateway --}}
        <div class="card flex flex-col justify-between border-rose-200 {{ !($providerStates['doku'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-rose-50 text-rose-700">
                            <span class="cc-icon text-[20px]">credit_card</span>
                        </div>
                        <div>
                            <div class="card-title">DOKU Jokul &amp; Kirim DOKU (Inquiry)</div>
                            <div class="card-subtitle">Checkout Money-In + Cek Nama Asli Bank &amp; E-Wallet (SNAP)</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        @include('admin.settings._provider_toggle', ['provider' => 'doku', 'enabled' => $providerStates['doku'] ?? true])
                        <div class="flex flex-wrap justify-end items-center gap-1">
                            @if(!empty($doku['configured']))
                                <span class="badge badge-green">Terkonfigurasi</span>
                            @else
                                <span class="badge badge-amber">Belum Diisi</span>
                            @endif
                            @if(!empty($doku['kirim_configured']) && !empty($doku['kirim_inquiry_enabled']))
                                <span class="badge badge-green">Cek Nama Aktif</span>
                            @else
                                <span class="badge badge-amber">Inquiry Butuh Private Key</span>
                            @endif
                            @if(!empty($doku['is_active_payment']))
                                <span class="badge badge-indigo">AKTIF</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Kredensial <code class="font-mono">Client-Id</code> &amp; <code class="font-mono">Secret Key</code> dipakai untuk Jokul, sedangkan <code class="font-mono">Private Key RSA</code> dipakai untuk <strong>Kirim DOKU SNAP</strong> (cek nama asli pemilik rekening Bank &amp; E-Wallet). Secret Key &amp; Private Key terenkripsi di database.
                    </p>

                    <div class="rounded-md bg-slate-50 p-2.5 text-[11px] text-slate-600 font-mono border border-slate-200">
                        Webhook URL: <span class="text-slate-900 select-all">{{ $doku['webhook_url'] ?? url('/api/v1/webhooks/doku') }}</span>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.doku.update') }}" id="dokuForm" class="space-y-3 pt-1">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">Sandbox Base URL</label>
                                <input type="url"
                                       name="doku_sandbox_url"
                                       value="{{ $doku['sandbox_url'] ?? 'https://api-sandbox.doku.com' }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Production Base URL</label>
                                <input type="url"
                                       name="doku_production_url"
                                       value="{{ $doku['production_url'] ?? 'https://api.doku.com' }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">DOKU Client-Id (Checkout &amp; Default)</label>
                                <input type="text"
                                       name="doku_client_id"
                                       value="{{ $doku['client_id'] ?? '' }}"
                                       placeholder="BRN-.... atau MCH-...."
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Partner ID Kirim DOKU (Opsional)</label>
                                <input type="text"
                                       name="doku_kirim_partner_id"
                                       value="{{ $doku['kirim_partner_id'] ?? '' }}"
                                       placeholder="Kosongkan jika sama dengan Client-Id"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">
                                DOKU Secret Key
                                @if(!empty($doku['secret_masked']))
                                    <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $doku['secret_masked'] }}</code>)</span>
                                @endif
                            </label>
                            <input type="password"
                                   name="doku_secret_key"
                                   value=""
                                   placeholder="{{ !empty($doku['secret_masked']) ? 'Biarkan kosong untuk mempertahankan key tersimpan' : 'SK-....' }}"
                                   autocomplete="new-password"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">SNAP Channel-Id</label>
                                <input type="text"
                                       name="doku_kirim_channel_id"
                                       value="{{ $doku['kirim_channel_id'] ?? 'H2H' }}"
                                       placeholder="H2H"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">No. HP Pengirim (Customer Number)</label>
                                <input type="text"
                                       name="doku_customer_number"
                                       value="{{ $doku['kirim_customer_number'] ?? '6281234567890' }}"
                                       placeholder="6281234567890"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label class="form-label text-xs mb-0">
                                    DOKU Private Key RSA (Kirim DOKU SNAP)
                                    @if(!empty($doku['has_private_key']))
                                        <span class="text-[11px] font-normal text-emerald-700">(Tersimpan)</span>
                                    @endif
                                </label>
                                <button type="button"
                                        onclick="generateDokuKeypair('{{ url('/admin/settings/doku/generate-keys') }}')"
                                        class="text-[11px] font-semibold text-rose-700 hover:underline">
                                    Generate RSA Keypair
                                </button>
                            </div>
                            <textarea name="doku_private_key"
                                      id="dokuPrivateKeyInput"
                                      rows="2"
                                      placeholder="{{ !empty($doku['has_private_key']) ? 'Biarkan kosong untuk mempertahankan Private Key tersimpan...' : '-----BEGIN PRIVATE KEY----- ...' }}"
                                      class="form-input font-mono text-[10px] leading-tight"></textarea>
                            <div id="dokuPublicKeyWrap" class="{{ empty($doku['public_key_derived']) ? 'hidden' : '' }} space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-medium text-slate-600">Public Key (tempel ke DOKU Dashboard &rarr; Edit Merchant Public Key):</span>
                                    <button type="button"
                                            onclick="navigator.clipboard?.writeText(document.getElementById('dokuPublicKeyOutput').value); this.textContent='Tersalin';"
                                            class="text-[11px] font-semibold text-slate-700 hover:underline">Salin Public Key</button>
                                </div>
                                <textarea id="dokuPublicKeyOutput"
                                          rows="2"
                                          readonly
                                          class="form-input font-mono text-[10px] leading-tight bg-white text-slate-600">{{ $doku['public_key_derived'] ?? '' }}</textarea>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <div class="space-y-1">
                                <label class="text-xs flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="doku_kirim_inquiry_enabled" value="1"
                                           {{ ($doku['kirim_inquiry_enabled'] ?? true) ? 'checked' : '' }}
                                           class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span class="text-slate-600">Aktifkan Cek Nama Asli Bank &amp; E-Wallet (Kirim DOKU)</span>
                                </label>
                                <label class="text-xs flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="activate_doku" value="1"
                                           {{ ($doku['is_active_payment'] ?? false) ? 'checked' : '' }}
                                           class="h-3.5 w-3.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span class="text-slate-600">Jadikan DOKU provider Money-In aktif</span>
                                </label>
                            </div>
                            <label class="text-xs flex items-center gap-1.5 cursor-pointer text-red-600">
                                <input type="checkbox" name="clear_doku_secrets" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                <span>Hapus Secret Tersimpan</span>
                            </label>
                        </div>

                        <div id="dokuTestResult" class="hidden rounded-md p-2.5 text-xs"></div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button"
                                    onclick="testPaymentGateway('doku', '{{ route('admin.settings.doku.test') }}', 'dokuForm', 'dokuTestResult')"
                                    class="btn btn-sm btn-secondary">
                                Test Koneksi DOKU
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan DOKU
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
async function testPaymentGateway(name, endpoint, formId, resultBoxId) {
    const form = document.getElementById(formId);
    const box = document.getElementById(resultBoxId);
    if (!form || !box) return;

    box.className = 'rounded-md p-2.5 text-xs bg-slate-100 text-slate-700';
    box.textContent = 'Menguji koneksi ke ' + name.toUpperCase() + '...';

    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
            },
            body: new FormData(form),
        });
        const text = await res.text();
        let json = {};
        try {
            json = JSON.parse(text);
        } catch (_) {
            json = { success: false, message: 'Server merespons HTTP ' + res.status + ' (non-JSON).' };
        }
        box.className = json.success
            ? 'rounded-md p-2.5 text-xs bg-emerald-50 text-emerald-800 border border-emerald-200'
            : 'rounded-md p-2.5 text-xs bg-red-50 text-red-800 border border-red-200';
        box.textContent = json.message || ('HTTP ' + res.status);
    } catch (err) {
        box.className = 'rounded-md p-2.5 text-xs bg-red-50 text-red-800 border border-red-200';
        box.textContent = 'Gagal menguji koneksi: ' + err.message;
    }
}

function arrayBufferToPem(buffer, label) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const b64 = btoa(binary);
    const wrapped = (b64.match(/.{1,64}/g) || []).join('\n');
    return `-----BEGIN ${label}-----\n${wrapped}\n-----END ${label}-----`;
}

async function generateRsaInBrowser() {
    const pair = await window.crypto.subtle.generateKey(
        {
            name: 'RSASSA-PKCS1-v1_5',
            modulusLength: 2048,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: 'SHA-256',
        },
        true,
        ['sign', 'verify']
    );
    const [privBuf, pubBuf] = await Promise.all([
        window.crypto.subtle.exportKey('pkcs8', pair.privateKey),
        window.crypto.subtle.exportKey('spki', pair.publicKey),
    ]);
    return {
        private_key: arrayBufferToPem(privBuf, 'PRIVATE KEY'),
        public_key: arrayBufferToPem(pubBuf, 'PUBLIC KEY'),
    };
}

async function generateDokuKeypair(fallbackEndpoint) {
    const form = document.getElementById('dokuForm');
    const privBox = document.getElementById('dokuPrivateKeyInput');
    const pubBox = document.getElementById('dokuPublicKeyOutput');
    const pubWrap = document.getElementById('dokuPublicKeyWrap');
    const resultBox = document.getElementById('dokuTestResult');
    if (!form || !privBox || !pubBox || !pubWrap) return;

    try {
        let keys;
        if (window.crypto && window.crypto.subtle && window.isSecureContext) {
            keys = await generateRsaInBrowser();
        } else {
            const res = await fetch(fallbackEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
            });
            const json = await res.json();
            if (!res.ok || !json.success) {
                throw new Error(json.message || ('HTTP ' + res.status));
            }
            keys = json;
        }
        privBox.value = keys.private_key || '';
        pubBox.value = keys.public_key || '';
        pubWrap.classList.remove('hidden');
        if (resultBox) {
            resultBox.className = 'rounded-md p-2.5 text-xs bg-emerald-50 text-emerald-800 border border-emerald-200';
            resultBox.textContent = 'Keypair RSA-2048 dibuat. Salin Public Key ke DOKU Dashboard (Edit Merchant Public Key), lalu klik Simpan DOKU.';
        }
    } catch (err) {
        if (resultBox) {
            resultBox.className = 'rounded-md p-2.5 text-xs bg-red-50 text-red-800 border border-red-200';
            resultBox.textContent = 'Gagal membuat keypair RSA: ' + err.message;
        }
    }
}
</script>
@endpush
@endsection
