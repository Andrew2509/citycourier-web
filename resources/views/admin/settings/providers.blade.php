@extends('layouts.admin')

@section('title', 'Provider & Integrasi')

@section('content')
<div class="flex flex-col gap-6">
    {{-- ═══ Header ═══ --}}
    <div>
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-2xl">extension</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Provider & Integrasi</h1>
                <p class="text-sm text-slate-500 mt-0.5">Konfigurasi koneksi eksternal untuk WhatsApp, Pengiriman, Pembayaran, Peta, dan DANA</p>
            </div>
        </div>
    </div>

    {{-- ═══ Accordion Sections ═══ --}}
    <div class="flex flex-col gap-4">

        {{-- ───────────────── 1. WhatsApp (Fonnte) ─────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="whatsapp">
            <button type="button" onclick="toggleProvider('whatsapp')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-green-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-green-600 text-xl">chat</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">WhatsApp (Fonnte)</h3>
                    <p class="text-xs text-slate-400 mt-0.5">OTP &amp; notifikasi via WhatsApp</p>
                </div>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full {{ !empty($whatsapp['token']) ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                    <span class="text-xs font-medium {{ !empty($whatsapp['token']) ? 'text-green-600' : 'text-slate-400' }}">{{ !empty($whatsapp['token']) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                </span>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-whatsapp">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-whatsapp">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label for="fonnte_token" class="block text-sm font-semibold text-slate-700 mb-1.5">Fonnte Token</label>
                                    <div class="relative">
                                        <input type="password" name="fonnte_token" id="fonnte_token" value="{{ old('fonnte_token', $whatsapp['token']) }}" autocomplete="off"
                                               class="w-full px-4 py-2.5 pr-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('fonnte_token') border-red-500 @enderror"
                                               placeholder="Masukkan token dari dashboard Fonnte" required>
                                        <button type="button" data-pw-toggle="fonnte_token" title="Tampilkan/sembunyikan token"
                                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </div>
                                    @error('fonnte_token')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="fonnte_send_number" class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor Pengirim <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                    <input type="text" name="fonnte_send_number" id="fonnte_send_number" value="{{ old('fonnte_send_number', $whatsapp['send_number']) }}"
                                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('fonnte_send_number') border-red-500 @enderror"
                                           placeholder="Contoh: 08123456789">
                                    @error('fonnte_send_number')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="whatsapp_provider" class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                                    <select name="whatsapp_provider" id="whatsapp_provider"
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="fonnte" {{ ($whatsapp['provider'] ?? 'fonnte') == 'fonnte' ? 'selected' : '' }}>Fonnte (Recommended)</option>
                                        <option value="orbitwa" {{ ($whatsapp['provider'] ?? 'fonnte') == 'orbitwa' ? 'selected' : '' }}>OrbitWA (Legacy)</option>
                                        <option value="mock" {{ ($whatsapp['provider'] ?? 'fonnte') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                        <option value="auto" {{ ($whatsapp['provider'] ?? 'fonnte') == 'auto' ? 'selected' : '' }}>Auto (Otomatis)</option>
                                    </select>
                                    @error('whatsapp_provider')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-primary/5 rounded-xl border border-dashed border-primary/30 p-4">
                                    <h4 class="text-xs font-bold text-primary mb-2 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">wifi_tethering</span> Test Koneksi
                                    </h4>
                                    <input type="text" id="wa_test_phone"
                                           class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-slate-800 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-primary/20"
                                           placeholder="Nomor HP (0812...)">
                                    <button type="button" id="btn-test-whatsapp" data-test-url="{{ route('admin.settings.whatsapp.test') }}" data-test-body="whatsapp"
                                            class="w-full px-3 py-2 rounded-lg text-xs font-semibold bg-white text-primary border border-primary/20 hover:bg-primary hover:text-white transition-all flex items-center justify-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">send</span> Kirim Test
                                    </button>
                                    <div id="whatsapp-test-result" class="mt-3 hidden">
                                        <div id="whatsapp-test-alert" class="p-3 rounded-xl">
                                            <p class="font-semibold text-xs" id="whatsapp-test-title"></p>
                                            <p class="text-xs mt-1" id="whatsapp-test-message"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500">
                                    <p>Digunakan untuk mengirim OTP via WhatsApp saat login/registrasi.</p>
                                    <p class="mt-1">Daftar: <a href="https://fonnte.com" target="_blank" rel="noopener" class="text-primary hover:underline">fonnte.com</a></p>
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

        {{-- ─────────────── 2. RajaOngkir / Komerce ─────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="rajaongkir">
            <button type="button" onclick="toggleProvider('rajaongkir')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-xl">local_shipping</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">RajaOngkir / Komerce</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Cek ongkir, provinsi, kota &amp; kecamatan</p>
                </div>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full {{ !empty($rajaongkir['api_key']) ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                    <span class="text-xs font-medium {{ !empty($rajaongkir['api_key']) ? 'text-green-600' : 'text-slate-400' }}">{{ !empty($rajaongkir['api_key']) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                </span>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-rajaongkir">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-rajaongkir">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label for="rajaongkir_api_key" class="block text-sm font-semibold text-slate-700 mb-1.5">RajaOngkir API Key</label>
                                    <div class="relative">
                                        <input type="password" name="rajaongkir_api_key" id="rajaongkir_api_key" value="{{ old('rajaongkir_api_key', $rajaongkir['api_key']) }}" autocomplete="off"
                                               class="w-full px-4 py-2.5 pr-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('rajaongkir_api_key') border-red-500 @enderror"
                                               placeholder="Masukkan API Key dari dashboard RajaOngkir" required>
                                        <button type="button" data-pw-toggle="rajaongkir_api_key" title="Tampilkan/sembunyikan API key"
                                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </div>
                                    @error('rajaongkir_api_key')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div id="rajaongkir-account-type-group">
                                        <label for="rajaongkir_account_type" class="block text-sm font-semibold text-slate-700 mb-1.5">Tipe Akun</label>
                                        <select name="rajaongkir_account_type" id="rajaongkir_account_type"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="starter" {{ ($rajaongkir['account_type'] ?? 'starter') == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                                            <option value="basic" {{ ($rajaongkir['account_type'] ?? 'starter') == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                                            <option value="pro" {{ ($rajaongkir['account_type'] ?? 'starter') == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                                        </select>
                                        @error('rajaongkir_account_type')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="rajaongkir_provider" class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                                        <select name="rajaongkir_provider" id="rajaongkir_provider"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="rajaongkir" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                                            <option value="komerce" {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                                        </select>
                                        @error('rajaongkir_provider')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <label id="rajaongkir-sandbox-group" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer {{ ($rajaongkir['provider'] ?? 'rajaongkir') == 'komerce' ? '' : 'hidden' }}">
                                    <input type="checkbox" name="rajaongkir_sandbox" value="1" id="rajaongkir_sandbox" {{ $rajaongkir_sandbox ? 'checked' : '' }} class="w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-700">Sandbox Mode</p>
                                        <p class="text-xs text-slate-400">Aktifkan untuk lingkungan testing Komerce</p>
                                    </div>
                                </label>
                            </div>
                            <div class="space-y-4">
                                <button type="button" id="btn-test-rajaongkir" data-test-url="{{ route('admin.settings.rajaongkir.test') }}"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                </button>
                                <div id="rajaongkir-test-result" class="hidden">
                                    <div id="rajaongkir-test-alert" class="p-3 rounded-xl">
                                        <p class="font-semibold text-xs" id="rajaongkir-test-title"></p>
                                        <p class="text-xs mt-1" id="rajaongkir-test-message"></p>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Tipe Akun:</p>
                                    <p><strong>Starter</strong> &mdash; JNE, POS, TIKI</p>
                                    <p><strong>Pro</strong> &mdash; sampai tingkat kecamatan</p>
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

        {{-- ─────────────── 3. Payment (Komerce) ─────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="payment">
            <button type="button" onclick="toggleProvider('payment')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-purple-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-purple-600 text-xl">payment</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Payment (Komerce)</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Virtual Account &amp; QRIS</p>
                </div>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full {{ !empty($payment['api_key']) ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                    <span class="text-xs font-medium {{ !empty($payment['api_key']) ? 'text-green-600' : 'text-slate-400' }}">{{ !empty($payment['api_key']) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                </span>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-payment">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-payment">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.payment.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label for="komerce_payment_api_key" class="block text-sm font-semibold text-slate-700 mb-1.5">API Key</label>
                                    <div class="relative">
                                        <input type="password" name="komerce_payment_api_key" id="komerce_payment_api_key" value="{{ old('komerce_payment_api_key', $payment['api_key']) }}" autocomplete="off"
                                               class="w-full px-4 py-2.5 pr-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('komerce_payment_api_key') border-red-500 @enderror"
                                               placeholder="Masukkan API Key dari Komerce" required>
                                        <button type="button" data-pw-toggle="komerce_payment_api_key" title="Tampilkan/sembunyikan API key"
                                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </div>
                                    @error('komerce_payment_api_key')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    <p class="text-xs text-slate-400 mt-1">Dari <a href="https://collaborator.komerce.id" target="_blank" rel="noopener" class="text-primary hover:underline">collaborator.komerce.id</a> &rarr; Integration</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="komerce_payment_env" class="block text-sm font-semibold text-slate-700 mb-1.5">Environment</label>
                                        <select name="komerce_payment_env" id="komerce_payment_env"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="sandbox" {{ ($payment['env'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                            <option value="production" {{ ($payment['env'] ?? 'sandbox') === 'production' ? 'selected' : '' }}>Production (Live)</option>
                                        </select>
                                        @error('komerce_payment_env')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="komerce_payment_callback_key" class="block text-sm font-semibold text-slate-700 mb-1.5">Callback Key <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                        <input type="text" name="komerce_payment_callback_key" id="komerce_payment_callback_key" value="{{ old('komerce_payment_callback_key', $payment['callback_key']) }}"
                                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                                               placeholder="Key untuk verifikasi webhook">
                                        <p class="text-xs text-slate-400 mt-1">Webhook: <code class="bg-slate-100 px-1 py-0.5 rounded">{{ config('app.url') }}/api/payment/callback</code></p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <button type="button" id="btn-test-payment" data-test-url="{{ route('admin.settings.payment.test') }}"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
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

        {{-- ─────────────── 4. Map Provider ─────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="map">
            <button type="button" onclick="toggleProvider('map')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-amber-600 text-xl">map</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">Map Provider</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Routing &amp; autocomplete untuk kurir</p>
                </div>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full {{ !empty($map['base_url']) ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                    <span class="text-xs font-medium {{ !empty($map['base_url']) ? 'text-green-600' : 'text-slate-400' }}">{{ !empty($map['base_url']) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                </span>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-map">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-map">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.map.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div>
                                    <label for="map_provider" class="block text-sm font-semibold text-slate-700 mb-1.5">Provider</label>
                                    <select name="map_provider" id="map_provider"
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="osrm" {{ ($map['provider'] ?? 'osrm') == 'osrm' ? 'selected' : '' }}>OSRM / OpenStreetMap (Recommended)</option>
                                        <option value="maplibre" {{ ($map['provider'] ?? 'osrm') == 'maplibre' ? 'selected' : '' }}>MapLibre GL (Open Source)</option>
                                        <option value="mapbox" {{ ($map['provider'] ?? 'osrm') == 'mapbox' ? 'selected' : '' }}>Mapbox</option>
                                        <option value="google" {{ ($map['provider'] ?? 'osrm') == 'google' ? 'selected' : '' }}>Google Maps API</option>
                                    </select>
                                    @error('map_provider')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="map_base_url" class="block text-sm font-semibold text-slate-700 mb-1.5">Base URL</label>
                                    <input type="url" name="map_base_url" id="map_base_url" value="{{ old('map_base_url', $map['base_url']) }}"
                                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('map_base_url') border-red-500 @enderror"
                                           placeholder="https://router.project-osrm.org" required>
                                    @error('map_base_url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="map_api_key" class="block text-sm font-semibold text-slate-700 mb-1.5">API Key</label>
                                    <div class="relative">
                                        <input type="password" name="map_api_key" id="map_api_key" value="{{ old('map_api_key', $map['api_key']) }}" autocomplete="off"
                                               class="w-full px-4 py-2.5 pr-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('map_api_key') border-red-500 @enderror"
                                               placeholder="Kosongkan untuk OSRM publik">
                                        <button type="button" data-pw-toggle="map_api_key" title="Tampilkan/sembunyikan API key"
                                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </div>
                                    @error('map_api_key')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    <p class="text-xs text-slate-400 mt-1" id="map-api-key-hint">Semua request dari Flutter akan diproyeksikan melalui Laravel.</p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <button type="button" id="btn-test-map" data-test-url="{{ route('admin.settings.map.test') }}"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
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
                                    <div class="flex items-center gap-1.5"><span class="px-1 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">POST</span><code>/api/shipping/map/routing</code></div>
                                    <div class="flex items-center gap-1.5"><span class="px-1 bg-green-100 text-green-700 rounded text-[10px] font-bold">GET</span><code>/api/shipping/map/autocomplete</code></div>
                                    <div class="flex items-center gap-1.5"><span class="px-1 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">POST</span><code>/api/shipping/map/matrix</code></div>
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

        {{-- ─────────────── 5. DANA ─────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden provider-card" data-provider="dana">
            <button type="button" onclick="toggleProvider('dana')" class="w-full flex items-center gap-4 p-5 text-left hover:bg-slate-50/50 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-blue-600/10 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-xl">account_balance_wallet</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-800">DANA</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Official DANA &mdash; widget binding untuk top up kurir</p>
                </div>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="w-2.5 h-2.5 rounded-full {{ !empty($dana['client_id']) ? 'bg-green-500' : 'bg-slate-300' }}"></span>
                    <span class="text-xs font-medium {{ !empty($dana['client_id']) ? 'text-green-600' : 'text-slate-400' }}">{{ !empty($dana['client_id']) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' }}</span>
                </span>
                <span class="material-symbols-outlined text-slate-400 transition-transform duration-200 provider-chevron" id="chevron-dana">expand_more</span>
            </button>
            <div class="provider-body hidden" id="body-dana">
                <div class="px-5 pb-5 border-t border-slate-100 pt-5">
                    <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="md:col-span-2 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="dana_mode" class="block text-sm font-semibold text-slate-700 mb-1.5">Mode</label>
                                        <select name="dana_mode" id="dana_mode"
                                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="mock" {{ ($dana['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                            <option value="sandbox" {{ ($dana['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                            <option value="production" {{ ($dana['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                                        </select>
                                        @error('dana_mode')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="dana_merchant_id" class="block text-sm font-semibold text-slate-700 mb-1.5">Merchant ID</label>
                                        <input type="text" name="dana_merchant_id" id="dana_merchant_id" value="{{ old('dana_merchant_id', $dana['merchant_id']) }}"
                                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('dana_merchant_id') border-red-500 @enderror">
                                        @error('dana_merchant_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="dana_api_base_url" class="block text-sm font-semibold text-slate-700 mb-1.5">API Base URL</label>
                                    <input type="url" name="dana_api_base_url" id="dana_api_base_url" value="{{ old('dana_api_base_url', $dana['api_base_url']) }}"
                                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('dana_api_base_url') border-red-500 @enderror"
                                           placeholder="https://api.sandbox.dana.id">
                                    @error('dana_api_base_url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="dana_client_id" class="block text-sm font-semibold text-slate-700 mb-1.5">Client ID</label>
                                        <input type="text" name="dana_client_id" id="dana_client_id" value="{{ old('dana_client_id', $dana['client_id']) }}"
                                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('dana_client_id') border-red-500 @enderror">
                                        @error('dana_client_id')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="dana_client_secret" class="block text-sm font-semibold text-slate-700 mb-1.5">Client Secret</label>
                                        <div class="relative">
                                            <input type="password" name="dana_client_secret" id="dana_client_secret" value="{{ old('dana_client_secret', $dana['client_secret']) }}" autocomplete="off"
                                                   class="w-full px-4 py-2.5 pr-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('dana_client_secret') border-red-500 @enderror">
                                            <button type="button" data-pw-toggle="dana_client_secret" title="Tampilkan/sembunyikan client secret"
                                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-primary transition-colors">
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </button>
                                        </div>
                                        @error('dana_client_secret')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="dana_callback_url" class="block text-sm font-semibold text-slate-700 mb-1.5">Callback URL</label>
                                    <input type="url" name="dana_callback_url" id="dana_callback_url" value="{{ old('dana_callback_url', $dana['callback_url']) }}"
                                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('dana_callback_url') border-red-500 @enderror"
                                           placeholder="https://citycourier.pabm.space/api/courier/dana/callback">
                                    @error('dana_callback_url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="dana_public_key" class="block text-sm font-semibold text-slate-700 mb-1.5">Public Key <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                                        <textarea name="dana_public_key" id="dana_public_key" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PUBLIC KEY-----">{{ old('dana_public_key', $dana['public_key']) }}</textarea>
                                    </div>
                                    <div>
                                        <label for="dana_private_key" class="block text-sm font-semibold text-slate-700 mb-1.5">Private Key</label>
                                        <textarea name="dana_private_key" id="dana_private_key" rows="4" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PRIVATE KEY-----">{{ old('dana_private_key', $dana['private_key']) }}</textarea>
                                        <p class="text-xs text-slate-400 mt-1">Digunakan untuk menandatangani permintaan ke DANA.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <button type="button" id="btn-test-dana" data-test-url="{{ route('admin.settings.dana.test') }}"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi
                                </button>
                                <div id="dana-test-result" class="hidden">
                                    <div id="dana-test-alert" class="p-3 rounded-xl">
                                        <p class="font-semibold text-xs" id="dana-test-title"></p>
                                        <p class="text-xs mt-1" id="dana-test-message"></p>
                                    </div>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs text-slate-500 space-y-1">
                                    <p class="font-semibold text-slate-600">Mode:</p>
                                    <p><strong>Mock</strong> &mdash; simulasi (development)</p>
                                    <p><strong>Sandbox</strong> &mdash; testing DANA sandbox</p>
                                    <p><strong>Production</strong> &mdash; live</p>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ─── Accordion Toggle ────────────────────────────────────
    window.toggleProvider = function(name) {
        const body = document.getElementById('body-' + name);
        const chevron = document.getElementById('chevron-' + name);
        const isHidden = body.classList.contains('hidden');

        document.querySelectorAll('.provider-body').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.provider-chevron').forEach(el => el.style.transform = 'rotate(0deg)');

        if (isHidden) {
            body.classList.remove('hidden');
            chevron.style.transform = 'rotate(180deg)';
        }
    };

    // ─── Show/Hide Password Toggle ──────────────────────────
    document.querySelectorAll('[data-pw-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const input = document.getElementById(btn.dataset.pwToggle);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = isPassword ? 'visibility_off' : 'visibility';
            input.focus();
        });
    });

    // ─── RajaOngkir: tampilkan Sandbox & sembunyikan Tipe Akun untuk Komerce ──
    const rkProvider = document.getElementById('rajaongkir_provider');
    const rkAccGroup = document.getElementById('rajaongkir-account-type-group');
    const rkSandboxGroup = document.getElementById('rajaongkir-sandbox-group');
    if (rkProvider) {
        function rkToggle() {
            const isKomerce = rkProvider.value === 'komerce';
            if (rkAccGroup) rkAccGroup.style.display = isKomerce ? 'none' : 'block';
            if (rkSandboxGroup) rkSandboxGroup.classList.toggle('hidden', !isKomerce);
        }
        rkProvider.addEventListener('change', rkToggle);
        rkToggle();
    }

    // ─── Map: preset base URL per provider ──────────────────
    const mapProvider = document.getElementById('map_provider');
    const mapBaseUrl = document.getElementById('map_base_url');
    const mapApiKey = document.getElementById('map_api_key');
    const mapKeyHint = document.getElementById('map-api-key-hint');
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
            if (mapKeyHint) mapKeyHint.innerText = isOsrm ? 'OSRM publik tidak memerlukan token.' : 'Token akan diproyeksikan melalui Laravel.';
        });
    }

    // ─── Test Koneksi via Fetch (AJAX) ──────────────────────
    function buildWhatsappBody() {
        const phoneInput = document.getElementById('wa_test_phone');
        const phone = phoneInput ? phoneInput.value.trim() : '';
        if (!phone) {
            alert('Silakan masukkan nomor HP terlebih dahulu.');
            if (phoneInput) phoneInput.focus();
            return null;
        }
        const tokenInput = document.querySelector('input[name="fonnte_token"]');
        const sendNumberInput = document.querySelector('input[name="fonnte_send_number"]');
        return {
            phone: phone,
            token: tokenInput ? tokenInput.value.trim() : '',
            fonnte_send_number: sendNumberInput ? sendNumberInput.value.trim() : '',
        };
    }

    document.querySelectorAll('[data-test-url]').forEach(function(btn) {
        const prefix = btn.id.replace('btn-test-', '') + '-test-';
        const originalHtml = btn.innerHTML;

        btn.addEventListener('click', function() {
            const body = (btn.dataset.testBody === 'whatsapp') ? buildWhatsappBody() : {};
            if (body === null) return;

            const resultEl = document.getElementById(prefix + 'result');
            const alertEl = document.getElementById(prefix + 'alert');
            const titleEl = document.getElementById(prefix + 'title');
            const msgEl = document.getElementById(prefix + 'message');

            btn.disabled = true;
            btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Mengecek...';

            fetch(btn.dataset.testUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(body),
            })
            .then(function(r) {
                return r.json().catch(function() {
                    return { success: false, message: 'Respons dari server tidak valid.' };
                });
            })
            .then(function(data) {
                const ok = data.success === true;
                resultEl.classList.remove('hidden');
                alertEl.className = 'p-3 rounded-xl ' + (ok ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200');
                titleEl.textContent = ok ? 'Berhasil' : 'Gagal';
                titleEl.className = 'font-semibold text-xs ' + (ok ? 'text-green-700' : 'text-red-700');
                msgEl.textContent = data.message || 'Tidak ada pesan.';
            })
            .catch(function() {
                resultEl.classList.remove('hidden');
                alertEl.className = 'p-3 rounded-xl bg-red-50 border border-red-200';
                titleEl.textContent = 'Error';
                titleEl.className = 'font-semibold text-xs text-red-700';
                msgEl.textContent = 'Terjadi kesalahan jaringan.';
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    });

    // ─── Auto-open accordion sesuai state ───────────────────
    const hash = window.location.hash ? window.location.hash.replace('#', '') : null;
    @if(session('provider'))
        toggleProvider('{{ session("provider") }}');
    @elseif($errors->has('fonnte_token') || $errors->has('fonnte_send_number') || $errors->has('whatsapp_provider'))
        toggleProvider('whatsapp');
    @elseif($errors->has('rajaongkir_api_key') || $errors->has('rajaongkir_account_type') || $errors->has('rajaongkir_provider'))
        toggleProvider('rajaongkir');
    @elseif($errors->has('komerce_payment_api_key') || $errors->has('komerce_payment_env'))
        toggleProvider('payment');
    @elseif($errors->has('map_provider') || $errors->has('map_base_url') || $errors->has('map_api_key'))
        toggleProvider('map');
    @elseif($errors->has('dana_mode') || $errors->has('dana_api_base_url') || $errors->has('dana_client_id') || $errors->has('dana_merchant_id') || $errors->has('dana_callback_url'))
        toggleProvider('dana');
    @elseif(session('success'))
        @php
            $msg = strtolower(session('success'));
            $target = str_contains($msg, 'whatsapp') ? 'whatsapp'
                : (str_contains($msg, 'rajaongkir') ? 'rajaongkir'
                : (str_contains($msg, 'pembayaran') || str_contains($msg, 'payment') ? 'payment'
                : (str_contains($msg, 'peta') || str_contains($msg, 'map') ? 'map'
                : (str_contains($msg, 'dana') ? 'dana' : null))));
        @endphp
        @if(!empty($target))
            toggleProvider('{{ $target }}');
        @elseif(hash && document.getElementById('body-' + hash))
            toggleProvider(hash);
        @else
            toggleProvider('whatsapp');
        @endif
    @elseif(hash && document.getElementById('body-' + hash))
        toggleProvider(hash);
    @else
        toggleProvider('whatsapp');
    @endif
});
</script>
@endpush