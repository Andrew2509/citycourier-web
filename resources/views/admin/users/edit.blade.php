@extends('layouts.admin')

@section('title', 'Edit Pengguna')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div>
        <a href="{{ route('admin.users.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-gray-500 transition hover:text-[#059669]">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Daftar User
        </a>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#059669]/10">
                <span class="material-symbols-outlined text-[26px] text-[#059669]">person_edit</span>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Pengguna</h1>
                <p class="text-sm text-gray-500">Perbarui informasi akun {{ $user->name }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" enctype="multipart/form-data" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Name --}}
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="Masukkan nama lengkap">
                @error('name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="contoh@email.com">
                @error('email')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Phone --}}
            <div>
                <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Telepon <span class="text-red-500">*</span>
                </label>
                <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" required
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="08xxxxxxxxxx">
                @error('phone')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Role --}}
            <div>
                <label for="role_id" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Role <span class="text-red-500">*</span>
                </label>
                <select name="role_id" id="role_id" required
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]">
                    <option value="" disabled>Pilih Role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                            {{ ucfirst($role->name) }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Address --}}
            <div class="lg:col-span-2">
                <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Alamat <span class="text-red-500">*</span>
                </label>
                <input type="text" name="address" id="address" value="{{ old('address', $user->address) }}" required
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="Masukkan alamat lengkap">
                @error('address')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Password
                </label>
                <input type="password" name="password" id="password"
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="Kosongkan jika tidak ingin mengubah">
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">
                    Konfirmasi Password
                </label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm transition placeholder:text-gray-400 focus:border-[#059669] focus:outline-none focus:ring-1 focus:ring-[#059669]"
                    placeholder="Ulangi password">
            </div>

            {{-- Avatar --}}
            <div class="lg:col-span-2">
                <label for="avatar" class="mb-1.5 block text-sm font-medium text-gray-700">Avatar</label>
                <div class="flex items-center gap-4">
                    <div id="avatar-preview" class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-gray-200 bg-gray-50">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover">
                        @else
                            <span class="material-symbols-outlined text-[28px] text-gray-300">person</span>
                        @endif
                    </div>
                    <div>
                        <input type="file" name="avatar" id="avatar" accept="image/*"
                            class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#059669]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#059669] hover:file:bg-[#059669]/20">
                        <p class="mt-1 text-xs text-gray-400">Format: JPG, PNG, WEBP. Maks 2MB. Kosongkan jika tidak ingin mengubah.</p>
                    </div>
                </div>
                @error('avatar')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-6">
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[#059669] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#047857] focus:outline-none focus:ring-2 focus:ring-[#059669] focus:ring-offset-2">
                <span class="material-symbols-outlined text-[18px]">save</span>
                Perbarui Pengguna
            </button>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                Batal
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.getElementById('avatar').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function (ev) {
                const preview = document.getElementById('avatar-preview');
                preview.innerHTML = `<img src="${ev.target.result}" class="h-16 w-16 rounded-full object-cover">`;
            };
            reader.readAsDataURL(file);
        }
    });
</script>
@endpush
@endsection
