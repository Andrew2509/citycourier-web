@extends('layouts.admin')

@section('title', 'Edit Role')
@section('page-title', 'Edit Role')

@section('content')
<div class="glass-card" style="max-width: 600px;">
    <form action="{{ route('admin.roles.update', $role) }}" method="POST">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Nama Role</label>
            <input type="text" name="name" value="{{ old('name', $role->name) }}" class="" style="width:100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary);" required>
            @error('name') <div style="color:red; margin-top:5px;">{{ $message }}</div> @enderror
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block;margin-bottom:5px;">Permissions</label>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                @foreach($permissions as $permission)
                    <label>
                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>
                        {{ $permission->name }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
