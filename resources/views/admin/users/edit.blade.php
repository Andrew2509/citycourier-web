@extends('layouts.admin')

@section('title', 'Edit Pengguna - ' . $user->name)

@section('content')
<div class="space-y-5 max-w-3xl">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.users.index') }}" class="back-link" title="Kembali ke Daftar Pengguna">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            </a>
            <div>
                <h1 class="page-title">Edit Pengguna</h1>
                <p class="page-subtitle font-mono">ID: #{{ $user->id }} &bull; {{ $user->name }} ({{ $user->email }})</p>
            </div>
        </div>
    </div>

    {{-- Form Edit Pengguna --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Perbarui Informasi Akun</div>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.users.update', $user) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="form-label">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $user->name) }}"
                               required
                               class="form-input @error('name') form-invalid @enderror">
                        @error('name') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="email" class="form-label">
                            Alamat Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email', $user->email) }}"
                               required
                               class="form-input font-mono text-xs @error('email') form-invalid @enderror">
                        @error('email') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="form-label">
                            Nomor Telepon / WhatsApp <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="phone"
                               id="phone"
                               value="{{ old('phone', $user->phone) }}"
                               required
                               class="form-input font-mono @error('phone') form-invalid @enderror">
                        @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="role_id" class="form-label">
                            Role / Jabatan Sistem <span class="text-red-500">*</span>
                        </label>
                        <select name="role_id" id="role_id" required class="form-select @error('role_id') form-invalid @enderror">
                            <option value="" disabled>Pilih Role...</option>
                            @php
                                $currentUserRoleId = $user->roles->first()?->id;
                            @endphp
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $currentUserRoleId) == $role->id ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="address" class="form-label">
                        Alamat Lengkap <span class="text-red-500">*</span>
                    </label>
                    <textarea name="address"
                              id="address"
                              rows="2"
                              required
                              class="form-textarea @error('address') form-invalid @enderror">{{ old('address', $user->address) }}</textarea>
                    @error('address') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                    <div>
                        <label for="password" class="form-label">
                            Ubah Password Baru (Opsional)
                        </label>
                        <input type="password"
                               name="password"
                               id="password"
                               placeholder="Kosongkan jika tidak diubah"
                               class="form-input @error('password') form-invalid @enderror">
                        <p class="form-help">Minimal 8 karakter jika ingin mengganti.</p>
                        @error('password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="form-label">
                            Konfirmasi Password Baru
                        </label>
                        <input type="password"
                               name="password_confirmation"
                               id="password_confirmation"
                               placeholder="Ulangi password baru"
                               class="form-input">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label for="avatar" class="form-label">Ubah Foto Profil / Avatar</label>
                    <div class="flex items-center gap-3">
                        <div class="avatar bg-brand-50 text-brand-700 font-bold shrink-0">
                            @if($user->avatar)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar) }}"
                                     alt="{{ $user->name }}"
                                     class="h-full w-full object-cover rounded-full">
                            @else
                                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                        <input type="file"
                               name="avatar"
                               id="avatar"
                               accept="image/*"
                               class="form-input py-1 text-xs">
                    </div>
                    <p class="form-help">Format: JPG, PNG, WEBP. Maksimal 2MB.</p>
                </div>

                <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
