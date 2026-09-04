@extends('layouts.admin')

@section('title', 'Provider WhatsApp')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Provider WhatsApp</h1>
        <p class="text-sm text-slate-400 mt-1">Kelola konfigurasi API OrbitWA</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-surface-border overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h4 class="text-base font-bold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">chat</span>
                    Konfigurasi API OrbitWA
                </h4>
            </div>
            <div class="p-5">
                <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                    @csrf

                    <div class="mb-5">
                        <label for="orbitwa_api_key" class="block text-sm font-semibold text-slate-700 mb-2">OrbitWA API Key</label>
                        <input type="text" name="orbitwa_api_key" id="orbitwa_api_key"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('orbitwa_api_key') border-error @enderror"
                               value="{{ old('orbitwa_api_key', $settings['api_key']) }}"
                               placeholder="Masukkan API Key dari dashboard OrbitWA">
                        @error('orbitwa_api_key')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 mt-1.5">Anda bisa mendapatkan API Key dari menu <strong>Profile</strong> atau <strong>API</strong> di dashboard OrbitWA.</p>
                    </div>

                    <div class="mb-5">
                        <label for="orbitwa_device_id" class="block text-sm font-semibold text-slate-700 mb-2">ID Device</label>
                        <input type="text" name="orbitwa_device_id" id="orbitwa_device_id"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('orbitwa_device_id') border-error @enderror"
                               value="{{ old('orbitwa_device_id', $settings['device_id']) }}"
                               placeholder="Contoh: 1">
                        @error('orbitwa_device_id')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 mt-1.5">Masukkan ID Device yang terdaftar di dashboard OrbitWA Anda.</p>
                    </div>

                    <div class="mb-5">
                        <label for="orbitwa_base_url" class="block text-sm font-semibold text-slate-700 mb-2">Base URL API</label>
                        <input type="url" name="orbitwa_base_url" id="orbitwa_base_url"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('orbitwa_base_url') border-error @enderror"
                               value="{{ old('orbitwa_base_url', $settings['base_url']) }}"
                               placeholder="Contoh: https://orbitwaapi.site/api/v1">
                        @error('orbitwa_base_url')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 mt-1.5">Secara default adalah <code class="bg-slate-100 px-1.5 py-0.5 rounded text-primary">https://orbitwaapi.site/api/v1</code>.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
            <!-- Info Card -->
            <div class="bg-white rounded-2xl border border-surface-border p-5">
                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-info text-lg">info</span>
                    Informasi Integrasi
                </h4>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Integrasi ini digunakan untuk mengirimkan kode OTP melalui WhatsApp kepada pengguna saat proses Login/Registrasi lewat nomor HP.
                </p>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <h5 class="text-xs font-bold text-slate-600 mb-2">Tips:</h5>
                    <ul class="text-xs text-slate-500 space-y-1.5">
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[12px] text-success mt-0.5">check</span>
                            Pastikan status perangkat WhatsApp Anda <strong>Connected</strong> di dashboard OrbitWA.
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-[12px] text-success mt-0.5">check</span>
                            Gunakan format nomor HP internasional (tanpa +) jika memungkinkan.
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Test Connection -->
            <div class="bg-primary/5 rounded-2xl border border-dashed border-primary/30 p-5">
                <h4 class="text-sm font-bold text-primary flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-lg">wifi_tethering</span>
                    Test Koneksi
                </h4>
                <p class="text-xs text-slate-500 mb-4">
                    Kirim pesan percobaan untuk memastikan API Key sudah benar.
                </p>
                <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST">
                    @csrf
                    <input type="text" name="phone"
                           class="w-full px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary mb-3"
                           placeholder="Nomor HP (Contoh: 08123...)" required>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-semibold bg-white text-primary border border-primary/20 hover:bg-primary hover:text-white transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">send</span>
                        Kirim Pesan Test
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
