@extends('layouts.admin')

@section('title', 'Provider & Integrasi')

@section('content')
<!-- Header & Breadcrumbs Section -->
<div class="flex flex-col gap-space-sm mb-space-xl">
    <div class="flex items-center gap-space-xs font-label-md text-label-md text-secondary">
        <span>Sistem &amp; Keamanan</span>
        <span class="material-symbols-outlined text-[14px]">chevron_right</span>
        <span class="text-on-surface font-semibold">Provider &amp; Integrasi</span>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-md">
            <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
                <span class="material-symbols-outlined text-[28px]">hub</span>
            </div>
            <div class="flex flex-col">
                <div class="flex items-center gap-space-sm flex-wrap">
                    <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight">Provider &amp; Integrasi Eksternal</h1>
                    <span class="inline-flex items-center gap-1.5 px-space-sm py-space-2xs rounded-full bg-surface-container-highest text-on-surface font-label-sm text-label-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Semua Layanan Online
                    </span>
                </div>
                <p class="font-body-md text-body-md text-secondary mt-space-2xs">
                    Kelola semua kredensial API gateway, webhooks, dan provider pihak ketiga dalam satu sistem orkestrasi terpusat.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Provider Configurations Stack -->
<div class="flex flex-col gap-space-md">

    {{-- ═══ 1. PROVIDER WHATSAPP (Fonnte) ═══ --}}
    <div class="rounded-xl bg-surface-container-lowest shadow-sm transition-all overflow-hidden" id="card-whatsapp">
        <!-- Header Bar -->
        <div class="flex items-center justify-between p-space-lg cursor-pointer select-none hover:bg-surface-container-low/40 transition-colors" onclick="toggleCard('whatsapp')">
            <div class="flex items-center gap-space-md">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">chat</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Provider WhatsApp</span>
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full {{ !empty($whatsapp['token']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-label-sm text-label-sm font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full {{ !empty($whatsapp['token']) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ !empty($whatsapp['token']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Fonnte — OTP &amp; notifikasi pengiriman kurir via WhatsApp otomatis</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px] text-secondary transition-transform {{ session('provider') === 'whatsapp' ? 'rotate-180' : '' }}" id="chevron-whatsapp">expand_more</span>
        </div>
        <!-- Content Area -->
        <div class="{{ session('provider') !== 'whatsapp' && !$errors->has('fonnte_token') ? 'hidden' : '' }} px-space-lg pb-space-lg pt-space-xs" id="content-whatsapp">
            <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-xl">
                    <!-- Left Column: Form Settings -->
                    <div class="lg:col-span-7 flex flex-col gap-space-md">
                        <!-- API Token -->
                        <div class="flex flex-col gap-space-xs">
                            <label class="font-label-md text-label-md font-semibold text-on-surface flex items-center justify-between" for="fonnte_token">
                                <span>Fonnte API Token</span>
                                <span class="font-label-sm text-label-sm text-secondary font-normal">Wajib diisi</span>
                            </label>
                            <div class="relative flex items-center">
                                <input class="w-full h-10 px-space-md pr-12 rounded-lg bg-surface font-data-mono text-data-mono text-on-surface focus:bg-surface-container-lowest focus:outline-none shadow-sm @error('fonnte_token') ring-2 ring-error @enderror" id="fonnte_token" name="fonnte_token" type="password" value="{{ old('fonnte_token', $whatsapp['token']) }}" placeholder="Masukkan token API Fonnte" autocomplete="off"/>
                                <button class="absolute right-space-sm p-space-2xs text-secondary hover:text-on-surface" type="button" onclick="togglePasswordVisibility('fonnte_token', this)">
                                    <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                                </button>
                            </div>
                            @error('fonnte_token')<p class="font-label-sm text-label-sm text-error">{{ $message }}</p>@enderror
                            <p class="font-label-sm text-label-sm text-secondary">Token otentikasi REST API didapatkan dari dashboard akun Fonnte Anda.</p>
                        </div>
                        <!-- Sender Number -->
                        <div class="flex flex-col gap-space-xs">
                            <label class="font-label-md text-label-md font-semibold text-on-surface flex items-center justify-between" for="fonnte_send_number">
                                <span>Nomor Pengirim (Sender ID)</span>
                                <span class="font-label-sm text-label-sm text-secondary font-normal">Opsional</span>
                            </label>
                            <div class="relative flex items-center">
                                <span class="absolute left-space-md text-secondary material-symbols-outlined text-[18px]">call</span>
                                <input class="w-full h-10 pl-11 pr-space-md rounded-lg bg-surface font-data-mono text-data-mono text-on-surface focus:bg-surface-container-lowest focus:outline-none shadow-sm" id="fonnte_send_number" name="fonnte_send_number" type="text" value="{{ old('fonnte_send_number', $whatsapp['send_number']) }}" placeholder="Contoh: 08123456789"/>
                            </div>
                            <p class="font-label-sm text-label-sm text-secondary">Nomor WhatsApp perangkat gateway yang terhubung secara aktif.</p>
                        </div>
                        <!-- Provider Select -->
                        <div class="flex flex-col gap-space-xs">
                            <label class="font-label-md text-label-md font-semibold text-on-surface" for="whatsapp_provider">Provider Gateway</label>
                            <div class="relative flex items-center">
                                <select class="w-full h-10 px-space-md pr-10 rounded-lg bg-surface font-body-md text-body-md text-on-surface focus:bg-surface-container-lowest focus:outline-none shadow-sm appearance-none cursor-pointer" id="whatsapp_provider" name="whatsapp_provider">
                                    <option value="fonnte" {{ ($whatsapp['provider'] ?? 'fonnte') == 'fonnte' ? 'selected' : '' }}>Fonnte (Recommended)</option>
                                    <option value="orbitwa" {{ ($whatsapp['provider'] ?? 'fonnte') == 'orbitwa' ? 'selected' : '' }}>OrbitWA (Legacy)</option>
                                    <option value="mock" {{ ($whatsapp['provider'] ?? 'fonnte') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                    <option value="auto" {{ ($whatsapp['provider'] ?? 'fonnte') == 'auto' ? 'selected' : '' }}>Auto (Otomatis)</option>
                                </select>
                                <span class="absolute right-space-md pointer-events-none text-secondary material-symbols-outlined text-[20px]">unfold_more</span>
                            </div>
                            <p class="font-label-sm text-label-sm text-secondary">
                                Digunakan untuk mengirim kode OTP pendaftaran kurir, update resi, dan notifikasi setoran harian. Layanan: <a class="text-primary hover:underline font-medium" href="https://fonnte.com" target="_blank">fonnte.com</a>.
                            </p>
                        </div>
                    </div>
                    <!-- Right Column: Interactive Test Bench -->
                    <div class="lg:col-span-5 flex flex-col justify-between">
                        <div class="p-space-lg rounded-xl bg-surface-container-low flex flex-col gap-space-md">
                            <div class="flex items-center gap-space-xs text-primary font-semibold font-label-md text-label-md">
                                <span class="material-symbols-outlined text-[18px]">cell_tower</span>
                                <span>Test Koneksi WhatsApp Gateway</span>
                            </div>
                            <p class="font-body-sm text-body-sm text-secondary">
                                Verifikasi validitas token dan pastikan perangkat WhatsApp dalam status terhubung (online) sebelum digunakan untuk operasional.
                            </p>
                            <div class="flex flex-col gap-space-xs">
                                <label class="font-label-sm text-label-sm font-semibold text-on-surface" for="test-wa-input">Nomor HP Tujuan Tes</label>
                                <input class="w-full h-9 px-space-md rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" id="test-wa-input" placeholder="0812XXXXXXXX" type="text"/>
                            </div>
                            <button class="w-full h-9 rounded-lg bg-surface-container-lowest hover:bg-surface text-primary font-label-md text-label-md font-semibold inline-flex items-center justify-center gap-space-xs shadow-sm transition-all" id="btn-test-wa" type="button" onclick="kirimTestWa()">
                                <span class="material-symbols-outlined text-[16px]">send</span>
                                <span id="label-test-wa">Kirim Pesan Tes</span>
                            </button>
                            <div class="hidden p-space-sm rounded-lg font-label-sm text-label-sm flex items-center gap-2" id="test-wa-result">
                                <span class="material-symbols-outlined text-[16px]" id="test-wa-result-icon">check_circle</span>
                                <span id="test-wa-result-text"></span>
                            </div>
                        </div>
                        <!-- Save CTA for WhatsApp -->
                        <div class="flex items-center justify-end gap-space-sm mt-space-lg">
                            <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm inline-flex items-center gap-space-xs transition-all">
                                <span class="material-symbols-outlined text-[18px]">save</span>
                                <span>Simpan Konfigurasi WhatsApp</span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ 2. PROVIDER RAJAONGKIR ═══ --}}
    <div class="rounded-xl bg-surface-container-lowest shadow-sm transition-all overflow-hidden" id="card-rajaongkir">
        <div class="flex items-center justify-between p-space-lg cursor-pointer select-none hover:bg-surface-container-low/40 transition-colors" onclick="toggleCard('rajaongkir')">
            <div class="flex items-center gap-space-md">
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">local_shipping</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Provider RajaOngkir</span>
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full {{ !empty($rajaongkir['api_key']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-label-sm text-label-sm font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full {{ !empty($rajaongkir['api_key']) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ !empty($rajaongkir['api_key']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Cek ongkos kirim antarkota, kalkulasi tarif kecamatan, dan fallback rate se-Indonesia</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px] text-secondary transition-transform" id="chevron-rajaongkir">expand_more</span>
        </div>
        <div class="hidden px-space-lg pb-space-lg pt-space-xs" id="content-rajaongkir">
            <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-lg p-space-md rounded-xl bg-surface-container-low mb-space-md">
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="rajaongkir_api_key">RajaOngkir API Key ({{ ucfirst($rajaongkir['account_type'] ?? 'starter') }} Account)</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="password" name="rajaongkir_api_key" id="rajaongkir_api_key" value="{{ old('rajaongkir_api_key', $rajaongkir['api_key']) }}" autocomplete="off"/>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="rajaongkir_account_type">Tipe Akun</label>
                        <select class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-body-md text-body-md text-on-surface focus:outline-none shadow-sm" name="rajaongkir_account_type" id="rajaongkir_account_type">
                            <option value="starter" {{ ($rajaongkir['account_type'] ?? 'starter') == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                            <option value="basic" {{ ($rajaongkir['account_type'] ?? 'starter') == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                            <option value="pro" {{ ($rajaongkir['account_type'] ?? 'starter') == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="rajaongkir_provider">Provider</label>
                        <select class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-body-md text-body-md text-on-surface focus:outline-none shadow-sm" name="rajaongkir_provider" id="rajaongkir_provider">
                            <option value="rajaongkir" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                            <option value="komerce" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-space-sm">
                    <button class="px-space-md py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm inline-flex items-center gap-space-xs transition-all" type="submit">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Simpan RajaOngkir</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ 3. LAYANAN PEMBAYARAN (KOMERCE PAYMENT) ═══ --}}
    <div class="rounded-xl bg-surface-container-lowest shadow-sm transition-all overflow-hidden" id="card-payment">
        <div class="flex items-center justify-between p-space-lg cursor-pointer select-none hover:bg-surface-container-low/40 transition-colors" onclick="toggleCard('payment')">
            <div class="flex items-center gap-space-md">
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">credit_card</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Layanan Pembayaran (Payment Gateway)</span>
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full {{ !empty($payment['api_key']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-label-sm text-label-sm font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full {{ !empty($payment['api_key']) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ !empty($payment['api_key']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        <span class="px-space-xs py-space-2xs rounded-lg bg-secondary-container text-on-secondary-container font-label-sm text-label-sm font-bold">Komerce Pay</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Komerce Payment — Virtual Account (BCA, Mandiri, BNI, BRI) &amp; QRIS Dinamis instan</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px] text-secondary transition-transform" id="chevron-payment">expand_more</span>
        </div>
        <div class="hidden px-space-lg pb-space-lg pt-space-xs" id="content-payment">
            <form action="{{ route('admin.settings.payment.update') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md p-space-md rounded-xl bg-surface-container-low mb-space-md">
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="komerce_payment_api_key">API Key</label>
                        <div class="relative flex items-center">
                            <input class="w-full h-10 px-space-md pr-10 rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="password" name="komerce_payment_api_key" id="komerce_payment_api_key" value="{{ old('komerce_payment_api_key', $payment['api_key']) }}" autocomplete="off"/>
                            <button class="absolute right-space-sm p-space-2xs text-secondary" type="button" onclick="togglePasswordVisibility('komerce_payment_api_key', this)">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="komerce_payment_env">Environment</label>
                        <select class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-body-md text-body-md text-on-surface focus:outline-none shadow-sm" name="komerce_payment_env" id="komerce_payment_env">
                            <option value="sandbox" {{ ($payment['env'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="production" {{ ($payment['env'] ?? 'sandbox') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                        </select>
                    </div>
                    <div class="col-span-full flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="komerce_payment_callback_key">Callback Key</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="text" name="komerce_payment_callback_key" id="komerce_payment_callback_key" value="{{ old('komerce_payment_callback_key', $payment['callback_key']) }}" placeholder="Key untuk verifikasi webhook"/>
                        <p class="font-label-sm text-label-sm text-secondary">Webhook: <code class="bg-surface-container-high px-space-xs py-space-2xs rounded font-data-mono">{{ config('app.url') }}/api/payment/callback</code></p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-space-sm">
                    <button class="px-space-md py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm inline-flex items-center gap-space-xs transition-all" type="submit">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Simpan Kredensial Payment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ 4. KONFIGURASI PETA & ROUTING ═══ --}}
    <div class="rounded-xl bg-surface-container-lowest shadow-sm transition-all overflow-hidden" id="card-map">
        <div class="flex items-center justify-between p-space-lg cursor-pointer select-none hover:bg-surface-container-low/40 transition-colors" onclick="toggleCard('map')">
            <div class="flex items-center gap-space-md">
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">map</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Konfigurasi Peta &amp; Routing Engine</span>
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full {{ !empty($map['base_url']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-label-sm text-label-sm font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full {{ !empty($map['base_url']) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ !empty($map['base_url']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        <span class="px-space-xs py-space-2xs rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm font-bold">{{ strtoupper($map['provider'] ?? 'OSRM') }}</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">OSRM / Mapbox / Google Maps — Routing kalkulasi rute armada kurir, ETA, &amp; autocomplete</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px] text-secondary transition-transform" id="chevron-map">expand_more</span>
        </div>
        <div class="hidden px-space-lg pb-space-lg pt-space-xs" id="content-map">
            <form action="{{ route('admin.settings.map.update') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md p-space-md rounded-xl bg-surface-container-low mb-space-md">
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="map_provider">Primary Routing Engine</label>
                        <select class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-body-md text-body-md text-on-surface focus:outline-none shadow-sm" name="map_provider" id="map_provider">
                            <option value="osrm" {{ ($map['provider'] ?? 'osrm') == 'osrm' ? 'selected' : '' }}>OSRM (Open Source Routing Machine)</option>
                            <option value="mapbox" {{ ($map['provider'] ?? 'osrm') == 'mapbox' ? 'selected' : '' }}>Mapbox Navigation API v5</option>
                            <option value="google" {{ ($map['provider'] ?? 'osrm') == 'google' ? 'selected' : '' }}>Google Routes &amp; Distance Matrix</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="map_base_url">Routing Endpoint Base URL</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="url" name="map_base_url" id="map_base_url" value="{{ old('map_base_url', $map['base_url']) }}" placeholder="https://router.project-osrm.org"/>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="map_api_key">Map Token (Opsional)</label>
                        <div class="relative flex items-center">
                            <input class="w-full h-10 px-space-md pr-10 rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="password" name="map_api_key" id="map_api_key" value="{{ old('map_api_key', $map['api_key']) }}" placeholder="Kosongkan untuk OSRM publik"/>
                            <button class="absolute right-space-sm p-space-2xs text-secondary" type="button" onclick="togglePasswordVisibility('map_api_key', this)">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-space-sm">
                    <button class="px-space-md py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm inline-flex items-center gap-space-xs transition-all" type="submit">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Simpan Pengaturan Peta</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ 5. PROVIDER DANA (E-WALLET) ═══ --}}
    <div class="rounded-xl bg-surface-container-lowest shadow-sm transition-all overflow-hidden" id="card-dana">
        <div class="flex items-center justify-between p-space-lg cursor-pointer select-none hover:bg-surface-container-low/40 transition-colors" onclick="toggleCard('dana')">
            <div class="flex items-center gap-space-md">
                <div class="w-10 h-10 rounded-lg bg-sky-500/10 text-sky-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-space-sm">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Provider DANA</span>
                        <span class="inline-flex items-center gap-1 px-space-xs py-space-2xs rounded-full {{ !empty($dana['client_id']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }} font-label-sm text-label-sm font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full {{ !empty($dana['client_id']) ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ !empty($dana['client_id']) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        <span class="px-space-xs py-space-2xs rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm font-bold">Direct S4D</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-secondary">Widget Binding — Dompet digital kurir, auto-pencairan honorarium, &amp; top-up saldo kasbon</span>
                </div>
            </div>
            <span class="material-symbols-outlined text-[20px] text-secondary transition-transform" id="chevron-dana">expand_more</span>
        </div>
        <div class="hidden px-space-lg pb-space-lg pt-space-xs" id="content-dana">
            <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-space-md p-space-md rounded-xl bg-surface-container-low mb-space-md">
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="dana_client_id">DANA Client ID</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="text" name="dana_client_id" id="dana_client_id" value="{{ old('dana_client_id', $dana['client_id']) }}"/>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="dana_client_secret">DANA Secret Key</label>
                        <div class="relative flex items-center">
                            <input class="w-full h-10 px-space-md pr-10 rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="password" name="dana_client_secret" id="dana_client_secret" value="{{ old('dana_client_secret', $dana['client_secret']) }}" autocomplete="off"/>
                            <button class="absolute right-space-sm p-space-2xs text-secondary" type="button" onclick="togglePasswordVisibility('dana_client_secret', this)">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="dana_mode">Mode</label>
                        <select class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-body-md text-body-md text-on-surface focus:outline-none shadow-sm" name="dana_mode" id="dana_mode">
                            <option value="mock" {{ ($dana['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                            <option value="sandbox" {{ ($dana['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                            <option value="production" {{ ($dana['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="dana_merchant_id">Merchant ID</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface-container-lowest font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="text" name="dana_merchant_id" id="dana_merchant_id" value="{{ old('dana_merchant_id', $dana['merchant_id']) }}"/>
                    </div>
                    <div class="col-span-full flex flex-col gap-space-xs">
                        <label class="font-label-md text-label-md font-semibold text-on-surface" for="dana_callback_url">Disbursement Webhook Handler</label>
                        <input class="w-full h-10 px-space-md rounded-lg bg-surface font-data-mono text-data-mono text-on-surface focus:outline-none shadow-sm" type="url" name="dana_callback_url" id="dana_callback_url" value="{{ old('dana_callback_url', $dana['callback_url']) }}"/>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-space-sm">
                    <button class="px-space-md py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm inline-flex items-center gap-space-xs transition-all" type="submit">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Simpan Integrasi DANA</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Toast Notification Container -->
<div class="fixed bottom-6 right-6 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none z-50 flex items-center gap-space-sm px-space-md py-space-sm rounded-xl bg-inverse-surface text-inverse-on-surface shadow-xl" id="toast">
    <span class="material-symbols-outlined text-[20px] text-primary" id="toast-icon">check_circle</span>
    <span class="font-body-sm text-body-sm font-semibold" id="toast-message"></span>
</div>

@endsection

@push('scripts')
<script>
function toggleCard(cardKey) {
    const content = document.getElementById('content-' + cardKey);
    const chevron = document.getElementById('chevron-' + cardKey);
    if (!content || !chevron) return;
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        chevron.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        chevron.classList.remove('rotate-180');
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('.material-symbols-outlined');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = 'visibility';
    } else {
        input.type = 'password';
        icon.textContent = 'visibility_off';
    }
}

function showToast(message, isError) {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-message');
    const toastIcon = document.getElementById('toast-icon');
    toastMsg.textContent = message;
    if (isError) {
        toastIcon.textContent = 'error';
        toastIcon.classList.remove('text-primary');
        toastIcon.classList.add('text-rose-400');
    } else {
        toastIcon.textContent = 'check_circle';
        toastIcon.classList.add('text-primary');
        toastIcon.classList.remove('text-rose-400');
    }
    toast.classList.remove('translate-y-20', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
        toast.classList.remove('translate-y-0', 'opacity-100');
    }, 3200);
}

function kirimTestWa() {
    const input = document.getElementById('test-wa-input');
    const label = document.getElementById('label-test-wa');
    const result = document.getElementById('test-wa-result');
    const resultIcon = document.getElementById('test-wa-result-icon');
    const resultText = document.getElementById('test-wa-result-text');

    if (!input.value.trim()) {
        showToast('Masukkan nomor HP tujuan terlebih dahulu.', true);
        input.focus();
        return;
    }

    label.textContent = 'Mengirim...';
    result.classList.add('hidden');

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const tokenInput = document.querySelector('input[name="fonnte_token"]');
    const sendNumberInput = document.querySelector('input[name="fonnte_send_number"]');

    fetch('{{ route("admin.settings.whatsapp.test") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            phone: input.value.trim(),
            token: tokenInput ? tokenInput.value.trim() : '',
            fonnte_send_number: sendNumberInput ? sendNumberInput.value.trim() : '',
        }),
    })
    .then(r => r.json().catch(() => ({ success: false, message: 'Respons tidak valid.' })))
    .then(data => {
        label.textContent = 'Kirim Pesan Tes';
        result.classList.remove('hidden');
        if (data.success) {
            result.className = 'p-space-sm rounded-lg bg-emerald-50 text-emerald-800 font-label-sm text-label-sm flex items-center gap-2';
            resultIcon.textContent = 'check_circle';
            resultIcon.className = 'material-symbols-outlined text-[16px] text-emerald-600';
        } else {
            result.className = 'p-space-sm rounded-lg bg-red-50 text-red-800 font-label-sm text-label-sm flex items-center gap-2';
            resultIcon.textContent = 'error';
            resultIcon.className = 'material-symbols-outlined text-[16px] text-red-600';
        }
        resultText.textContent = data.message || 'Tidak ada pesan.';
        showToast(data.message, !data.success);
    })
    .catch(() => {
        label.textContent = 'Kirim Pesan Tes';
        result.classList.remove('hidden');
        result.className = 'p-space-sm rounded-lg bg-red-50 text-red-800 font-label-sm text-label-sm flex items-center gap-2';
        resultIcon.textContent = 'error';
        resultIcon.className = 'material-symbols-outlined text-[16px] text-red-600';
        resultText.textContent = 'Terjadi kesalahan jaringan.';
        showToast('Terjadi kesalahan jaringan.', true);
    });
}

// Auto-open accordion based on session
document.addEventListener('DOMContentLoaded', function() {
    @if(session('provider'))
        toggleCard('{{ session("provider") }}');
    @elseif(session('success'))
        toggleCard('whatsapp');
    @else
        toggleCard('whatsapp');
    @endif

    // Show toast for flash messages
    @if(session('success'))
        showToast('{{ session("success") }}');
    @endif
    @if(session('error'))
        showToast('{{ session("error") }}', true);
    @endif
});
</script>
@endpush
