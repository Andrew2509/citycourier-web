@extends('layouts.admin')

@section('title', 'Manajemen APK')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen APK</h1>
            <p class="text-sm text-slate-500">Upload dan kelola aplikasi CityCourier untuk diunduh</p>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="bg-white rounded-2xl border border-surface-border p-6 mb-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">upload</span>
            Upload APK Baru
        </h2>
        
        <form action="{{ route('admin.app-download.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Version -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Versi</label>
                    <input type="text" name="version" value="{{ $active->version ?? '1.0.0' }}" 
                           class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                           placeholder="1.0.0" required>
                </div>
                
                <!-- APK File -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">File APK</label>
                    <input type="file" name="apk_file" accept=".apk"
                           class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                           required>
                </div>
            </div>
            
            <!-- Google Drive URL -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Google Drive URL (opsional - untuk download lebih cepat)</label>
                <input type="url" name="google_drive_url" 
                       class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                       placeholder="https://drive.google.com/file/d/xxx/view?usp=sharing">
                <p class="text-xs text-slate-400 mt-1">Upload APK ke Google Drive, lalu paste link share di sini</p>
            </div>
            
            <!-- Release Notes -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Rilis (opsional)</label>
                <textarea name="release_notes" rows="3"
                          class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                          placeholder="Apa yang baru di versi ini..."></textarea>
            </div>
            
            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-primary to-primary-light text-white rounded-xl font-medium hover:shadow-lg hover:shadow-primary/30 transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    Upload APK
                </button>
                <span class="text-sm text-slate-400">Maksimal 200MB</span>
            </div>
        </form>
    </div>

    <!-- Active Download Info -->
    @if($active)
    <div class="bg-gradient-to-r from-primary to-primary-light rounded-2xl p-6 text-white mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-3xl">android</span>
                </div>
                <div>
                    <h3 class="text-xl font-bold">Download Aktif</h3>
                    <p class="text-white/80">Versi {{ $active->version }} • {{ $active->formatted_size }}</p>
                </div>
            </div>
            <a href="{{ route('download.app') }}" class="px-6 py-3 bg-white text-primary rounded-xl font-semibold hover:bg-white/90 transition-all flex items-center gap-2">
                <span class="material-symbols-outlined">download</span>
                Download Sekarang
            </a>
        </div>
    </div>
    @endif

    <!-- Version History -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-6 border-b border-surface-border">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">history</span>
                Riwayat Versi
            </h2>
        </div>
        
        @if($downloads->count() > 0)
        <div class="divide-y divide-surface-border">
            @foreach($downloads as $download)
            <div class="p-4 {{ $download->is_active ? 'bg-primary/5' : '' }} flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 {{ $download->is_active ? 'bg-primary' : 'bg-slate-100' }} rounded-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-lg {{ $download->is_active ? 'text-white' : 'text-slate-500' }}">android</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-slate-800">v{{ $download->version }}</p>
                            @if($download->is_active)
                                <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-medium rounded-full">Aktif</span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500">{{ $download->original_filename }} • {{ $download->formatted_size }} • {{ $download->created_at->format('d M Y H:i') }}</p>
                        @if($download->release_notes)
                            <p class="text-sm text-slate-600 mt-1">{{ $download->release_notes }}</p>
                        @endif
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    @if(!$download->is_active)
                    <form action="{{ route('admin.app-download.set-active', $download) }}" method="POST">
                        @csrf
                        <button type="submit" class="p-2 text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all" title="Aktifkan">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        </button>
                    </form>
                    @endif
                    
                    <form action="{{ route('admin.app-download.destroy', $download) }}" method="POST" onsubmit="return confirm('Hapus versi ini?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-slate-400 hover:text-error hover:bg-error/5 rounded-lg transition-all" title="Hapus">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="p-12 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300">cloud_upload</span>
            <p class="text-slate-500 mt-4">Belum ada APK yang diupload</p>
        </div>
        @endif
    </div>
</div>
@endsection
