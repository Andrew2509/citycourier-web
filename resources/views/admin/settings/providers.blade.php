@extends('layouts.admin')

@section('title', 'Provider & Integrasi')
@section('page-title', 'Provider & Integrasi')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">extension</span>
            </div>
            Provider & Integrasi
        </h1>
        <p class="text-sm text-slate-500 mt-1">Kelola semua konfigurasi provider eksternal dalam satu halaman</p>
    </div>

    {{-- Provider Cards --}}
    <div class="flex flex-col gap-4" id="provider-list">

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- 1. WhatsApp (Fonnte) --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="whatsapp">
            <button onclick="toggleProvider('whatsapp')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-green-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-green-600 text-xl">chat</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Provider WhatsApp</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Fonnte &mdash; OTP & notifikasi via WhatsApp</p>
                </div>
                @if(!empty($whatsapp['token']))
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold flex-shrink-0">Aktif</span>
                @else
                    <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold flex-shrink-0">Belum Diisi</span>
                @endif
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-whatsapp">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-whatsapp">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Fonnte API Token</label>
                                    <input type="text" name="fonnte_token" value="{{ old('fonnte_token', $whatsapp['token']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Masukkan token dari dashboard Fonnte">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Pengirim <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                    <input type="text" name="fonnte_send_number" value="{{ old('fonnte_send_number', $whatsapp['send_number']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Contoh: 08123456789">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                                    <select name="whatsapp_provider" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="fonnte" {{ ($whatsapp['provider'] ?? 'fonnte') == 'fonnte' ? 'selected' : '' }}>Fonnte (Recommended)</option>
                                        <option value="orbitwa" {{ ($whatsapp['provider'] ?? 'fonnte') == 'orbitwa' ? 'selected' : '' }}>OrbitWA (Legacy)</option>
                                        <option value="auto" {{ ($whatsapp['provider'] ?? 'fonnte') == 'auto' ? 'selected' : '' }}>Auto (Otomatis)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-primary/5 rounded-xl border border-dashed border-primary/30 p-4">
                                    <h4 class="text-xs font-bold text-primary mb-2 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Koneksi
                                    </h4>
                                    <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST">
                                        @csrf
                                        <input type="text" name="phone" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-primary/20" placeholder="Nomor HP (0812...)" required>
                                        <button type="submit" class="w-full px-3 py-2 rounded-lg text-xs font-semibold bg-white text-primary border border-primary/20 hover:bg-primary hover:text-white transition-all flex items-center justify-center gap-1.5">
                                            <span class="material-symbols-outlined text-sm">send</span> Kirim Test
                                        </button>
                                    </form>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500">
                                    <p>Digunakan untuk mengirim OTP via WhatsApp saat Login/Registrasi.</p>
                                    <p class="mt-1">Daftar: <a href="https://fonnte.com" target="_blank" class="text-primary hover:underline">fonnte.com</a></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Simpan WhatsApp
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- 2. RajaOngkir --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="rajaongkir">
            <button onclick="toggleProvider('rajaongkir')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-xl">local_shipping</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Provider RajaOngkir</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Cek ongkir, provinsi, kota & kecamatan</p>
                </div>
                @if(!empty($rajaongkir['api_key']))
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold flex-shrink-0">Aktif</span>
                @else
                    <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold flex-shrink-0">Belum Diisi</span>
                @endif
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-rajaongkir">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-rajaongkir">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">RajaOngkir API Key</label>
                                    <input type="text" name="rajaongkir_api_key" value="{{ old('rajaongkir_api_key', $rajaongkir['api_key']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Masukkan API Key dari dashboard RajaOngkir">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Service Provider</label>
                                        <select name="rajaongkir_provider" id="rajaongkir_provider" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="rajaongkir" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                                            <option value="komerce" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                                        </select>
                                    </div>
                                    <div id="rajaongkir_account_type_group">
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tipe Akun</label>
                                        <select name="rajaongkir_account_type" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="starter" {{ ($rajaongkir['account_type'] ?? 'starter') == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                                            <option value="basic" {{ ($rajaongkir['account_type'] ?? 'starter') == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                                            <option value="pro" {{ ($rajaongkir['account_type'] ?? 'starter') == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                                        </select>
                                    </div>
                                </div>
                                <label class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer" id="rajaongkir_sandbox_group" style="display:none;">
                                    <input type="checkbox" name="rajaongkir_sandbox" value="1" {{ $rajaongkir_sandbox ? 'checked' : '' }} class="w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700">Sandbox Mode</p>
                                        <p class="text-xs text-slate-400">Aktifkan untuk lingkungan Testing Komerce</p>
                                    </div>
                                </label>
                            </div>
                            <div class="space-y-4">
                                <div id="rajaongkir-test-area">
                                    <button type="button" id="btn-test-rajaongkir" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                        <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                    </button>
                                    <div id="rajaongkir-test-result" class="mt-3 hidden">
                                        <div id="rajaongkir-test-alert" class="p-3 rounded-xl">
                                            <p class="font-semibold text-xs" id="rajaongkir-test-title"></p>
                                            <p class="text-xs mt-1" id="rajaongkir-test-message"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Tipe Akun:</p>
                                    <p><strong>Starter</strong> &mdash; JNE, POS, TIKI</p>
                                    <p><strong>Pro</strong> &mdash; Sampai tingkat kecamatan</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Simpan RajaOngkir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- 3. Payment (Komerce) --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="payment">
            <button onclick="toggleProvider('payment')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-purple-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-purple-600 text-xl">credit_card</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Layanan Pembayaran</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Komerce Payment &mdash; Virtual Account & QRIS</p>
                </div>
                @if(!empty($payment['api_key']))
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold flex-shrink-0">Aktif</span>
                @else
                    <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold flex-shrink-0">Belum Diisi</span>
                @endif
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-payment">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-payment">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.payment.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                                        Komerce Payment API Key <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-600 rounded text-xs font-semibold">Wajib</span>
                                    </label>
                                    <input type="password" name="komerce_payment_api_key" id="payment_api_key" value="{{ old('komerce_payment_api_key', $payment['api_key']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Masukkan API Key dari Komerce" autocomplete="off">
                                    <p class="text-xs text-slate-400 mt-1">Dari <a href="https://collaborator.komerce.id" target="_blank" class="text-primary hover:underline">collaborator.komerce.id</a> &rarr; Integration</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Environment</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="komerce_payment_env" value="sandbox" class="hidden peer" {{ ($payment['env'] ?? 'sandbox') === 'sandbox' ? 'checked' : '' }}>
                                            <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-primary peer-checked:bg-primary/5 transition-all text-center">
                                                <span class="material-symbols-outlined text-xl text-slate-400 peer-checked:text-primary">science</span>
                                                <p class="text-sm font-semibold text-slate-700 mt-1">Sandbox</p>
                                                <p class="text-[10px] text-slate-400">Testing</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" name="komerce_payment_env" value="production" class="hidden peer" {{ ($payment['env'] ?? 'sandbox') === 'production' ? 'checked' : '' }}>
                                            <div class="p-3 rounded-xl border-2 border-slate-200 peer-checked:border-primary peer-checked:bg-primary/5 transition-all text-center">
                                                <span class="material-symbols-outlined text-xl text-slate-400 peer-checked:text-primary">rocket_launch</span>
                                                <p class="text-sm font-semibold text-slate-700 mt-1">Production</p>
                                                <p class="text-[10px] text-slate-400">Live</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Callback Key <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                    <input type="text" name="komerce_payment_callback_key" value="{{ old('komerce_payment_callback_key', $payment['callback_key']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Key untuk verifikasi webhook">
                                    <p class="text-xs text-slate-400 mt-1">Webhook: <code class="bg-slate-100 px-1 py-0.5 rounded">{{ config('app.url') }}/api/payment/callback</code></p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-slate-50 rounded-xl p-4 space-y-2">
                                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wider">Status</p>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-slate-500">API Key</span>
                                        <span class="font-semibold {{ $payment['api_key'] ? 'text-green-600' : 'text-red-500' }}">{{ $payment['api_key'] ? '✓ Terisi' : '✗ Kosong' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-slate-500">Mode</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ ($payment['env'] ?? 'sandbox') === 'production' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($payment['env'] ?? 'sandbox') }}</span>
                                    </div>
                                </div>
                                <button type="button" id="btn-test-payment" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                </button>
                                <div id="payment-test-result" class="hidden">
                                    <div id="payment-test-alert" class="p-3 rounded-xl">
                                        <p class="font-semibold text-xs" id="payment-test-title"></p>
                                        <p class="text-xs mt-1" id="payment-test-message"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Simpan Pembayaran
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- 4. Map --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="map">
            <button onclick="toggleProvider('map')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-amber-600 text-xl">map</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Konfigurasi Peta</h3>
                    <p class="text-xs text-slate-400 mt-0.5">OSRM / Mapbox / Google Maps &mdash; Routing & Autocomplete</p>
                </div>
                @if(!empty($map['base_url']))
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold flex-shrink-0">Aktif</span>
                @else
                    <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-lg text-xs font-semibold flex-shrink-0">Default</span>
                @endif
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-map">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-map">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.map.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Penyedia Peta</label>
                                    <select name="map_provider" id="map_provider" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="osrm" {{ ($map['provider'] ?? 'osrm') == 'osrm' ? 'selected' : '' }}>OSRM / OpenStreetMap (Recommended)</option>
                                        <option value="maplibre" {{ ($map['provider'] ?? 'osrm') == 'maplibre' ? 'selected' : '' }}>Maplibre GL (Open Source)</option>
                                        <option value="mapbox" {{ ($map['provider'] ?? 'osrm') == 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                                        <option value="google" {{ ($map['provider'] ?? 'osrm') == 'google' ? 'selected' : '' }}>Google Maps API</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">API Base URL</label>
                                    <input type="url" name="map_base_url" id="map_base_url" value="{{ old('map_base_url', $map['base_url']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="https://router.project-osrm.org">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Access Token / API Key</label>
                                    <input type="password" name="map_api_key" id="map_api_key" value="{{ old('map_api_key', $map['api_key']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Kosongkan untuk OSRM publik">
                                    <p class="text-xs text-slate-400 mt-1" id="api-key-hint">Semua request dari Flutter akan diproyeksi melalui Laravel.</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <button type="button" id="btn-test-map" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                </button>
                                <div id="map-test-result" class="hidden">
                                    <div id="map-test-alert" class="p-3 rounded-xl">
                                        <p class="font-semibold text-xs" id="map-test-title"></p>
                                        <p class="text-xs mt-1" id="map-test-message"></p>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Endpoints:</p>
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">POST</span>
                                        <code>/api/shipping/map/routing</code>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1 bg-green-100 text-green-700 rounded text-[10px] font-bold">GET</span>
                                        <code>/api/shipping/map/autocomplete</code>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-1 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">POST</span>
                                        <code>/api/shipping/map/matrix</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Simpan Peta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════ --}}
        {{-- 5. DANA --}}
        {{-- ═══════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="dana">
            <button onclick="toggleProvider('dana')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-blue-600/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-xl">account_balance_wallet</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Provider DANA</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Widget Binding &mdash; Dompet digital untuk kurir</p>
                </div>
                @if(!empty($dana['client_id']))
                    <span class="px-2.5 py-1 bg-green-100 text-green-700 rounded-lg text-xs font-semibold flex-shrink-0">Aktif</span>
                @else
                    <span class="px-2.5 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold flex-shrink-0">Belum Diisi</span>
                @endif
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-dana">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-dana">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Mode Provider</label>
                                        <select name="dana_mode" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="mock" {{ ($dana['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                            <option value="sandbox" {{ ($dana['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                            <option value="production" {{ ($dana['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Merchant ID</label>
                                        <input type="text" name="dana_merchant_id" value="{{ old('dana_merchant_id', $dana['merchant_id']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">API Base URL</label>
                                    <input type="url" name="dana_api_base_url" value="{{ old('dana_api_base_url', $dana['api_base_url']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="https://api.sandbox.dana.id">
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Client ID</label>
                                        <input type="text" name="dana_client_id" value="{{ old('dana_client_id', $dana['client_id']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Client Secret</label>
                                        <input type="password" name="dana_client_secret" value="{{ old('dana_client_secret', $dana['client_secret']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Callback URL</label>
                                    <input type="url" name="dana_callback_url" value="{{ old('dana_callback_url', $dana['callback_url']) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="https://citycourier.pabm.space/api/courier/dana/callback">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Public Key (RSA) <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                    <textarea name="dana_public_key" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PUBLIC KEY-----">{{ old('dana_public_key', $dana['public_key']) }}</textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Private Key (RSA PKCS#8)</label>
                                    <textarea name="dana_private_key" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PRIVATE KEY-----">{{ old('dana_private_key', $dana['private_key']) }}</textarea>
                                    <p class="text-xs text-slate-400 mt-1">Digunakan untuk menandatangani permintaan ke DANA.</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div id="dana-test-area">
                                    <button type="button" id="btn-test-dana" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                        <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                    </button>
                                    <div id="dana-test-result" class="mt-3 hidden">
                                        <div id="dana-test-alert" class="p-3 rounded-xl">
                                            <p class="font-semibold text-xs" id="dana-test-title"></p>
                                            <p class="text-xs mt-1" id="dana-test-message"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Alur Widget Binding:</p>
                                    <ol class="space-y-1 list-decimal list-inside">
                                        <li>Kurir memanggil <code class="bg-slate-100 px-1 rounded">POST /api/courier/dana/connect</code></li>
                                        <li>Backend buat Deeplink Binding URL</li>
                                        <li>Kurir buka URL &rarr; DANA redirect ke callback</li>
                                        <li>Backend tukar <code class="bg-slate-100 px-1 rounded">auth_code</code> jadi token</li>
                                    </ol>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Mode:</p>
                                    <p><strong>Mock</strong> &mdash; Simulasi (development)</p>
                                    <p><strong>Sandbox</strong> &mdash; Testing DANA sandbox</p>
                                    <p><strong>Production</strong> &mdash; Live</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4 pt-4 border-t border-slate-100">
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span> Simpan DANA
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
document.addEventListener('DOMContentLoaded', function() {
    // ─── Accordion Toggle ────────────────────────────────────
    window.toggleProvider = function(name) {
        const body = document.getElementById('body-' + name);
        const chevron = document.getElementById('chevron-' + name);
        const isHidden = body.classList.contains('hidden');

        // Close all
        document.querySelectorAll('.provider-body').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.provider-chevron').forEach(el => el.style.transform = 'rotate(0deg)');

        if (isHidden) {
            body.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    // ─── RajaOngkir: toggle fields ───────────────────────────
    const rkProvider = document.getElementById('rajaongkir_provider');
    const rkAccGroup = document.getElementById('rajaongkir_account_type_group');
    const rkSandboxGroup = document.getElementById('rajaongkir_sandbox_group');
    if (rkProvider) {
        function rkToggle() {
            if (rkProvider.value === 'komerce') {
                rkAccGroup.style.display = 'none';
                rkSandboxGroup.style.display = 'flex';
            } else {
                rkAccGroup.style.display = 'block';
                rkSandboxGroup.style.display = 'none';
            }
        }
        rkProvider.addEventListener('change', rkToggle);
        rkToggle();
    }

    // ─── Map: provider defaults ──────────────────────────────
    const mapProvider = document.getElementById('map_provider');
    const mapBaseUrl = document.getElementById('map_base_url');
    const mapApiKey = document.getElementById('map_api_key');
    const mapKeyHint = document.getElementById('api-key-hint');
    const MAP_DEFAULTS = {
        osrm: 'https://router.project-osrm.org',
        mapbox: 'https://api.mapbox.com',
        maplibre: 'https://demotiles.maplibre.org',
        google: 'https://maps.googleapis.com',
    };
    if (mapProvider) {
        mapProvider.addEventListener('change', function() {
            const preset = MAP_DEFAULTS[this.value];
            if (preset) mapBaseUrl.value = preset;
            const isOsrm = this.value === 'osrm';
            mapApiKey.placeholder = isOsrm ? 'Kosongkan untuk OSRM publik' : 'Masukkan Access Token';
            mapKeyHint.innerText = isOsrm ? 'OSRM publik tidak memerlukan token.' : 'Token akan diproyeksi melalui Laravel.';
        });
    }

    // ─── Generic Test Button Helper ──────────────────────────
    function setupTest(btnId, url, resultId, alertId, titleId, msgId) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Mengecek...';
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            })
            .then(r => r.json())
            .then(data => {
                document.getElementById(resultId).classList.remove('hidden');
                const alert = document.getElementById(alertId);
                alert.className = 'p-3 rounded-xl ' + (data.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200');
                document.getElementById(titleId).innerText = data.success ? '✓ Berhasil' : '✗ Gagal';
                document.getElementById(titleId).className = 'font-semibold text-xs ' + (data.success ? 'text-green-700' : 'text-red-700');
                document.getElementById(msgId).innerText = data.message;
            })
            .catch(() => {
                document.getElementById(resultId).classList.remove('hidden');
                document.getElementById(alertId).className = 'p-3 rounded-xl bg-red-50 border border-red-200';
                document.getElementById(titleId).innerText = 'Error';
                document.getElementById(msgId).innerText = 'Terjadi kesalahan jaringan.';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi';
            });
        });
    }

    setupTest('btn-test-rajaongkir', '{{ route("admin.settings.rajaongkir.test") }}', 'rajaongkir-test-result', 'rajaongkir-test-alert', 'rajaongkir-test-title', 'rajaongkir-test-message');
    setupTest('btn-test-payment', '{{ route("admin.settings.payment.test") }}', 'payment-test-result', 'payment-test-alert', 'payment-test-title', 'payment-test-message');
    setupTest('btn-test-map', '{{ route("admin.settings.map.test") }}', 'map-test-result', 'map-test-alert', 'map-test-title', 'map-test-message');
    setupTest('btn-test-dana', '{{ route("admin.settings.dana.test") }}', 'dana-test-result', 'dana-test-alert', 'dana-test-title', 'dana-test-message');
});
</script>
@endpush
@endsection
