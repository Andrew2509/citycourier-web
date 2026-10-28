@extends('layouts.admin')

@section('title', 'Kelola APK Distribusi')

@section('content')
<div class="space-y-5">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-slate-200">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Distribusi Android
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs text-slate-500 font-medium">Driver &amp; Pelanggan APK</span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Kelola APK Distribusi</h1>
            <p class="text-xs text-slate-500 mt-0.5">Upload link rilis baru, kelola versi aktif, dan bagikan tautan unduh aplikasi</p>
        </div>
        @if($active)
        <a href="{{ route('download.app') }}" target="_blank" rel="noopener" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm self-start sm:self-auto">
            <span class="material-symbols-outlined text-[16px]">download</span>
            <span>Unduh APK Aktif</span>
        </a>
        @endif
    </div>

    <!-- Active Release Card -->
    @if($active)
    <div class="card p-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white border-slate-800 shadow-md">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center shrink-0 border border-white/10">
                    <span class="material-symbols-outlined text-[28px] text-emerald-400">android</span>
                </div>
                <div class="space-y-0.5 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Rilis Publik Aktif</span>
                        <span class="text-xs text-slate-400 font-mono">v{{ $active->version }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-white tracking-tight">City Courier Driver v{{ $active->version }}</h3>
                    <div class="flex items-center gap-2 text-xs text-slate-300 font-mono">
                        <span>{{ $active->formatted_size }}</span>
                        @if($active->created_at)
                            <span>•</span>
                            <span>Dirilis {{ $active->created_at->format('d M Y') }}</span>
                        @endif
                    </div>
                    @if($active->release_notes)
                        <p class="text-xs text-slate-400 mt-1 line-clamp-1 italic">"{{ $active->release_notes }}"</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ $active->google_drive_url }}" target="_blank" rel="noopener" class="btn btn-secondary text-xs h-9 px-3 gap-1.5 bg-white/10 text-white border-white/20 hover:bg-white/20">
                    <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                    <span>Link Drive</span>
                </a>
                <a href="{{ route('download.app') }}" target="_blank" rel="noopener" class="btn btn-primary text-xs h-9 px-3.5 gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    <span>Unduh Sekarang</span>
                </a>
            </div>
        </div>
    </div>
    @endif

    <!-- Add New Version Form -->
    @canPerm('app_download.create')
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-primary-600">add_link</span>
            <div>
                <h2 class="text-xs font-bold text-slate-900">Tambah Versi Rilis Baru</h2>
                <span class="text-[11px] text-slate-500">Versi yang baru ditambahkan akan otomatis menjadi rilis aktif</span>
            </div>
        </div>

        <form action="{{ route('admin.app-download.store') }}" method="POST" class="p-4 sm:p-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="version" class="block text-xs font-semibold text-slate-700 mb-1">Nomor Versi <span class="text-rose-500">*</span></label>
                    <input type="text" name="version" id="version" value="{{ old('version', $active->version ?? '1.0.0') }}"
                           class="form-input text-xs w-full @error('version') border-rose-500 @enderror"
                           placeholder="Contoh: 1.0.1" required>
                    @error('version')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="file_size" class="block text-xs font-semibold text-slate-700 mb-1">Perkiraan Ukuran File (MB)</label>
                    <input type="number" name="file_size" id="file_size" step="0.1" min="0" value="{{ old('file_size') }}"
                           class="form-input text-xs w-full @error('file_size') border-rose-500 @enderror"
                           placeholder="Contoh: 45.5">
                    @error('file_size')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="google_drive_url" class="block text-xs font-semibold text-slate-700 mb-1">Tautan File APK Google Drive <span class="text-rose-500">*</span></label>
                <input type="url" name="google_drive_url" id="google_drive_url" value="{{ old('google_drive_url') }}"
                       class="form-input text-xs w-full @error('google_drive_url') border-rose-500 @enderror"
                       placeholder="https://drive.google.com/file/d/xxx/view?usp=sharing" required>
                @error('google_drive_url')<p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-slate-400 mt-1">Pastikan izin file di Google Drive diatur ke "Siapa saja yang memiliki link dapat melihat/mengunduh".</p>
            </div>

            <div>
                <label for="release_notes" class="block text-xs font-semibold text-slate-700 mb-1">Catatan Rilis (Changelog) <span class="text-[11px] font-normal text-slate-400">(opsional)</span></label>
                <textarea name="release_notes" id="release_notes" rows="2"
                          class="form-textarea text-xs w-full"
                          placeholder="Perbaikan bug pada tracking live, peningkatan stabilitas notifikasi WhatsApp...">{{ old('release_notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end pt-2 border-t border-slate-100">
                <button type="submit" class="btn btn-primary text-xs h-9 px-4 gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[16px]">cloud_upload</span>
                    <span>Publikasikan Versi Ini</span>
                </button>
            </div>
        </form>
    </div>
    @endcanPerm

    <!-- Version History Table -->
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-slate-500">history</span>
                <div>
                    <h2 class="text-xs font-bold text-slate-900">Riwayat Versi Rilis</h2>
                    <span class="text-[11px] text-slate-500">{{ $downloads->count() }} versi tercatat dalam database</span>
                </div>
            </div>
        </div>

        @if($downloads->count() > 0)
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Versi</th>
                        <th>Berkas APK</th>
                        <th>Ukuran</th>
                        <th>Status Rilis</th>
                        <th>Tanggal Rilis</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($downloads as $download)
                    <tr class="{{ $download->is_active ? 'bg-primary-50/30' : '' }}">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded {{ $download->is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }} flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[16px]">android</span>
                                </div>
                                <span class="font-bold font-mono text-slate-900 text-xs">v{{ $download->version }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="text-xs">
                                <p class="font-medium text-slate-800">{{ $download->filename ?: 'citycourier-v' . $download->version . '.apk' }}</p>
                                @if($download->google_drive_url)
                                <a href="{{ $download->google_drive_url }}" target="_blank" rel="noopener" class="text-[11px] text-primary-600 hover:underline inline-flex items-center gap-0.5 mt-0.5">
                                    <span class="material-symbols-outlined text-[12px]">link</span> Buka di Drive
                                </a>
                                @endif
                            </div>
                        </td>
                        <td class="font-mono text-xs text-slate-600 whitespace-nowrap">{{ $download->formatted_size }}</td>
                        <td>
                            @if($download->is_active)
                            <span class="badge badge-success text-[11px]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Rilis Aktif
                            </span>
                            @else
                            <span class="badge badge-neutral text-[11px]">Arsip</span>
                            @endif
                        </td>
                        <td class="text-xs text-slate-500 font-mono whitespace-nowrap">
                            {{ $download->created_at ? $download->created_at->format('d M Y, H:i') : '-' }}
                        </td>
                        <td class="text-right">
                            <div class="inline-flex items-center justify-end gap-1">
                                @if(!$download->is_active)
                                <form action="{{ route('admin.app-download.set-active', $download) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" title="Jadikan Rilis Aktif" class="btn btn-secondary text-xs h-7 px-2 gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-emerald-600">check_circle</span>
                                        <span>Aktifkan</span>
                                    </button>
                                </form>
                                @else
                                <span class="badge badge-success text-[10px] px-2 py-1">
                                    Versi Aktif
                                </span>
                                @endif

                                <form action="{{ route('admin.app-download.destroy', $download) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Hapus versi v{{ $download->version }}? Tindakan ini tidak dapat dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus Versi" class="btn btn-secondary text-xs h-7 w-7 p-0 text-rose-600 hover:bg-rose-50">
                                        <span class="material-symbols-outlined text-[15px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(method_exists($downloads, 'hasPages') && $downloads->hasPages())
        <div class="px-4 py-3 border-t border-slate-100">
            {{ $downloads->links() }}
        </div>
        @endif

        @else
        <div class="p-8 text-center text-slate-400">
            <span class="material-symbols-outlined text-[32px] text-slate-300 mx-auto block mb-1">cloud_off</span>
            <p class="text-xs font-semibold text-slate-600">Belum ada riwayat rilis APK</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Tambahkan versi perdana menggunakan formulir di atas.</p>
        </div>
        @endif
    </div>
</div>
@endsection
