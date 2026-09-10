@extends('layouts.admin')

@section('title', 'Provider RajaOngkir')
@section('page-title', 'Provider RajaOngkir')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[22px]">local_shipping</span>
        </div>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Provider RajaOngkir</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola konfigurasi API RajaOngkir untuk cek ongkir</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
                <div class="px-space-xl py-space-md border-b border-surface-container-high">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API RajaOngkir
                    </h3>
                </div>
                <div class="p-space-xl">
                    <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                        @csrf

                        {{-- API Key --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">RajaOngkir API Key</label>
                            <input type="text" name="rajaongkir_api_key" value="{{ old('rajaongkir_api_key', $settings['api_key']) }}" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all" placeholder="Masukkan API Key dari dashboard RajaOngkir">
                            @error('rajaongkir_api_key') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Provider --}}
                        <div class="mb-space-lg">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Service Provider</label>
                            <select name="rajaongkir_provider" id="rajaongkir_provider" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                                <option value="rajaongkir" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                                <option value="komerce" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                            </select>
                            @error('rajaongkir_provider') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                            <p class="font-label-sm text-label-sm text-secondary mt-space-sm"><strong>Komerce</strong> mendukung pencarian hingga tingkat Kelurahan/Desa.</p>
                        </div>

                        {{-- Account Type --}}
                        <div class="mb-space-lg" id="account_type_group">
                            <label class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Tipe Akun</label>
                            <select name="rajaongkir_account_type" class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all">
                                <option value="starter" {{ old('rajaongkir_account_type', $settings['account_type']) == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                                <option value="basic" {{ old('rajaongkir_account_type', $settings['account_type']) == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                                <option value="pro" {{ old('rajaongkir_account_type', $settings['account_type']) == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                            </select>
                            @error('rajaongkir_account_type') <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p> @enderror
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="mb-space-lg" id="sandbox_mode_group">
                            <label class="flex items-center gap-space-md p-space-lg rounded-lg bg-surface border border-surface-container-high cursor-pointer">
                                <input type="checkbox" name="rajaongkir_sandbox" id="rajaongkir_sandbox" value="1" {{ \App\Models\Setting::get('rajaongkir_sandbox') ? 'checked' : '' }} class="w-5 h-5 rounded border-surface-container-high text-primary focus:ring-primary">
                                <div>
                                    <p class="font-body-sm text-body-sm text-on-surface font-semibold">Sandbox Mode</p>
                                    <p class="font-label-sm text-label-sm text-secondary">Aktifkan untuk lingkungan Testing/Sandbox Komerce</p>
                                </div>
                            </label>
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
                        <span class="material-symbols-outlined text-primary">info</span>
                        Informasi Integrasi
                    </h3>
                </div>
                <div class="p-space-xl">
                    <p class="font-body-sm text-body-sm text-on-surface mb-space-md">Integrasi RajaOngkir digunakan untuk menghitung biaya pengiriman secara otomatis.</p>
                    <div class="flex flex-col gap-space-md">
                        <p class="font-label-sm text-label-sm text-secondary font-bold uppercase tracking-wider">Ketentuan Tipe Akun:</p>
                        <div class="flex flex-col gap-space-sm">
                            <div class="p-space-md bg-surface rounded-lg">
                                <p class="font-body-sm text-body-sm text-on-surface font-semibold">Starter</p>
                                <p class="font-body-sm text-body-sm text-secondary">JNE, POS, TIKI. Sampai tingkat kota.</p>
                            </div>
                            <div class="p-space-md bg-surface rounded-lg">
                                <p class="font-body-sm text-body-sm text-on-surface font-semibold">Basic</p>
                                <p class="font-body-sm text-body-sm text-secondary">Lebih banyak kurir. Sampai tingkat kota.</p>
                            </div>
                            <div class="p-space-md bg-surface rounded-lg">
                                <p class="font-body-sm text-body-sm text-on-surface font-semibold">Pro</p>
                                <p class="font-body-sm text-body-sm text-secondary">Mendukung hingga tingkat kecamatan.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const providerSelect = document.getElementById('rajaongkir_provider');
    const accountTypeGroup = document.getElementById('account_type_group');
    const sandboxModeGroup = document.getElementById('sandbox_mode_group');
    const btnTest = document.getElementById('btn-test');

    function toggleFields() {
        if (providerSelect.value === 'komerce') {
            accountTypeGroup.style.display = 'none';
            sandboxModeGroup.style.display = 'block';
        } else {
            accountTypeGroup.style.display = 'block';
            sandboxModeGroup.style.display = 'none';
        }
    }
    
    providerSelect.addEventListener('change', toggleFields);
    toggleFields();

    btnTest.addEventListener('click', function() {
        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">progress_activity</span> Menghubungkan...';
        
        fetch("{{ route('admin.settings.rajaongkir.test') }}", {
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
