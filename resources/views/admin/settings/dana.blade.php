@extends('layouts.admin')

@section('title', 'Pengaturan Provider DANA')
@section('page-title', 'Pengaturan Provider DANA')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">account_balance_wallet</span>
            </div>
            Pengaturan Provider DANA
        </h1>
        <p class="text-sm text-slate-500 mt-1">Kelola kredensial & mode integrasi DANA Widget Binding</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi DANA
                    </h3>
                </div>
                <div class="p-5">
                    <form action="{{ route('admin.settings.dana.update') }}" method="POST">
                        @csrf

                        {{-- Mode --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Mode Provider</label>
                            <select name="dana_mode" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                                <option value="mock" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'mock' ? 'selected' : '' }}>Mock (Development)</option>
                                <option value="sandbox" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'sandbox' ? 'selected' : '' }}>Sandbox (Testing)</option>
                                <option value="production" {{ old('dana_mode', $settings['mode'] ?? 'mock') == 'production' ? 'selected' : '' }}>Production (Live)</option>
                            </select>
                            @error('dana_mode') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- API Base URL --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">API Base URL</label>
                            <input type="url" name="dana_api_base_url" value="{{ old('dana_api_base_url', $settings['api_base_url']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="https://api.sandbox.dana.id">
                            @error('dana_api_base_url') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Client ID --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Client ID (X-PARTNER-ID)</label>
                            <input type="text" name="dana_client_id" value="{{ old('dana_client_id', $settings['client_id']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                            @error('dana_client_id') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Client Secret --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Client Secret</label>
                            <input type="password" name="dana_client_secret" value="{{ old('dana_client_secret', $settings['client_secret']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                            @error('dana_client_secret') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Merchant ID --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Merchant ID</label>
                            <input type="text" name="dana_merchant_id" value="{{ old('dana_merchant_id', $settings['merchant_id']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                            @error('dana_merchant_id') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Public Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Public Key (RSA)</label>
                            <textarea name="dana_public_key" rows="4" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----">{{ old('dana_public_key', $settings['public_key']) }}</textarea>
                            @error('dana_public_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2">Public key dari merchant DANA Anda (opsional).</p>
                        </div>

                        {{-- Callback URL --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Callback URL</label>
                            <input type="url" name="dana_callback_url" value="{{ old('dana_callback_url', $settings['callback_url']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="https://citycourier.pabm.space/api/courier/dana/callback">
                            @error('dana_callback_url') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Private Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Private Key (RSA PKCS#8)</label>
                            <textarea name="dana_private_key" rows="6" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----">{{ old('dana_private_key', $settings['private_key']) }}</textarea>
                            @error('dana_private_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2">Private key digunakan untuk menandatangani permintaan ke DANA.</p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" id="btn-test" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors text-sm font-medium flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">wifi_tethering</span>
                                Cek Koneksi
                            </button>
                            <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/30 transition-all text-sm font-semibold flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">save</span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>

                    {{-- Test Result --}}
                    <div id="test-result" class="mt-5 hidden">
                        <div id="test-alert" class="p-4 rounded-xl">
                            <p class="font-semibold text-sm" id="test-title"></p>
                            <p class="text-sm mt-1" id="test-message"></p>
                            <div id="test-data" class="mt-3 p-3 bg-white rounded-lg border border-slate-200 text-xs hidden"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">route</span>
                        Alur DANA Widget Binding
                    </h3>
                </div>
                <div class="p-5">
                    <ol class="space-y-3 text-sm text-slate-600">
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">1</span>Aplikasi kurir memanggil <code class="bg-slate-100 px-1 rounded text-xs">POST /api/courier/dana/connect</code></li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">2</span>Backend membuat <strong>Deeplink Binding URL</strong></li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">3</span>Kurir membuka URL tersebut</li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">4</span>DANA me-redirect ke callback dengan <code class="bg-slate-100 px-1 rounded text-xs">auth_code</code></li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">5</span>Backend menukar auth_code menjadi access token</li>
                    </ol>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">info</span>
                        Mode Tersedia
                    </h3>
                </div>
                <div class="p-5 space-y-2">
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <p class="font-semibold text-slate-700 text-sm">Mock</p>
                        <p class="text-xs text-slate-500">Simulasi tanpa koneksi asli (development)</p>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <p class="font-semibold text-slate-700 text-sm">Sandbox</p>
                        <p class="text-xs text-slate-500">Testing di lingkungan DANA sandbox</p>
                    </div>
                    <div class="p-3 bg-slate-50 rounded-lg">
                        <p class="font-semibold text-slate-700 text-sm">Production</p>
                        <p class="text-xs text-slate-500">Live (perlu kredensial production)</p>
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
            testAlert.className = 'p-4 rounded-xl ' + (data.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200');
            document.getElementById('test-title').innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            document.getElementById('test-title').className = 'font-semibold text-sm ' + (data.success ? 'text-green-700' : 'text-red-700');
            document.getElementById('test-message').innerText = data.message;
        })
        .catch(() => {
            document.getElementById('test-result').classList.remove('hidden');
            document.getElementById('test-alert').className = 'p-4 rounded-xl bg-red-50 border border-red-200';
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
