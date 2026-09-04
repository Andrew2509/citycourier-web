@extends('layouts.admin')

@section('title', 'Provider RajaOngkir')
@section('page-title', 'Provider RajaOngkir')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-xl">local_shipping</span>
            </div>
            Provider RajaOngkir
        </h1>
        <p class="text-sm text-slate-500 mt-1">Kelola konfigurasi API RajaOngkir untuk cek ongkir</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h3 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">settings</span>
                        Konfigurasi API RajaOngkir
                    </h3>
                </div>
                <div class="p-5">
                    <form action="{{ route('admin.settings.rajaongkir.update') }}" method="POST">
                        @csrf

                        {{-- API Key --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">RajaOngkir API Key</label>
                            <input type="text" name="rajaongkir_api_key" value="{{ old('rajaongkir_api_key', $settings['api_key']) }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="Masukkan API Key dari dashboard RajaOngkir">
                            @error('rajaongkir_api_key') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Provider --}}
                        <div class="mb-5">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Service Provider</label>
                            <select name="rajaongkir_provider" id="rajaongkir_provider" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                                <option value="rajaongkir" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'rajaongkir' ? 'selected' : '' }}>RajaOngkir (Official)</option>
                                <option value="komerce" {{ old('rajaongkir_provider', $settings['provider'] ?? 'rajaongkir') == 'komerce' ? 'selected' : '' }}>Komerce (RajaOngkir v2)</option>
                            </select>
                            @error('rajaongkir_provider') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400 mt-2"><strong>Komerce</strong> mendukung pencarian hingga tingkat Kelurahan/Desa.</p>
                        </div>

                        {{-- Account Type --}}
                        <div class="mb-5" id="account_type_group">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tipe Akun</label>
                            <select name="rajaongkir_account_type" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                                <option value="starter" {{ old('rajaongkir_account_type', $settings['account_type']) == 'starter' ? 'selected' : '' }}>Starter (Gratis)</option>
                                <option value="basic" {{ old('rajaongkir_account_type', $settings['account_type']) == 'basic' ? 'selected' : '' }}>Basic (Berbayar)</option>
                                <option value="pro" {{ old('rajaongkir_account_type', $settings['account_type']) == 'pro' ? 'selected' : '' }}>Pro (Berbayar)</option>
                            </select>
                            @error('rajaongkir_account_type') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Sandbox Mode --}}
                        <div class="mb-5" id="sandbox_mode_group">
                            <label class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200 cursor-pointer">
                                <input type="checkbox" name="rajaongkir_sandbox" id="rajaongkir_sandbox" value="1" {{ \App\Models\Setting::get('rajaongkir_sandbox') ? 'checked' : '' }} class="w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary">
                                <div>
                                    <p class="text-sm font-semibold text-slate-700">Sandbox Mode</p>
                                    <p class="text-xs text-slate-400">Aktifkan untuk lingkungan Testing/Sandbox Komerce</p>
                                </div>
                            </label>
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
                        <span class="material-symbols-outlined text-primary">info</span>
                        Informasi Integrasi
                    </h3>
                </div>
                <div class="p-5">
                    <p class="text-sm text-slate-600 mb-4">Integrasi RajaOngkir digunakan untuk menghitung biaya pengiriman secara otomatis.</p>
                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Ketentuan Tipe Akun:</p>
                        <div class="space-y-2 text-sm">
                            <div class="p-3 bg-slate-50 rounded-lg">
                                <p class="font-semibold text-slate-700">Starter</p>
                                <p class="text-xs text-slate-500">JNE, POS, TIKI. Sampai tingkat kota.</p>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-lg">
                                <p class="font-semibold text-slate-700">Basic</p>
                                <p class="text-xs text-slate-500">Lebih banyak kurir. Sampai tingkat kota.</p>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-lg">
                                <p class="font-semibold text-slate-700">Pro</p>
                                <p class="text-xs text-slate-500">Mendukung hingga tingkat kecamatan.</p>
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
