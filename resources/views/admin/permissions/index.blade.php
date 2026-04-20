@extends('layouts.admin')

@section('title', 'Manajemen Permission')
@section('page-title', 'Manajemen Permission')
@section('page-subtitle', 'Kelola Permission')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">Permission</div>
        <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary">Tambah Permission</a>
    </div>
    
    @if(session('success'))
        <div style="padding: 15px; margin-bottom: 20px; border-radius:8px; background-color: rgba(34,197,94,0.2); color: #16a34a; border: 1px solid #16a34a;">
            {{ session('success') }}
        </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($permissions as $permission)
            <tr>
                <td>{{ $permission->id }}</td>
                <td>{{ $permission->name }}</td>
                <td>
                    <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-info" style="color:white;text-decoration:none;">Edit</a>
                    <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin hapus permission ini?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top:20px;">
        {{ $permissions->links() }}
    </div>
</div>
@endsection
