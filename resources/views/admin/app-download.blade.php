@extends('layouts.admin')

@section('title', 'Kelola APK Distribusi')

@section('content')
<div class="max-w-5xl mx-auto flex flex-col gap-6">
    {{-- ═══ Header ═══ --}}
    <div>
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                <span class="material-symbols-outlined text-white text-2xl">android</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Kelola APK Distribusi</h1>
                <p class="text-sm text-slate-500 mt-0.5">Upload dan kelola versi aplikasi CityCourier untuk distribusi</p>
            </div>
        </div>
    </div>

    {{-- ═══ Active Release Banner ═══ --}}
    @if($active)
    <div class="bg-gradient-to-r from-primary to-primary-light rounded-2xl p-6 text-white shadow-lg shadow-primary/20">
        <div class="flex flex-col md:flex-row md:items-center gap-4 justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-3xl">android</span>
                </div>
                <div class="min-w-0">
                    <span class="px-2.5 py-0.5 bg-white/20 text-white rounded-full text-[11px] font-semibold uppercase tracking-wide">Rilis Aktif</span>
                    <h3 class="text-xl font-bold mt-1.5">CityCourier v{{ $active->version }}</h3>
                    <p class="text-sm text-white/80">{{ $active->formatted_size }}
                        @if($active->created_at)
                            &bull; rilis {{ $active->created_at->format('d M Y') }}
                        @endif
                    </p>
                    @if($active->release_notes)
                    <p class="text-xs text-white/70 mt-1 line-clamp-2">{{ $active->release_notes }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('download.app') }}" target="_blank" rel="noopener"
               class="px-6 py-3 bg-white text-primary rounded-xl font-semibold hover:bg-white/90 transition-all flex items-center justify-center gap-2 flex-shrink-0 shadow-md">
                <span class="material-symbols-outlined">download</span>
                Unduh Aplikasi
            </a>
        </div>
    </div>
    @endif

    {{-- ═══ Add New Version Form ═══ --}}
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-6 border-b border-surface-border">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">add_link</span>
                Tambah Versi Baru
            </h2>
            <p class="text-xs text-slate-400 mt-1">Versi baru yang ditambahkan otomatis menjadi rilis aktif.</p>
        </div>
        <div class="p-6">
            <form action="{{ route('admin.app-download.store') }}" method="POST" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="version" class="block text-sm font-semibold text-slate-700 mb-1.5">Versi</label>
                        <input type="text" name="version" id="version" value="{{ old('version', $active->version ?? '1.0.0') }}"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('version') border-red-500 @enderror"
                               placeholder="1.0.0" required>
                        @error('version')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="file_size" class="block text-sm font-semibold text-slate-700 mb-1.5">Ukuran File (MB)</label>
                        <input type="number" name="file_size" id="file_size" step="0.1" min="0"
                               value="{{ old('file_size') }}"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('file_size') border-red-500 @enderror"
                               placeholder="60">
                        @error('file_size')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="google_drive_url" class="block text-sm font-semibold text-slate-700 mb-1.5">Google Drive URL</label>
                    <input type="url" name="google_drive_url" id="google_drive_url" value="{{ old('google_drive_url') }}"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all @error('google_drive_url') border-red-500 @enderror"
                           placeholder="https://drive.google.com/file/d/xxx/view?usp=sharing" required>
                    @error('google_drive_url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-slate-400 mt-1">Upload APK ke Google Drive, setel berbagi ke publik, lalu tempel link di sini.</p>
                </div>

                <div>
                    <label for="release_notes" class="block text-sm font-semibold text-slate-700 mb-1.5">Catatan Rilis <span class="text-xs font-normal text-slate-400">(opsional)</span></label>
                    <textarea name="release_notes" id="release_notes" rows="3"
                              class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"
                              placeholder="Apa yang baru di versi ini...">{{ old('release_notes') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white font-semibold hover:shadow-lg hover:shadow-primary/20 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">add_link</span>
                        Tambahkan Versi
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ Version History Table ═══ --}}
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-6 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">history</span>
                Riwayat Versi
            </h2>
            @if($downloads->count() > 0)
            <span class="px-2.5 py-1 bg-primary/10 text-primary rounded-full text-xs font-semibold">{{ $downloads->count() }} versi</span>
            @endif
        </div>

        @if($downloads->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-400">
                        <th class="px-6 py-3 font-semibold">Versi</th>
                        <th class="px-6 py-3 font-semibold">File</th>
                        <th class="px-6 py-3 font-semibold">Ukuran</th>
                        <th class="px-6 py-3 font-semibold">Status</th>
                        <th class="px-6 py-3 font-semibold">Tanggal</th>
                        <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($downloads as $download)
                    <tr class="{{ $download->is_active ? 'bg-primary/5' : 'hover:bg-slate-50/60' }} transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 {{ $download->is_active ? 'bg-primary' : 'bg-slate-100' }} rounded-lg flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-[16px] {{ $download->is_active ? 'text-white' : 'text-slate-500' }}">android</span>
                                </div>
                                <span class="font-bold text-slate-800">v{{ $download->version }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-slate-700 font-medium">{{ $download->filename ?: 'citycourier-v' . $download->version . '.apk' }}</p>
                            @if($download->google_drive_url)
                            <a href="{{ $download->google_drive_url }}" target="_blank" rel="noopener" class="text-xs text-primary hover:underline inline-flex items-center gap-1 mt-0.5">
                                <span class="material-symbols-outlined text-[12px]">link</span> Google Drive
                            </a>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500 whitespace-nowrap">{{ $download->formatted_size }}</td>
                        <td class="px-6 py-4">
                            @if($download->is_active)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-primary/10 text-primary rounded-full text-xs font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Aktif
                            </span>
                            @else
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-medium">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                            @if($download->created_at)
                                {{ $download->created_at->format('d M Y H:i') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                @if(!$download->is_active)
                                <form action="{{ route('admin.app-download.set-active', $download) }}" method="POST">
                                    @csrf
                                    <button type="submit" title="Set sebagai aktif"
                                            class="p-2 text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
                                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                    </button>
                                </form>
                                @else
                                <span class="p-2 text-slate-200 cursor-not-allowed" title="Versi aktif saat ini">
                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                </span>
                                @endif

                                <form action="{{ route('admin.app-download.destroy', $download) }}" method="POST"
                                      onsubmit="return confirm('Hapus versi v{{ $download->version }}? Tindakan ini tidak dapat dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus"
                                            class="p-2 text-slate-400 hover:text-error hover:bg-error/5 rounded-lg transition-all">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
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
        <div class="px-6 py-4 border-t border-slate-100">
            {{ $downloads->links() }}
        </div>
        @endif

        @else
        <div class="p-14 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-primary/5 flex items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-primary/40">cloud_upload</span>
            </div>
            <p class="text-slate-600 font-semibold mt-4">Belum ada versi APK</p>
            <p class="text-sm text-slate-400 mt-1">Tambahkan versi pertama lewat formulir di atas untuk mulai distribusi.</p>
        </div>
        @endif
    </div>
</div>
@endsection