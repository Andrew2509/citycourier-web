@extends('layouts.admin')

@section('title', 'Tambah User')
@section('page-title', 'Tambah User')

@section('content')
<div class="flex flex-col gap-6 max-w-2xl">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.users.index') }}" class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-light flex items-center justify-center shadow-lg shadow-primary/20">
                    <span class="material-symbols-outlined text-white text-xl">person_add</span>
                </div>
                Tambah User
            </h1>
            <p class="text-sm text-slate-500 mt-1">Buat akun pengguna baru</p>
        </div>
    </div>

    {{-- Form Card --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            {{-- Avatar Section --}}
            <div class="p-6 border-b border-slate-100 text-center">
                <label class="block text-sm font-semibold text-slate-700 mb-3">Foto Profil</label>
                <div class="flex flex-col items-center gap-4">
                    <div class="relative">
                        <img id="preview" src="{{ asset('assets/images/default-avatar.png') }}" class="w-24 h-24 rounded-full object-cover border-4 border-primary/20 shadow-lg">
                        <label for="avatar" class="absolute bottom-0 right-0 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center cursor-pointer hover:bg-primary/90 transition-colors shadow-lg">
                            <span class="material-symbols-outlined text-sm">photo_camera</span>
                        </label>
                    </div>
                    <input type="file" name="avatar" id="avatar" class="hidden" onchange="previewImage()">
                    @error('avatar') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Form Fields --}}
            <div class="p-6 space-y-5">
                {{-- Name --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Lengkap</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="Masukkan nama lengkap" required>
                    @error('name') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="contoh@email.com" required>
                    @error('email') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="08xxxxxxxxxx">
                    @error('phone') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Address --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Alamat</label>
                    <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none" placeholder="Masukkan alamat lengkap">{{ old('address') }}</textarea>
                    @error('address') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                        <input type="password" name="password" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="••••••••" required>
                        @error('password') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" placeholder="••••••••" required>
                    </div>
                </div>

                {{-- Roles --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Roles</label>
                    <select name="roles[]" multiple class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all">
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Tahan Ctrl (Windows) atau Command (Mac) untuk memilih lebih dari satu</p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="p-6 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors text-sm font-medium">
                    Batal
                </a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary to-primary-light text-white hover:shadow-lg hover:shadow-primary/30 transition-all text-sm font-semibold">
                    Simpan User
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewImage() {
        const file = document.querySelector('#avatar').files[0];
        const preview = document.querySelector('#preview');
        const reader = new FileReader();

        reader.onloadend = function() {
            preview.src = reader.result;
        }

        if (file) {
            reader.readAsDataURL(file);
        } else {
            preview.src = "{{ asset('assets/images/default-avatar.png') }}";
        }
    }
</script>
@endsection
