@extends('layouts.admin')

@section('title', 'Layanan Pembayaran')
@section('page-title', 'Layanan Pembayaran')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[22px]">credit_card</span>
        </div>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Layanan Pembayaran</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola konfigurasi API Komerce untuk Virtual Account dan QRIS</p>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="p-space-md rounded-lg bg-emerald-50 border border-emerald-200 flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
            <p class="font-body-sm text-body-sm text-emerald-700">{{ session('success') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="p-space-md rounded-lg bg-red-50 border border-red-200 flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-red-600 text-[20px]">error</span>
            <p class="font-body-sm text-body-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Main Form --}}
        <div class="lg:col-span-2 flex flex-col gap-space-xl">
            {{-- Config Card --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API Komerce Payment
                    </h3>
                </div>
                <div class="p-space-xl">
                    <form action="{{ route('admin.settings.payment.update') }}" method="POST" id="form-payment">
                        @csrf

                        {{-- API Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                                Komerce Payment API Key
                                <span class="ml-space-sm px-space-sm py-space-2xs bg-red-100 text-red-600 rounded-full text-xs font-bold">Wajib</span>
                            </label>
                            <div class="flex">
                                <input type="password" name="komerce_payment_api_key" id="komerce_payment_api_key" value="{{ old('komerce_payment_api_key', $settings['api_key']) }}" class="flex-1 px-space-md py-space-xs rounded-l-lg border border-surface-container-high border-r-0 bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="Masukkan API Key dari dashboard Komerce" autocomplete="off">
                                <button type="button" id="btn-toggle-key" class="px-space-md rounded-r-lg border border-surface-container-high bg-surface text-on-surface-variant hover:bg-surface-container-high transition-colors">
                                    <span class="material-symbols-outlined text-[18px]" id="eye-icon">visibility</span>
                                </button>
                            </div>
                            @error('komerce_payment_api_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm">Dapatkan API Key dari <a href="https://collaborator.komerce.id" target="_blank" class="text-primary hover:underline">collaborator.komerce.id</a> → Integration → API Key</p>
                        </div>

                        {{-- Environment --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-md">Environment (Mode)</label>
                            <div class="grid grid-cols-2 gap-space-md">
                                <label class="cursor-pointer">
                                    <input type="radio" name="komerce_payment_env" value="sandbox" class="hidden peer" {{ old('komerce_payment_env', $settings['env']) === 'sandbox' ? 'checked' : '' }}>
                                    <div class="p-space-lg rounded-lg border-2 border-surface-container-high peer-checked:border-primary peer-checked:bg-primary-container/5 transition-all text-center">
                                        <span class="material-symbols-outlined text-[24px] text-secondary peer-checked:text-primary">science</span>
                                        <p class="font-body-sm text-body-sm text-on-surface font-semibold mt-space-sm">Sandbox</p>
                                        <p class="font-label-sm text-label-sm text-secondary">Testing & Development</p>
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="komerce_payment_env" value="production" class="hidden peer" {{ old('komerce_payment_env', $settings['env']) === 'production' ? 'checked' : '' }}>
                                    <div class="p-space-lg rounded-lg border-2 border-surface-container-high peer-checked:border-primary peer-checked:bg-primary-container/5 transition-all text-center">
                                        <span class="material-symbols-outlined text-[24px] text-secondary peer-checked:text-primary">rocket_launch</span>
                                        <p class="font-body-sm text-body-sm text-on-surface font-semibold mt-space-sm">Production</p>
                                        <p class="font-label-sm text-label-sm text-secondary">Live & Nyata</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Callback Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                                Callback Key
                                <span class="ml-space-sm px-space-sm py-space-2xs bg-emerald-100 text-emerald-600 rounded-full text-xs font-bold">Opsional</span>
                            </label>
                            <input type="text" name="komerce_payment_callback_key" value="{{ old('komerce_payment_callback_key', $settings['callback_key']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="Key untuk verifikasi webhook">
                            @error('komerce_payment_callback_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm">Webhook: <code class="bg-surface-container-high px-space-xs py-space-2xs rounded font-data-mono text-xs">{{ config('app.url') }}/api/payment/callback</code></p>
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

            {{-- Endpoint Table --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">api</span>
                        Endpoint API yang Digunakan
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface border-b border-surface-container-high">
                                <th class="px-space-xl py-space-md font-label-sm text-label-sm text-secondary uppercase">Method</th>
                                <th class="px-space-xl py-space-md font-label-sm text-label-sm text-secondary uppercase">Endpoint</th>
                                <th class="px-space-xl py-space-md font-label-sm text-label-sm text-secondary uppercase">Fungsi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-container-high/60 font-body-sm text-body-sm text-on-surface">
                            <tr><td class="px-space-xl py-space-md"><span class="px-space-xs py-space-2xs bg-emerald-100 text-emerald-700 rounded text-xs font-bold">GET</span></td><td class="px-space-xl py-space-md font-data-mono text-xs text-on-surface-variant">/api/payment/methods</td><td class="px-space-xl py-space-md text-on-surface-variant">Ambil daftar VA & QRIS</td></tr>
                            <tr><td class="px-space-xl py-space-md"><span class="px-space-xs py-space-2xs bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-space-xl py-space-md font-data-mono text-xs text-on-surface-variant">/api/payment/create</td><td class="px-space-xl py-space-md text-on-surface-variant">Buat transaksi pembayaran</td></tr>
                            <tr><td class="px-space-xl py-space-md"><span class="px-space-xs py-space-2xs bg-emerald-100 text-emerald-700 rounded text-xs font-bold">GET</span></td><td class="px-space-xl py-space-md font-data-mono text-xs text-on-surface-variant">/api/payment/{id}/status</td><td class="px-space-xl py-space-md text-on-surface-variant">Cek status pembayaran</td></tr>
                            <tr><td class="px-space-xl py-space-md"><span class="px-space-xs py-space-2xs bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-space-xl py-space-md font-data-mono text-xs text-on-surface-variant">/api/payment/{id}/cancel</td><td class="px-space-xl py-space-md text-on-surface-variant">Batalkan pembayaran</td></tr>
                            <tr><td class="px-space-xl py-space-md"><span class="px-space-xs py-space-2xs bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span></td><td class="px-space-xl py-space-md font-data-mono text-xs text-on-surface-variant">/api/payment/callback</td><td class="px-space-xl py-space-md text-on-surface-variant">Webhook dari Komerce</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Info Panel --}}
        <div class="flex flex-col gap-space-xl">
            {{-- Status --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">analytics</span>
                        Status Konfigurasi
                    </h3>
                </div>
                <div class="p-space-xl flex flex-col gap-space-md">
                    <div class="flex items-center justify-between py-space-sm border-b border-surface-container-high">
                        <span class="font-body-sm text-body-sm text-secondary">API Key</span>
                        <span class="font-body-sm text-body-sm font-semibold {{ $settings['api_key'] ? 'text-emerald-600' : 'text-red-600' }}">{{ $settings['api_key'] ? '✓ Terkonfigurasi' : '✗ Belum diisi' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-space-sm border-b border-surface-container-high">
                        <span class="font-body-sm text-body-sm text-secondary">Environment</span>
                        <span class="px-space-xs py-space-2xs rounded text-xs font-bold {{ ($settings['env'] ?? 'sandbox') === 'production' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($settings['env'] ?? 'sandbox') }}</span>
                    </div>
                    <div class="flex items-center justify-between py-space-sm">
                        <span class="font-body-sm text-body-sm text-secondary">Base URL</span>
                        <span class="font-data-mono text-xs text-on-surface-variant">{{ ($settings['env'] ?? 'sandbox') === 'production' ? 'api.komerce.id' : 'api-sandbox.komerce.id' }}</span>
                    </div>
                </div>
            </div>

            {{-- Guide --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">menu_book</span>
                        Cara Mendapatkan API Key
                    </h3>
                </div>
                <div class="p-space-xl">
                    <ol class="flex flex-col gap-space-md font-body-sm text-body-sm text-on-surface-variant">
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">1</span>Buka <a href="https://collaborator.komerce.id" target="_blank" class="text-primary hover:underline">collaborator.komerce.id</a></li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">2</span>Login dengan akun RajaOngkir</li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">3</span>Menu <strong>Integration → API Key</strong></li>
                        <li class="flex items-start gap-space-sm"><span class="w-5 h-5 rounded-full bg-primary-container text-on-primary text-xs flex items-center justify-center shrink-0 mt-0.5">4</span>Salin API Key dan tempel di form</li>
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
            testAlert.className = 'p-space-md rounded-lg ' + (data.success ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200');
            document.getElementById('test-title').innerText = data.success ? '✓ Koneksi Berhasil' : '✗ Koneksi Gagal';
            document.getElementById('test-title').className = 'font-semibold text-sm ' + (data.success ? 'text-emerald-700' : 'text-red-700');
            document.getElementById('test-message').innerText = data.message;
        })
        .catch(() => {
            document.getElementById('test-result').classList.remove('hidden');
            document.getElementById('test-alert').className = 'p-space-md rounded-lg bg-red-50 border border-red-200';
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
