@extends('layouts.admin')

@section('title', 'Kelola APK Distribusi')

@section('content')
<div class="flex flex-col w-full gap-space-xl">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-space-md">
        <div class="flex flex-col gap-space-2xs">
            <div class="flex items-center gap-space-xs text-primary font-label-sm uppercase tracking-wider font-bold">
                <span class="material-symbols-outlined text-[16px]">install_mobile</span>
                <span>Unduh Aplikasi</span>
            </div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Kelola APK Distribusi</h1>
            <p class="font-body-md text-body-md text-secondary">Upload dan kelola versi aplikasi CityCourier untuk distribusi</p>
        </div>
    </div>

    <!-- Active Release Banner -->
    @if($active)
    <div class="bg-gradient-to-r from-primary-container to-primary rounded-xl p-space-xl text-on-primary shadow-lg">
        <div class="flex flex-col md:flex-row md:items-center gap-space-md justify-between">
            <div class="flex items-center gap-space-md">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-[32px]">android</span>
                </div>
                <div class="min-w-0">
                    <span class="px-space-xs py-space-2xs bg-white/20 text-on-primary rounded-lg font-label-sm text-label-sm font-semibold uppercase tracking-wide">Rilis Aktif</span>
                    <h3 class="font-headline-lg text-headline-lg text-on-primary font-bold mt-space-xs">CityCourier v{{ $active->version }}</h3>
                    <p class="font-body-sm text-body-sm text-on-primary/80">{{ $active->formatted_size }}
                        @if($active->created_at)
                            &bull; rilis {{ $active->created_at->format('d M Y') }}
                        @endif
                    </p>
                    @if($active->release_notes)
                    <p class="text-xs text-on-primary/70 mt-space-2xs line-clamp-2">{{ $active->release_notes }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('download.app') }}" target="_blank" rel="noopener"
               class="px-space-lg py-space-sm bg-on-primary text-primary rounded-lg font-semibold hover:bg-white/90 transition-all flex items-center justify-center gap-space-xs flex-shrink-0 shadow-md font-label-md">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Unduh Aplikasi
            </a>
        </div>
    </div>
    @endif

    <!-- Add New Version Form -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="px-space-xl py-space-md border-b border-surface-container-high">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-[20px] text-primary">add_link</span>
                Tambah Versi Baru
            </h2>
            <p class="font-label-sm text-label-sm text-secondary mt-space-2xs">Versi baru yang ditambahkan otomatis menjadi rilis aktif.</p>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.app-download.store') }}" method="POST" class="space-y-4">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="version" class="block font-label-md text-label-md font-semibold text-on-surface mb-1.5">Versi</label>
                        <input type="text" name="version" id="version" value="{{ old('version', $active->version ?? '1.0.0') }}"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('version') border-red-500 @enderror"
                               placeholder="1.0.0" required>
                        @error('version')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="file_size" class="block font-label-md text-label-md font-semibold text-on-surface mb-1.5">Ukuran File (MB)</label>
                        <input type="number" name="file_size" id="file_size" step="0.1" min="0"
                               value="{{ old('file_size') }}"
                               class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('file_size') border-red-500 @enderror"
                               placeholder="60">
                        @error('file_size')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="google_drive_url" class="block font-label-md text-label-md font-semibold text-on-surface mb-1.5">Google Drive URL</label>
                    <input type="url" name="google_drive_url" id="google_drive_url" value="{{ old('google_drive_url') }}"
                           class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('google_drive_url') border-red-500 @enderror"
                           placeholder="https://drive.google.com/file/d/xxx/view?usp=sharing" required>
                    @error('google_drive_url')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                    <p class="text-xs text-secondary mt-1">Upload APK ke Google Drive, setel berbagi ke publik, lalu tempel link di sini.</p>
                </div>

                <div>
                    <label for="release_notes" class="block font-label-md text-label-md font-semibold text-on-surface mb-1.5">Catatan Rilis <span class="text-xs font-normal text-secondary">(opsional)</span></label>
                    <textarea name="release_notes" id="release_notes" rows="3"
                              class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all resize-none"
                              placeholder="Apa yang baru di versi ini...">{{ old('release_notes') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-semibold transition-all flex items-center gap-space-xs font-label-md shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">add_link</span>
                        Tambahkan Versi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Version History Table -->
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="px-space-xl py-space-md border-b border-surface-container-high flex items-center justify-between">
            <div class="flex items-center gap-space-md">
                <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-primary font-bold">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                </div>
                <div class="flex flex-col">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Riwayat Versi</h2>
                    @if($downloads->count() > 0)
                    <span class="font-label-sm text-label-sm text-secondary">{{ $downloads->count() }} versi</span>
                    @endif
                </div>
            </div>
        </div>

        @if($downloads->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-secondary">
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Versi</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">File</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Ukuran</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Status</th>
                        <th class="py-3.5 px-4 font-bold whitespace-nowrap">Tanggal</th>
                        <th class="py-3.5 px-4 font-bold text-right whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="font-body-sm text-body-sm text-on-surface divide-y divide-surface-container-high/60">
                    @foreach($downloads as $download)
                    <tr class="hover:bg-surface transition-colors group {{ $download->is_active ? 'bg-primary-fixed/20' : '' }}">
                        <td class="py-4 px-4 whitespace-nowrap">
                            <div class="flex items-center gap-space-sm">
                                <div class="w-9 h-9 {{ $download->is_active ? 'bg-primary-container text-on-primary' : 'bg-surface-container-high text-secondary' }} rounded-lg flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-[16px]">android</span>
                                </div>
                                <span class="font-bold text-on-surface font-data-mono">v{{ $download->version }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-on-surface font-medium">{{ $download->filename ?: 'citycourier-v' . $download->version . '.apk' }}</p>
                            @if($download->google_drive_url)
                            <a href="{{ $download->google_drive_url }}" target="_blank" rel="noopener" class="text-xs text-primary hover:underline inline-flex items-center gap-1 mt-space-2xs">
                                <span class="material-symbols-outlined text-[12px]">link</span> Google Drive
                            </a>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-secondary whitespace-nowrap">{{ $download->formatted_size }}</td>
                        <td class="py-4 px-4 whitespace-nowrap">
                            @if($download->is_active)
                            <span class="inline-flex items-center gap-space-2xs px-space-sm py-space-2xs bg-emerald-50 text-emerald-700 rounded-full font-label-sm text-xs font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Aktif
                            </span>
                            @else
                            <span class="px-space-sm py-space-2xs bg-surface-container text-secondary rounded-full font-label-sm text-xs font-medium">Nonaktif</span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-secondary whitespace-nowrap">
                            @if($download->created_at)
                                {{ $download->created_at->format('d M Y H:i') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="py-4 px-4 whitespace-nowrap text-right">
                            <div class="inline-flex items-center justify-end gap-space-xs">
                                @if(!$download->is_active)
                                <form action="{{ route('admin.app-download.set-active', $download) }}" method="POST" class="inline-block">
                                    @csrf
                                    <button type="submit" title="Set sebagai aktif" class="p-1.5 rounded-lg text-secondary hover:text-primary hover:bg-surface-container-low transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                    </button>
                                </form>
                                @else
                                <span class="p-1.5 text-primary cursor-not-allowed" title="Versi aktif saat ini">
                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                </span>
                                @endif

                                <form action="{{ route('admin.app-download.destroy', $download) }}" method="POST" class="inline-block"
                                      onsubmit="return confirm('Hapus versi v{{ $download->version }}? Tindakan ini tidak dapat dibatalkan.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus" class="p-1.5 rounded-lg text-secondary hover:text-error hover:bg-error-container/20 transition-colors">
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
        <div class="px-space-xl py-3.5 border-t border-surface-container-high">
            {{ $downloads->links() }}
        </div>
        @endif

        @else
        <div class="py-space-2xl px-space-md text-center">
            <div class="w-16 h-16 mx-auto rounded-xl bg-surface-container-high flex items-center justify-center">
                <span class="material-symbols-outlined text-[32px] text-secondary">cloud_upload</span>
            </div>
            <p class="text-on-surface font-semibold mt-space-md">Belum ada versi APK</p>
            <p class="font-body-sm text-body-sm text-secondary mt-space-xs">Tambahkan versi pertama lewat formulir di atas untuk mulai distribusi.</p>
        </div>
        @endif
    </div>
</div>
@endsection
