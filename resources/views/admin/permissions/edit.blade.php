@extends('layouts.admin')

@section('title', 'Edit Permission')
@section('page-title', 'Edit Permission')

@section('content')
<div class="glass-card" style="max-width: 600px;">
    <form action="{{ route('admin.permissions.update', $permission) }}" method="POST">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Nama Permission</label>
            <input type="text" name="name" value="{{ old('name', $permission->name) }}" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
            @error('name') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.permissions.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
