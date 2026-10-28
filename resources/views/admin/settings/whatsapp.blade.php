@extends('layouts.admin')

@section('title', 'Provider WhatsApp (Fonnte)')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.providers') }}" class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center justify-center transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Fonnte Gateway
                </span>
            </div>
            <h1 class="font-display text-2xl font-bold text-slate-900 mt-1">Provider WhatsApp (Fonnte)</h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola token API Fonnte untuk notifikasi pesanan otomatis, QR Code resi, live tracking, dan OTP login.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Form -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center font-bold">
                        <span class="material-symbols-outlined text-[20px]">chat</span>
                    </div>
                    <h2 class="font-display text-base font-bold text-slate-900">Kredensial API Fonnte</h2>
                </div>
            </div>

            <div class="p-6">
                <form action="{{ route('admin.settings.whatsapp.update') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="fonnte_token" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Fonnte API Token
                        </label>
                        <input type="text" name="fonnte_token" id="fonnte_token"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 focus:bg-white transition-all shadow-sm @error('fonnte_token') border-red-500 @enderror"
                               value="{{ old('fonnte_token', $settings['token']) }}"
                               placeholder="Contoh: vL_9a8X..." required>
                        @error('fonnte_token')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 mt-1.5">
                            Dapatkan API token dari menu <strong>API Access</strong> di dashboard <a href="https://fonnte.com" target="_blank" rel="noopener" class="text-brand-600 hover:underline font-semibold">fonnte.com</a>.
                        </p>
                    </div>

                    <div>
                        <label for="fonnte_send_number" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Nomor Pengirim WhatsApp (Opsional)
                        </label>
                        <input type="text" name="fonnte_send_number" id="fonnte_send_number"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 focus:bg-white transition-all shadow-sm @error('fonnte_send_number') border-red-500 @enderror"
                               value="{{ old('fonnte_send_number', $settings['send_number']) }}"
                               placeholder="Contoh: 08123456789">
                        @error('fonnte_send_number')
                            <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 mt-1.5">
                            Nomor WhatsApp device yang terhubung ke Fonnte. Kosongkan jika memakai device default akun.
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-xs shadow-md shadow-brand-600/20 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            <span>Simpan Konfigurasi</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar Helper & Test Connection -->
        <div class="space-y-6">
            <!-- Informasi Integrasi -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-2 text-slate-800 font-display font-bold text-sm">
                    <span class="material-symbols-outlined text-brand-600 text-[20px]">info</span>
                    <span>Fitur Notifikasi Aktif</span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Integrasi Fonnte di City Courier bekerja secara otomatis pada:
                </p>
                <ul class="text-xs text-slate-600 space-y-2.5">
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0 mt-0.5">check_circle</span>
                        <span><strong>Notifikasi Pesanan Berhasil</strong> langsung ke WhatsApp pengirim beserta QR Code dan link Live Tracking.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0 mt-0.5">check_circle</span>
                        <span><strong>Verifikasi OTP WhatsApp</strong> saat registrasi dan login driver kurir.</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-[16px] text-emerald-600 shrink-0 mt-0.5">check_circle</span>
                        <span><strong>Tombol Kirim Ulang WA</strong> pada halaman detail pesanan admin.</span>
                    </li>
                </ul>
            </div>

            <!-- Test Connection Box -->
            <div class="bg-orange-50/50 rounded-2xl border border-orange-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center gap-2 text-brand-800 font-display font-bold text-sm">
                    <span class="material-symbols-outlined text-[20px] text-brand-600">wifi_tethering</span>
                    <span>Uji Coba Pengiriman WA</span>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Kirim pesan tes ke nomor WhatsApp Anda untuk memverifikasi token API Fonnte berfungsi dengan baik.
                </p>

                <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label for="test_phone" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Nomor WhatsApp Penerima</label>
                        <input type="text" name="test_phone" id="test_phone"
                               class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white text-slate-900 text-xs font-mono placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-600 transition-all shadow-sm"
                               placeholder="Contoh: 08123456789" required>
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">send</span>
                        <span>Kirim Pesan Uji Coba</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
