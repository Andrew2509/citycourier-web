@extends('layouts.admin')

@section('title', 'Edit Pengguna')

@section('content')
<div class="flex flex-col gap-space-xl">
    {{-- Page Header --}}
    <div class="flex items-center gap-space-md">
        <a href="{{ route('admin.users.index') }}" class="w-9 h-9 rounded-lg bg-surface-container-lowest border border-surface-container-high flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors shadow-sm">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div class="flex items-center gap-space-md">
            <div class="w-10 h-10 rounded-xl bg-primary-container/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[22px] text-primary">person_edit</span>
            </div>
            <div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface font-bold tracking-tight">Edit Pengguna</h1>
                <p class="font-body-sm text-body-sm text-secondary mt-space-2xs">Perbarui informasi akun {{ $user->name }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <div class="bg-surface-container-lowest rounded-xl shadow-sm overflow-hidden">
        <div class="px-space-xl py-space-md border-b border-surface-container-high">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-semibold flex items-center gap-space-xs">
                <span class="material-symbols-outlined text-primary">person</span>
                Form Edit Pengguna
            </h2>
        </div>
        <div class="p-space-xl">
            <form action="{{ route('admin.users.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-space-lg lg:grid-cols-2">
                    {{-- Name --}}
                    <div>
                        <label for="name" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('name') border-red-500 @enderror"
                            placeholder="Masukkan nama lengkap">
                        @error('name')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('email') border-red-500 @enderror"
                            placeholder="contoh@email.com">
                        @error('email')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label for="phone" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Telepon <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}" required
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('phone') border-red-500 @enderror"
                            placeholder="08xxxxxxxxxx">
                        @error('phone')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Role --}}
                    <div>
                        <label for="role_id" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select name="role_id" id="role_id" required
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('role_id') border-red-500 @enderror">
                            <option value="" disabled>Pilih Role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Address --}}
                    <div class="lg:col-span-2">
                        <label for="address" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Alamat <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="address" id="address" value="{{ old('address', $user->address) }}" required
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('address') border-red-500 @enderror"
                            placeholder="Masukkan alamat lengkap">
                        @error('address')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Password
                        </label>
                        <input type="password" name="password" id="password"
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all @error('password') border-red-500 @enderror"
                            placeholder="Kosongkan jika tidak ingin mengubah">
                        @error('password')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Confirm Password --}}
                    <div>
                        <label for="password_confirmation" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">
                            Konfirmasi Password
                        </label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                            class="w-full px-space-md py-space-xs rounded-lg border border-surface-container-high bg-surface text-on-surface text-sm focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all"
                            placeholder="Ulangi password">
                    </div>

                    {{-- Avatar --}}
                    <div class="lg:col-span-2">
                        <label for="avatar" class="block font-label-md text-label-md text-on-surface font-semibold mb-space-xs">Avatar</label>
                        <div class="flex items-center gap-space-md">
                            <div id="avatar-preview" class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-surface-container-high bg-surface">
                                @if ($user->avatar)
                                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover">
                                @else
                                    <span class="material-symbols-outlined text-[28px] text-secondary">person</span>
                                @endif
                            </div>
                            <div>
                                <input type="file" name="avatar" id="avatar" accept="image/*"
                                    class="block w-full text-sm text-on-surface-variant file:mr-4 file:rounded-lg file:border-0 file:bg-primary-container/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-container/20">
                                <p class="font-label-sm text-label-sm text-secondary mt-space-xs">Format: JPG, PNG, WEBP. Maks 2MB. Kosongkan jika tidak ingin mengubah.</p>
                            </div>
                        </div>
                        @error('avatar')
                            <p class="text-xs text-red-500 mt-space-2xs">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-space-lg flex items-center gap-space-md pt-space-md border-t border-surface-container-high">
                    <button type="submit" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-primary-container hover:bg-primary text-on-primary font-label-md text-label-md font-bold shadow-sm transition-all">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Perbarui Pengguna
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-space-xs px-space-lg py-space-xs rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container font-label-md text-label-md font-semibold transition-all">
                        Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
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
