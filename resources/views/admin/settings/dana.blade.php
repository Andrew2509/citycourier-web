@extends('layouts.admin')

@section('title', 'Pengaturan Provider DANA')
@section('page-title', 'Pengaturan Provider DANA')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
        </div>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Pengaturan Provider DANA</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola kredensial & mode integrasi DANA Widget Binding</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi DANA
                    </h3>
                </div>
                <div class="p-space-xl">
                    <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                        @csrf

                        {{-- Mode --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Mode Provider</label>
                            <select name="dana_mode" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                                <option value="mock" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                <option value="sandbox" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                <option value="production" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                            </select>
                            @error('dana_mode') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- API Base URL --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">API Base URL</label>
                            <input type="url" name="dana_api_base_url" value="{{ old('dana_api_base_url', $settings['api_base_url']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="https://api.sandbox.dana.id">
                            @error('dana_api_base_url') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Client ID --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Client ID (X-PARTNER-ID)</label>
                            <input type="text" name="dana_client_id" value="{{ old('dana_client_id', $settings['client_id']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                            @error('dana_client_id') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Client Secret --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Client Secret</label>
                            <input type="password" name="dana_client_secret" value="{{ old('dana_client_secret', $settings['client_secret']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                            @error('dana_client_secret') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Merchant ID --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Merchant ID</label>
                            <input type="text" name="dana_merchant_id" value="{{ old('dana_merchant_id', $settings['merchant_id']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                            @error('dana_merchant_id') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Public Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Public Key (RSA)</label>
                            <textarea name="dana_public_key" rows="4" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface font-data-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all resize-none" placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----">{{ old('dana_public_key', $settings['public_key']) }}</textarea>
                            @error('dana_public_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm">Public key dari merchant DANA Anda (opsional).</p>
                        </div>

                        {{-- Callback URL --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Callback URL</label>
                            <input type="url" name="dana_callback_url" value="{{ old('dana_callback_url', $settings['callback_url']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="https://citycourier.pabm.space/api/courier/dana/callback">
                            @error('dana_callback_url') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Private Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Private Key (RSA PKCS#8)</label>
                            <textarea name="dana_private_key" rows="6" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface font-data-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all resize-none" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----">{{ old('dana_private_key', $settings['private_key']) }}</textarea>
                            @error('dana_private_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm">Private key digunakan untuk menandatangani permintaan ke DANA.</p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-end gap-space-md pt-space-md border-t border-surface-container-high">
                            <button type="button" id="btn-test" class="px-space-md py-space-xs rounded-lg border border-surface-container-high text-on-surface-variant hover:bg-surface-container-high transition-colors text-sm font-medium flex items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[16px]">wifi_tethering</span>
                                Cek Koneksi
                            </button>
                            <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary transition-all text-sm font-semibold flex items-center gap-space-xs shadow-sm">
                                <span class="material-symbols-outlined text-[16px]">save</span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>

                    {{-- Test Result --}}
                    <div id="test-result" class="mt-space-lg hidden">
                        <div id="test-alert" class="p-space-md rounded-lg">
                            <p class="font-semibold text-sm" id="test-title"></p>
                            <p class="text-sm mt-space-2xs" id="test-message"></p>
                            <div id="test-data" class="mt-space-sm p-space-sm bg-surface-container-lowest rounded-lg border border-surface-container-high text-xs hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="flex flex-col gap-space-xl">
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">route</span>
                        Alur DANA Widget Binding
                    </h3>
                </div>
                <div class="p-space-xl">
                    <ol class="flex flex-col gap-space-md font-body-sm text-body-sm text-on-surface-variant">
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>Aplikasi kurir memanggil <code class="bg-surface-container-high px-space-xs rounded font-data-mono text-xs">POST /api/courier/dana/connect</code></li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>Backend membuat <strong>Deeplink Binding URL</strong></li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>Kurir membuka URL tersebut</li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">4</span>DANA me-redirect ke callback dengan <code class="bg-surface-container-high px-space-xs rounded font-data-mono text-xs">auth_code</code></li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">5</span>Backend menukar auth_code menjadi access token</li>
                    </ol>
                </div>
            </div>

            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">info</span>
                        Mode Tersedia
                    </h3>
                </div>
                <div class="p-space-xl flex flex-col gap-space-sm">
                    <div class="p-space-md bg-surface rounded-lg">
                        <p class="font-body-sm text-body-sm text-on-surface font-semibold">Mock</p>
                        <p class="font-body-sm text-body-sm text-secondary">Simulasi tanpa koneksi asli (development)</p>
                    </div>
                    <div class="p-space-md bg-surface rounded-lg">
                        <p class="font-body-sm text-body-sm text-on-surface font-semibold">Sandbox</p>
                        <p class="font-body-sm text-body-sm text-secondary">Testing di lingkungan DANA sandbox</p>
                    </div>
                    <div class="p-space-md bg-surface rounded-lg">
                        <p class="font-body-sm text-body-sm text-on-surface font-semibold">Production</p>
                        <p class="font-body-sm text-body-sm text-secondary">Live (perlu kredensial production)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnTest = document.getElementById('btn-test');

    btnTest.addEventListener('click', function() {
        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Menghubungkan...';
        
        fetch("{{ route('admin.settings.dana.test') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(r => r.json())
        .then(data => {
            const testResult = document.getElementById('test-result');
            const testAlert = document.getElementById('test-alert');
            testResult.classList.remove('hidden');
            testAlert.className = 'p-space-md rounded-lg ' + (data.success ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200');
            document.getElementById('test-title').innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            document.getElementById('test-title').className = 'font-semibold text-sm ' + (data.success ? 'text-emerald-700' : 'text-red-700');
            document.getElementById('test-message').innerText = data.message;
        })
        .catch(() => {
            document.getElementById('test-result').classList.remove('hidden');
            document.getElementById('test-alert').className = 'p-space-md rounded-lg bg-red-50 border border-red-200';
            document.getElementById('test-title').innerText = 'Error';
            document.getElementById('test-message').innerText = 'Terjadi kesalahan sistem.';
        })
        .finally(() => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi';
        });
    });
});
</script>
@endsection
