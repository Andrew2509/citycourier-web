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
            <p class="page-subtitle">Pusat orkestrasi integrasi gateway Xendit (satu-satunya payment &amp; payout gateway aktif), WhatsApp Fonnte, Meta WhatsApp Business Cloud API (Notifikasi &amp; Webhook), peta geolokasi, TomTom Traffic real-time, dan tarif ongkir. API key tersimpan terenkripsi di database.</p>
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

        {{-- KARTU 6: TomTom Traffic API (Flow v4 & Incidents v5) --}}
        @php
            $tomtomSafe = is_array($tomtom ?? null) ? $tomtom : [];
            $tomtomUpdateUrl = \Illuminate\Support\Facades\Route::has('admin.settings.tomtom.update')
                ? route('admin.settings.tomtom.update')
                : url('/admin/settings/tomtom');
            $tomtomTestUrl = \Illuminate\Support\Facades\Route::has('admin.settings.tomtom.test')
                ? route('admin.settings.tomtom.test')
                : url('/admin/settings/tomtom/test');
        @endphp
        <div class="card lg:col-span-2 flex flex-col justify-between border-orange-200 {{ !($providerStates['tomtom'] ?? true) ? 'opacity-75 bg-slate-50/60' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-orange-50 text-orange-600">
                            <span class="cc-icon text-[20px]">traffic</span>
                        </div>
                        <div>
                            <div class="card-title">TomTom Traffic API (Live Kemacetan &amp; Insiden)</div>
                            <div class="card-subtitle">Overlay kemacetan real-time &amp; insiden jalan di atas OpenStreetMap aplikasi kurir</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        @include('admin.settings._provider_toggle', ['provider' => 'tomtom', 'enabled' => $providerStates['tomtom'] ?? true])
                        <div class="flex items-center gap-1">
                            @if(!empty($tomtomSafe['configured']))
                                <span class="badge badge-green">Terkonfigurasi</span>
                            @else
                                <span class="badge badge-amber">Belum Diisi</span>
                            @endif
                            <span class="badge badge-indigo uppercase">
                                {{ $tomtomSafe['source'] ?? 'none' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Masukkan TomTom API Key langsung di sini tanpa perlu mengedit file <code class="font-mono">.env</code>. API Key disimpan terenkripsi di database dan tidak pernah dikirim ke aplikasi Flutter.
                    </p>

                    <div class="rounded-md bg-slate-50 p-2.5 text-[11px] text-slate-600 grid grid-cols-1 sm:grid-cols-2 gap-1 font-mono border border-slate-200">
                        <div>Proxy Flow Segment: <span class="text-slate-900 select-all">{{ url('/api/shipping/map/traffic/flow') }}</span></div>
                        <div>Proxy Incidents: <span class="text-slate-900 select-all">{{ url('/api/shipping/map/traffic/incidents') }}</span></div>
                        <div>Proxy Raster Tile: <span class="text-slate-900 select-all">{{ url('/api/shipping/map/traffic/tiles/{z}/{x}/{y}.png') }}</span></div>
                        <div>Proxy Delivery Summary: <span class="text-slate-900 select-all">{{ url('/api/shipping/map/traffic/delivery-summary') }}</span></div>
                    </div>

                    <form method="POST" action="{{ $tomtomUpdateUrl }}" id="tomtomForm" class="space-y-3 pt-1">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-2">
                                <label class="form-label text-xs">API Base URL</label>
                                <input type="url"
                                       name="tomtom_base_url"
                                       value="{{ $tomtomSafe['base_url'] ?? 'https://api.tomtom.com' }}"
                                       placeholder="https://api.tomtom.com"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Cache TTL (detik)</label>
                                <input type="number"
                                       name="tomtom_cache_ttl_seconds"
                                       min="15"
                                       max="300"
                                       value="{{ $tomtomSafe['cache_ttl_seconds'] ?? 60 }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Timeout API (detik)</label>
                                <input type="number"
                                       name="tomtom_timeout_seconds"
                                       min="3"
                                       max="20"
                                       value="{{ $tomtomSafe['timeout_seconds'] ?? 10 }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">
                                TomTom API Key
                                @if(!empty($tomtomSafe['api_key_masked']))
                                    <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $tomtomSafe['api_key_masked'] }}</code>)</span>
                                @endif
                            </label>
                            <input type="password"
                                   name="tomtom_api_key"
                                   value=""
                                   placeholder="{{ !empty($tomtomSafe['api_key_masked']) ? 'Biarkan kosong untuk mempertahankan API Key tersimpan' : 'Masukkan TomTom Traffic API Key...' }}"
                                   autocomplete="new-password"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <span class="text-[11px] text-slate-500">
                                Status Peta Induk: <strong class="{{ ($providerStates['map'] ?? true) ? 'text-emerald-700' : 'text-red-600' }}">{{ ($providerStates['map'] ?? true) ? 'AKTIF' : 'NONAKTIF' }}</strong> (memerlukan Peta &amp; Geocoding ON)
                            </span>
                            <label class="text-xs flex items-center gap-1.5 cursor-pointer text-red-600">
                                <input type="checkbox" name="clear_tomtom_secret" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                <span>Hapus API Key Tersimpan</span>
                            </label>
                        </div>

                        <div id="tomtomTestResult" class="hidden rounded-md p-2.5 text-xs"></div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button"
                                    onclick="testPaymentGateway('tomtom', '{{ $tomtomTestUrl }}', 'tomtomForm', 'tomtomTestResult')"
                                    class="btn btn-sm btn-secondary">
                                Test Koneksi TomTom
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan TomTom
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- KARTU 7: Meta WhatsApp Business Cloud API (Notifikasi & Webhook Resmi) --}}
        @php
            $waCloudSafe = is_array($whatsappCloud ?? null) ? $whatsappCloud : [];
            $waCloudUpdateUrl = \Illuminate\Support\Facades\Route::has('admin.settings.whatsapp-cloud.update')
                ? route('admin.settings.whatsapp-cloud.update')
                : url('/admin/settings/whatsapp-cloud');
            $waCloudTestUrl = \Illuminate\Support\Facades\Route::has('admin.settings.whatsapp-cloud.test')
                ? route('admin.settings.whatsapp-cloud.test')
                : url('/admin/settings/whatsapp-cloud/test');
            $waCloudEnabled = (bool) ($providerStates['whatsapp_cloud'] ?? ($waCloudSafe['enabled'] ?? false));
        @endphp
        <div class="card lg:col-span-2 flex flex-col justify-between border-emerald-200 {{ !$waCloudEnabled ? 'opacity-90 bg-slate-50/40' : '' }}">
            <div>
                <div class="card-header">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <span class="cc-icon text-[20px]">forum</span>
                        </div>
                        <div>
                            <div class="card-title">Meta WhatsApp Business Cloud API (Resmi Notifikasi &amp; Webhook)</div>
                            <div class="card-subtitle">Integrasi notifikasi otomatis &amp; status webhook resmi melalui Meta Graph API</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1.5">
                        @include('admin.settings._provider_toggle', ['provider' => 'whatsapp_cloud', 'enabled' => $waCloudEnabled])
                        <div class="flex flex-wrap items-center justify-end gap-1">
                            @if(!empty($waCloudSafe['configured']))
                                <span class="badge badge-green">API Terkonfigurasi</span>
                            @else
                                <span class="badge badge-amber">Token Belum Diisi</span>
                            @endif
                            @if(!empty($waCloudSafe['webhook_ready']))
                                <span class="badge badge-green">Webhook Siap</span>
                            @else
                                <span class="badge badge-gray">Webhook Belum Lengkap</span>
                            @endif
                            <span class="badge badge-indigo uppercase">
                                {{ $waCloudSafe['source'] ?? 'none' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body space-y-3">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Masukkan kredensial Meta WhatsApp Business Cloud API langsung dari sini tanpa harus mengedit file <code class="font-mono">.env</code>. Access Token, App Secret, dan Verify Token dienkripsi otomatis di database dan tidak pernah dikirim ke aplikasi Flutter.
                    </p>

                    <div class="rounded-md bg-slate-50 p-2.5 text-[11px] text-slate-600 space-y-1 font-mono border border-slate-200">
                        <div>Callback Webhook URL (Meta Dashboard): <span class="text-slate-900 select-all">{{ $waCloudSafe['webhook_url'] ?? url('/api/v1/whatsapp/webhook') }}</span></div>
                        <div class="font-sans text-slate-500">Subscribe field <code class="font-mono">messages</code> pada Meta Developers &rarr; WhatsApp &rarr; Configuration untuk memantau status pesan otomatis dan opt-out webhook.</div>
                    </div>

                    <form method="POST" action="{{ $waCloudUpdateUrl }}" id="whatsappCloudForm" class="space-y-3 pt-1">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-2">
                                <label class="form-label text-xs">Graph API Base URL</label>
                                <input type="url"
                                       name="whatsapp_cloud_base_url"
                                       value="{{ $waCloudSafe['base_url'] ?? 'https://graph.facebook.com' }}"
                                       placeholder="https://graph.facebook.com"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">API Version</label>
                                <input type="text"
                                       name="whatsapp_cloud_api_version"
                                       value="{{ $waCloudSafe['api_version'] ?? 'v21.0' }}"
                                       placeholder="v21.0"
                                       class="form-input font-mono text-xs" required>
                            </div>
                            <div>
                                <label class="form-label text-xs">Window Layanan (jam)</label>
                                <input type="number"
                                       name="whatsapp_cloud_window_hours"
                                       min="1"
                                       max="72"
                                       value="{{ $waCloudSafe['window_hours'] ?? 24 }}"
                                       class="form-input font-mono text-xs" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="form-label text-xs">Phone Number ID</label>
                                <input type="text"
                                       name="whatsapp_cloud_phone_number_id"
                                       value="{{ $waCloudSafe['phone_number_id'] ?? '' }}"
                                       placeholder="Contoh: 109283746501928"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">WABA ID (Business Account ID)</label>
                                <input type="text"
                                       name="whatsapp_cloud_waba_id"
                                       value="{{ $waCloudSafe['waba_id'] ?? '' }}"
                                       placeholder="Contoh: 192837465091827"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">Utility Template Name</label>
                                <input type="text"
                                       name="whatsapp_cloud_utility_template_name"
                                       value="{{ $waCloudSafe['utility_template_name'] ?? 'city_courier_chat_notification' }}"
                                       placeholder="city_courier_chat_notification"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="form-label text-xs">Bahasa Template</label>
                                    <input type="text"
                                           name="whatsapp_cloud_utility_template_lang"
                                           value="{{ $waCloudSafe['utility_template_language'] ?? 'id' }}"
                                           placeholder="id"
                                           class="form-input font-mono text-xs">
                                </div>
                                <div>
                                    <label class="form-label text-xs">Timeout (dtk)</label>
                                    <input type="number"
                                           name="whatsapp_cloud_timeout_seconds"
                                           min="3"
                                           max="60"
                                           value="{{ $waCloudSafe['timeout_seconds'] ?? 15 }}"
                                           class="form-input font-mono text-xs">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label text-xs">
                                Permanent Access Token (System User Token Meta)
                                @if(!empty($waCloudSafe['access_token_masked']))
                                    <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $waCloudSafe['access_token_masked'] }}</code>)</span>
                                @endif
                            </label>
                            <input type="password"
                                   name="whatsapp_cloud_access_token"
                                   value=""
                                   placeholder="{{ !empty($waCloudSafe['access_token_masked']) ? 'Biarkan kosong untuk mempertahankan Access Token tersimpan' : 'EAAG... (Token permanen dari Meta Business Suite)' }}"
                                   autocomplete="new-password"
                                   class="form-input font-mono text-xs">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label text-xs">
                                    Meta App Secret (<code class="font-mono">X-Hub-Signature-256</code>)
                                    @if(!empty($waCloudSafe['app_secret_masked']))
                                        <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $waCloudSafe['app_secret_masked'] }}</code>)</span>
                                    @endif
                                </label>
                                <input type="password"
                                       name="whatsapp_cloud_app_secret"
                                       value=""
                                       placeholder="{{ !empty($waCloudSafe['app_secret_masked']) ? 'Biarkan kosong untuk mempertahankan App Secret tersimpan' : 'App Secret dari Meta App Dashboard...' }}"
                                       autocomplete="new-password"
                                       class="form-input font-mono text-xs">
                            </div>
                            <div>
                                <label class="form-label text-xs">
                                    Webhook Verify Token (<code class="font-mono">hub.verify_token</code>)
                                    @if(!empty($waCloudSafe['verify_token_masked']))
                                        <span class="text-[11px] font-normal text-emerald-700">(Tersimpan: <code class="font-mono">{{ $waCloudSafe['verify_token_masked'] }}</code>)</span>
                                    @endif
                                </label>
                                <input type="password"
                                       name="whatsapp_cloud_verify_token"
                                       value=""
                                       placeholder="{{ !empty($waCloudSafe['verify_token_masked']) ? 'Biarkan kosong untuk mempertahankan Verify Token tersimpan' : 'Token bebas untuk handshake verifikasi webhook...' }}"
                                       autocomplete="new-password"
                                       class="form-input font-mono text-xs">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <label class="text-xs flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="whatsapp_cloud_enabled" value="0">
                                <input type="checkbox" name="whatsapp_cloud_enabled" value="1"
                                       {{ $waCloudEnabled ? 'checked' : '' }}
                                       class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="text-slate-600">Aktifkan Jembatan WhatsApp Business Cloud API (Meta)</span>
                            </label>
                            <label class="text-xs flex items-center gap-1.5 cursor-pointer text-red-600">
                                <input type="checkbox" name="clear_whatsapp_cloud_secrets" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                <span>Hapus Token &amp; Secret Tersimpan</span>
                            </label>
                        </div>

                        <div id="whatsappCloudTestResult" class="hidden rounded-md p-2.5 text-xs"></div>

                        {{-- Panel Uji Kirim Pesan Langsung ke Nomor Tujuan --}}
                        <div class="rounded-md border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                            <div class="text-xs font-semibold text-slate-700">Uji Kirim Pesan Langsung ke WhatsApp Tujuan</div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <input type="text"
                                       name="whatsapp_cloud_test_phone"
                                       id="whatsappCloudTestPhone"
                                       placeholder="Nomor tujuan: 0812xxxx / 62812xxxx"
                                       class="form-input font-mono text-xs bg-white">
                                <select name="whatsapp_cloud_test_mode" class="form-select text-xs bg-white">
                                    <option value="hello_world">Template Bawaan (hello_world)</option>
                                    <option value="utility_template">Template Chat Order ({{ $waCloudSafe['utility_template_name'] ?? 'city_courier_chat_notification' }})</option>
                                    <option value="text">Teks Bebas (khusus window 24 jam)</option>
                                </select>
                                <button type="button"
                                        onclick="testWhatsappCloudSend('{{ $waCloudTestUrl }}')"
                                        class="btn btn-sm btn-secondary w-full">
                                    Kirim Tes Pesan WA
                                </button>
                            </div>
                            <div class="text-[11px] text-slate-500">
                                Syarat agar chat aplikasi masuk ke WA masing-masing user: (1) Penerima sudah mengaktifkan <strong>Terima pesan via WhatsApp</strong> di layar chat Flutter, (2) Dalam mode Test Number Meta, nomor HP tujuan wajib ditambahkan dulu di <em>Manage phone number list</em> Meta Developers, (3) Buat &amp; approve Utility Template <code class="font-mono">{{ $waCloudSafe['utility_template_name'] ?? 'city_courier_chat_notification' }}</code>.
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button"
                                    onclick="testWhatsappCloudConnection('{{ $waCloudTestUrl }}')"
                                    class="btn btn-sm btn-secondary">
                                Test Koneksi Meta API
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                Simpan WhatsApp Cloud API
                            </button>
                        </div>
                    </form>

                    {{-- Diagnostik & Log Pengiriman/Webhook Terbaru --}}
                    <div class="mt-3 rounded-md border border-slate-200 bg-white p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <span class="font-semibold text-slate-700">Diagnostik Jembatan WhatsApp</span>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-green">User Opt-In WA: {{ (int) ($waCloudSafe['opted_in_count'] ?? 0) }}</span>
                                <span class="badge badge-indigo">Window 24 Jam Aktif: {{ (int) ($waCloudSafe['window_open_count'] ?? 0) }}</span>
                            </div>
                        </div>
                        @if(!empty($waCloudSafe['recent_logs']))
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-[11px] text-slate-600">
                                    <thead class="border-b border-slate-200 text-slate-500">
                                        <tr>
                                            <th class="py-1 pr-2">Waktu</th>
                                            <th class="py-1 pr-2">Arah</th>
                                            <th class="py-1 pr-2">HTTP</th>
                                            <th class="py-1 pr-2">Status</th>
                                            <th class="py-1">Detail / Error Meta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-mono">
                                        @foreach($waCloudSafe['recent_logs'] as $waLog)
                                            <tr>
                                                <td class="py-1 pr-2 whitespace-nowrap">{{ $waLog['time'] ?? '-' }}</td>
                                                <td class="py-1 pr-2 uppercase">{{ $waLog['direction'] }}</td>
                                                <td class="py-1 pr-2">{{ $waLog['http_status'] ?? '-' }}</td>
                                                <td class="py-1 pr-2">
                                                    <span class="{{ ($waLog['processing_status'] ?? '') === 'failed' ? 'text-red-600 font-semibold' : 'text-emerald-700 font-semibold' }}">
                                                        {{ $waLog['processing_status'] }}
                                                    </span>
                                                </td>
                                                <td class="py-1 font-sans text-slate-700">{{ $waLog['error_message'] ?? 'OK' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-[11px] text-slate-500">
                                Belum ada log pengiriman/webhook WhatsApp Cloud API. Bila user baru mengirim chat namun belum muncul log di sini, pastikan user penerima sudah mengaktifkan switch <strong>Terima pesan via WhatsApp</strong> di aplikasi Flutter.
                            </div>
                        @endif
                    </div>
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

async function testWhatsappCloudConnection(endpoint) {
    const phoneInput = document.getElementById('whatsappCloudTestPhone');
    const prev = phoneInput ? phoneInput.value : '';
    if (phoneInput) phoneInput.value = '';
    try {
        await testPaymentGateway('Meta WhatsApp Cloud API', endpoint, 'whatsappCloudForm', 'whatsappCloudTestResult');
    } finally {
        if (phoneInput) phoneInput.value = prev;
    }
}

async function testWhatsappCloudSend(endpoint) {
    const phoneInput = document.getElementById('whatsappCloudTestPhone');
    const box = document.getElementById('whatsappCloudTestResult');
    if (!phoneInput || !phoneInput.value.trim()) {
        if (box) {
            box.className = 'rounded-md p-2.5 text-xs bg-amber-50 text-amber-800 border border-amber-200';
            box.textContent = 'Masukkan nomor WhatsApp tujuan terlebih dahulu pada kolom Uji Kirim Pesan.';
        }
        return;
    }
    const form = document.getElementById('whatsappCloudForm');
    if (!form || !box) return;

    box.className = 'rounded-md p-2.5 text-xs bg-slate-100 text-slate-700';
    box.textContent = 'Mengirim pesan tes WhatsApp ke ' + phoneInput.value.trim() + '...';

    try {
        const fd = new FormData(form);
        fd.set('test_action', 'send_test');
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
            },
            body: fd,
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
        box.textContent = 'Gagal mengirim pesan tes: ' + err.message;
    }
}
</script>
@endpush
@endsection
