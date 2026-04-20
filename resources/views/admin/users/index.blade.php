@extends('layouts.admin')

@section('title', 'Manajemen User')
@section('page-title', 'Manajemen User')
@section('page-subtitle', 'Kelola User dan Assign Role')

@section('content')
<div class="glass-card">
    <div class="card-header">
        <div class="card-title">User</div>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Tambah User</a>
    </div>
    
    @if(session('success'))
        <div style="padding: 15px; margin-bottom: 20px; border-radius:8px; background-color: rgba(34,197,94,0.2); color: #16a34a; border: 1px solid #16a34a;">
            {{ session('success') }}
        </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th>Foto</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Alamat</th>
                <th>Role(s)</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>
                    <img src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('assets/images/default-avatar.png') }}" 
                         alt="Avatar" 
                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
                </td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>{{ $user->phone ?? '-' }}</td>
                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $user->address ?? '-' }}</td>
                <td>
                    @foreach($user->roles as $role)
                        <span class="badge badge-assigned">{{ $role->name }}</span>
                    @endforeach
                </td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-info" style="color:white;text-decoration:none;">Edit</a>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Yakin hapus user ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div style="margin-top:20px;">
        {{ $users->links() }}
    </div>
</div>
@endsection
