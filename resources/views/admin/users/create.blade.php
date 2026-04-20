@extends('layouts.admin')

@section('title', 'Tambah User')
@section('page-title', 'Tambah User')

@section('content')
<div class="glass-card" style="max-width: 600px;">
    <form action="{{ route('admin.users.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div style="margin-bottom: 20px; text-align: center;">
            <label style="display:block;margin-bottom:10px; font-weight: bold;">Foto Profil</label>
            <div style="margin-bottom: 10px;">
                <img id="preview" src="{{ asset('assets/images/default-avatar.png') }}" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color);">
            </div>
            <input type="file" name="avatar" id="avatar" onchange="previewImage()" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
            @error('avatar') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Nama</label>
            <input type="text" name="name" value="{{ old('name') }}" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
            @error('name') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
            @error('email') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Telepon</label>
            <input type="text" name="phone" value="{{ old('phone') }}" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
            @error('phone') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Alamat</label>
            <textarea name="address" rows="3" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">{{ old('address') }}</textarea>
            @error('address') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Password</label>
            <input type="password" name="password" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
            @error('password') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Roles</label>
            <select name="roles[]" multiple style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);">
                @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                @endforeach
            </select>
            <small style="color:var(--text-muted);">Tahan tombol Ctrl (Windows) atau Command (Mac) untuk memilih lebih dari satu.</small>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
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
