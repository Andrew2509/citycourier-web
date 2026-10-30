@extends('layouts.admin')

@section('title', 'Konfigurasi WhatsApp Fonnte')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.providers') }}" class="back-link" title="Kembali ke Integrasi Provider">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Integrasi WhatsApp (Fonnte API)</h1>
                <p class="page-subtitle">Kelola koneksi gateway WhatsApp untuk notifikasi otomatis pesanan dan live tracking kurir.</p>
            </div>
        </div>
        <div class="page-actions">
            @if(!empty($token))
                <span class="badge badge-green">
                    <span class="badge-dot"></span>
                    Gateway Siap Digunakan
                </span>
            @else
                <span class="badge badge-amber">
                    <span class="badge-dot"></span>
                    Token Belum Dikonfigurasi
                </span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Kolom Kiri / Form Pengaturan Utama --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Form Kredensial Fonnte --}}
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Kredensial API Fonnte</div>
                        <div class="card-subtitle">Masukkan token perangkat aktif dari dashboard resmi Fonnte</div>
                    </div>
                    <a href="https://fonnte.com" target="_blank" rel="noopener" class="text-xs text-brand-700 hover:underline inline-flex items-center gap-1 font-medium">
                        Buka Fonnte.com
                        <span class="material-symbols-outlined text-[14px]">open_in_new</span>
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.whatsapp.update') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="fonnte_token" class="form-label">
                                API Token Perangkat <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="fonnte_token"
                                   id="fonnte_token"
                                   value="{{ old('fonnte_token', $token ?? '') }}"
                                   placeholder="Contoh: pY#aX893kLz0..."
                                   required
                                   class="form-input font-mono text-xs">
                            <p class="form-help">
                                Dapatkan API Token dari menu <strong>Device</strong> di dashboard akun Fonnte Anda.
                            </p>
                        </div>

                        <div>
                            <label for="fonnte_send_number" class="form-label">
                                Nomor Pengirim WhatsApp Default (Opsional)
                            </label>
                            <input type="text"
                                   name="fonnte_send_number"
                                   id="fonnte_send_number"
                                   value="{{ old('fonnte_send_number', $sendNumber ?? '') }}"
                                   placeholder="Contoh: 081234567890"
                                   class="form-input font-mono text-xs">
                            <p class="form-help">
                                Nomor ponsel WhatsApp terhubung yang digunakan sebagai pengirim resmi notifikasi.
                            </p>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">save</span>
                                Simpan Kredensial
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Fitur Notifikasi Otomatis --}}
            <div class="card p-4 space-y-3">
                <div class="card-title">Fungsi Notifikasi WhatsApp yang Aktif</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-1">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-brand-600 text-[18px]">mark_email_read</span>
                            Pesanan Berhasil Dibuat
                        </div>
                        <p class="text-slate-500">
                            Mengirim rincian order, nomor resi, tarif, dan alamat ke nomor pengirim secara otomatis.
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-1">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-brand-600 text-[18px]">qr_code</span>
                            QR Code &amp; Live Tracking
                        </div>
                        <p class="text-slate-500">
                            Menyertakan link live tracking publik unik dan QR Code resi untuk serah terima paket kurir.
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-1">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-brand-600 text-[18px]">password</span>
                            Verifikasi OTP Kurir
                        </div>
                        <p class="text-slate-500">
                            Kirim kode OTP instan saat login atau verifikasi nomor telepon kurir di aplikasi driver.
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-1">
                        <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-brand-600 text-[18px]">sync</span>
                            Kirim Ulang Manual
                        </div>
                        <p class="text-slate-500">
                            Admin dapat memicu pengiriman ulang pesan WhatsApp kapan saja dari halaman detail pengiriman.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan / Uji Coba Pengiriman --}}
        <div class="space-y-5">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Uji Coba Pengiriman Pesan</div>
                        <div class="card-subtitle">Tes respon API WhatsApp Fonnte ke nomor tujuan</div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.whatsapp.test') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="phone" class="form-label">
                                Nomor WhatsApp Penerima <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="phone"
                                   id="phone"
                                   placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                                   required
                                   class="form-input font-mono text-xs">
                            <p class="form-help">
                                Format: 08123456789 atau 628123456789
                            </p>
                        </div>

                        <button type="submit" class="btn btn-secondary w-full">
                            <span class="material-symbols-outlined">send</span>
                            Kirim Pesan Uji Coba
                        </button>
                    </form>
                </div>
            </div>

            {{-- Status Gateway Info --}}
            <div class="card p-4 space-y-2.5 text-xs">
                <div class="font-semibold text-slate-900 border-b border-slate-100 pb-2">
                    Catatan Operasional
                </div>
                <p class="text-slate-500 leading-relaxed">
                    Pastikan perangkat WhatsApp di dashboard Fonnte berada dalam status <strong>Connected</strong> agar pengiriman pesan tidak tertunda.
                </p>
                <div class="rounded bg-slate-50 p-2.5 text-slate-600 border border-slate-200 font-mono text-[11.5px]">
                    Endpoint: https://api.fonnte.com/send
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
