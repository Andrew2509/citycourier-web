@extends('layouts.admin')

@section('title', 'Manajemen Role')
@section('page-title', 'Manajemen Role')
@section('page-subtitle', 'Kelola Role')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">Role</div>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">Tambah Role</a>
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
            @foreach($roles as $role)
            <tr>
                <td>{{ $role->id }}</td>
                <td>{{ $role->name }}</td>
                <td>
                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-info" style="color:white;text-decoration:none;">Edit</a>
                    <form action="{{ route('admin.roles.destroy', $role) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin hapus role ini?');">
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
        {{ $roles->links() }}
    </div>
</div>
@endsection
