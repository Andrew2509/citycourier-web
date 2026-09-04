@extends('layouts.admin')

@section('title', 'Layanan Pembayaran')
@section('page-title', 'Layanan Pembayaran')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">credit_card</span>
            </div>
            Layanan Pembayaran
        </h1>
        <p class="text-sm text-slate-500 mt-1">Kelola konfigurasi API Komerce untuk Virtual Account dan QRIS</p>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-green-50 border border-green-200 flex items-center gap-3">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-3">
            <span class="material-symbols-outlined text-red-600">error</span>
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Config Card --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API Komerce Payment
                    </h3>
                </div>
                <div class="p-5">
                    <form action="{{ route('admin.settings.payment.update') }}" method="POST" id="form-payment">
                        @csrf

                        {{-- API Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Komerce Payment API Key
                                <span class="ml-2 px-2 py-0.5 bg-red-100 text-red-600 rounded-full text-xs font-semibold">Wajib</span>
                            </label>
                            <div class="flex">
                                <input type="password" name="komerce_payment_api_key" id="komerce_payment_api_key" value="{{ old('komerce_payment_api_key', $settings['api_key']) }}" class="flex-1 px-4 py-3 rounded-l-xl border border-slate-200 border-r-0 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="Masukkan API Key dari dashboard Komerce" autocomplete="off">
                                <button type="button" id="btn-toggle-key" class="px-4 rounded-r-xl border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 transition-colors">
                                    <span class="material-symbols-outlined text-lg" id="eye-icon">visibility</span>
                                </button>
                            </div>
                            @error('komerce_payment_api_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2">Dapatkan API Key dari <a href="https://collaborator.komerce.id" target="_blank" class="text-primary hover:underline">collaborator.komerce.id</a> → Integration → API Key</p>
                        </div>

                        {{-- Environment --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-3">Environment (Mode)</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="komerce_payment_env" value="sandbox" class="hidden peer" {{ old('komerce_payment_env', $settings['env']) === 'sandbox' ? 'checked' : '' }}>
                                    <div class="p-4 rounded-xl border-2 border-slate-200 peer-checked:border-primary peer-checked:bg-primary/5 transition-all text-center">
                                        <span class="material-symbols-outlined text-2xl text-slate-400 peer-checked:text-primary">science</span>
                                        <p class="text-sm font-semibold text-slate-700 mt-2">Sandbox</p>
                                        <p class="text-xs text-slate-400">Testing & Development</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="komerce_payment_env" value="production" class="hidden peer" {{ old('komerce_payment_env', $settings['env']) === 'production' ? 'checked' : '' }}>
                                    <div class="p-4 rounded-xl border-2 border-slate-200 peer-checked:border-primary peer-checked:bg-primary/5 transition-all text-center">
                                        <span class="material-symbols-outlined text-2xl text-slate-400 peer-checked:text-primary">rocket_launch</span>
                                        <p class="text-sm font-semibold text-slate-700 mt-2">Production</p>
                                        <p class="text-xs text-slate-400">Live & Nyata</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Callback Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Callback Key
                                <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-600 rounded-full text-xs font-semibold">Opsional</span>
                            </label>
                            <input type="text" name="komerce_payment_callback_key" value="{{ old('komerce_payment_callback_key', $settings['callback_key']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="Key untuk verifikasi webhook">
                            @error('komerce_payment_callback_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2">Webhook: <code class="bg-slate-100 px-1.5 py-0.5 rounded">{{ config('app.url') }}/api/payment/callback</code></p>
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

            {{-- Endpoint Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">api</span>
                        Endpoint API yang Digunakan
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Method</th>
                                <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Endpoint</th>
                                <th class="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Fungsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr><td class="px-5 py-3"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-bold">GET</span></td><td class="px-5 py-3 font-mono text-xs text-slate-600">/api/payment/methods</td><td class="px-5 py-3 text-slate-600">Ambil daftar VA & QRIS</td></tr>
                            <tr><td class="px-5 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-5 py-3 font-mono text-xs text-slate-600">/api/payment/create</td><td class="px-5 py-3 text-slate-600">Buat transaksi pembayaran</td></tr>
                            <tr><td class="px-5 py-3"><span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-bold">GET</span></td><td class="px-5 py-3 font-mono text-xs text-slate-600">/api/payment/{id}/status</td><td class="px-5 py-3 text-slate-600">Cek status pembayaran</td></tr>
                            <tr><td class="px-5 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-5 py-3 font-mono text-xs text-slate-600">/api/payment/{id}/cancel</td><td class="px-5 py-3 text-slate-600">Batalkan pembayaran</td></tr>
                            <tr><td class="px-5 py-3"><span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-5 py-3 font-mono text-xs text-slate-600">/api/payment/callback</td><td class="px-5 py-3 text-slate-600">Webhook dari Komerce</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="space-y-6">
            {{-- Status --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">analytics</span>
                        Status Konfigurasi
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500">API Key</span>
                        <span class="text-sm font-semibold {{ $settings['api_key'] ? 'text-success' : 'text-danger' }}">{{ $settings['api_key'] ? '✓ Terkonfigurasi' : '✗ Belum diisi' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-sm text-slate-500">Environment</span>
                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ ($settings['env'] ?? 'sandbox') === 'production' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($settings['env'] ?? 'sandbox') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-500">Base URL</span>
                        <span class="text-xs font-mono text-slate-600">{{ ($settings['env'] ?? 'sandbox') === 'production' ? 'api.komerce.id' : 'api-sandbox.komerce.id' }}</span>
                    </div>
                </div>
            </div>

            {{-- Guide --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">menu_book</span>
                        Cara Mendapatkan API Key
                    </h3>
                </div>
                <div class="p-5">
                    <ol class="space-y-3 text-sm text-slate-600">
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">1</span>Buka <a href="https://collaborator.komerce.id" target="_blank" class="text-primary hover:underline">collaborator.komerce.id</a></li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">2</span>Login dengan akun RajaOngkir</li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">3</span>Menu <strong>Integration → API Key</strong></li>
                        <li class="flex items-start gap-2"><span class="w-5 h-5 rounded-full bg-primary text-white text-xs flex items-center justify-center flex-shrink-0 mt-0.5">4</span>Salin API Key dan tempel di form</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const keyInput = document.getElementById('komerce_payment_api_key');
    const btnToggle = document.getElementById('btn-toggle-key');
    const eyeIcon = document.getElementById('eye-icon');

    btnToggle.addEventListener('click', function () {
        if (keyInput.type === 'password') {
            keyInput.type = 'text';
            eyeIcon.innerText = 'visibility_off';
        } else {
            keyInput.type = 'password';
            eyeIcon.innerText = 'visibility';
        }
    });

    const btnTest = document.getElementById('btn-test');
    btnTest.addEventListener('click', function () {
        const apiKey = keyInput.value;
        if (!apiKey) { alert('Masukkan API Key terlebih dahulu.'); return; }
        
        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Mengecek...';
        
        fetch("{{ route('admin.settings.payment.test') }}", {
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
            document.getElementById('test-message').innerText = 'Terjadi kesalahan jaringan.';
        })
        .finally(() => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<span class="material-symbols-outlined text-sm">wifi_tethering</span> Cek Koneksi';
        });
    });
});
</script>
@endsection
