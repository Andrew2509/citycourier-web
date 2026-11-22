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
            <p class="page-subtitle">Pusat orkestrasi integrasi gateway Xendit (satu-satunya payment &amp; payout gateway aktif), WhatsApp Fonnte, peta geolokasi, dan tarif ongkir. API key tersimpan terenkripsi di database.</p>
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
</script>
@endpush
@endsection
