@extends('layouts.admin')

@section('title', 'Provider WhatsApp')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <div class="w-12 h-12 rounded-xl bg-primary-container text-on-primary flex items-center justify-center shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[22px]">chat</span>
        </div>
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight font-bold">Provider WhatsApp</h1>
            <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Kelola konfigurasi API Fonnte</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-space-xl">
        {{-- Main Form --}}
        <div class="lg:col-span-2 bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
            <div class="px-space-xl py-space-md border-b border-surface-container-high">
                <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-primary">chat</span>
                    Konfigurasi API Fonnte
                </h4>
            </div>
            <div class="p-space-xl">
                <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST">
                    @csrf

                    <div class="mb-space-lg">
                        <label for="fonnte_token" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Fonnte API Token</label>
                        <input type="text" name="fonnte_token" id="fonnte_token"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('fonnte_token') border-red-500 @enderror"
                               value="{{ old('fonnte_token', $settings['token']) }}"
                               placeholder="Masukkan token dari dashboard Fonnte">
                        @error('fonnte_token')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                        <p class="font-label-sm text-label-sm text-secondary mt-space-xs">Anda bisa mendapatkan token dari menu <strong>API Access</strong> di dashboard <a href="https://fonnte.com" target="_blank" class="text-primary hover:underline">fonnte.com</a>.</p>
                    </div>

                    <div class="mb-space-lg">
                        <label for="fonnte_send_number" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Nomor Pengirim (opsional)</label>
                        <input type="text" name="fonnte_send_number" id="fonnte_send_number"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('fonnte_send_number') border-red-500 @enderror"
                               value="{{ old('fonnte_send_number', $settings['send_number']) }}"
                               placeholder="Contoh: 08123456789">
                        @error('fonnte_send_number')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                        <p class="font-label-sm text-label-sm text-secondary mt-space-xs">Nomor WA yang terdaftar di Fonnte. Kosongkan untuk memakai default.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all flex items-center gap-space-xs">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="flex flex-col gap-space-xl">
            {{-- Info Card --}}
            <div class="bg-surface-container-lowest rounded-xl shadow-sm p-space-xl">
                <h4 class="font-label-md text-label-md text-on-surface font-bold flex items-center gap-space-xs mb-space-md">
                    <span class="material-symbols-outlined text-primary text-[18px]">info</span>
                    Informasi Integrasi
                </h4>
                <p class="font-body-sm text-body-sm text-secondary leading-relaxed">
                    Integrasi ini digunakan untuk mengirimkan kode OTP melalui WhatsApp kepada pengguna saat proses Login/Registrasi lewat nomor HP.
                </p>
                <div class="mt-space-md pt-space-md border-t border-surface-container-high">
                    <h5 class="font-label-sm text-label-sm text-on-surface-variant font-bold mb-space-sm">Tips:</h5>
                    <ul class="font-body-sm text-body-sm text-secondary flex flex-col gap-space-sm">
                        <li class="flex items-start gap-space-xs">
                            <span class="material-symbols-outlined text-[12px] text-emerald-600 mt-0.5">check</span>
                            Pastikan status akun Fonnte Anda <strong>aktif</strong> dan memiliki saldo cukup.
                        </li>
                        <li class="flex items-start gap-space-xs">
                            <span class="material-symbols-outlined text-[12px] text-emerald-600 mt-0.5">check</span>
                            Gunakan format nomor HP internasional (tanpa +) jika memungkinkan.
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Test Connection --}}
            <div class="bg-primary-container/5 rounded-xl border border-dashed border-primary-container/30 p-space-xl">
                <h4 class="font-label-md text-label-md text-primary font-bold flex items-center gap-space-xs mb-space-md">
                    <span class="material-symbols-outlined text-[18px]">wifi_tethering</span>
                    Test Koneksi
                </h4>
                <p class="font-body-sm text-body-sm text-secondary mb-space-md">
                    Kirim pesan percobaan untuk memastikan token sudah benar.
                </p>
                <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST">
                    @csrf
                    <input type="text" name="phone"
                           class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary mb-space-sm"
                           placeholder="Nomor HP (Contoh: 08123...)" required>
                    <button type="submit" class="w-full px-space-md py-space-xs rounded-lg text-sm font-semibold bg-surface-container-lowest text-primary border border-primary/20 hover:bg-primary hover:text-on-primary transition-all flex items-center justify-center gap-space-xs">
                        <span class="material-symbols-outlined text-[16px]">send</span>
                        Kirim Pesan Test
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
