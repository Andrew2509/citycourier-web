@extends('layouts.admin')

@section('title', 'Edit Drop Point')

@section('page-title', 'Edit Drop Point')
@section('page-subtitle', 'Perbarui data lokasi kantor ' . $dropPoint->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-edit"></i>
                    Edit Informasi Drop Point
                </div>
                <a href="{{ route('admin.drop-points.index') }}" class="btn btn-ghost btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.drop-points.update', $dropPoint) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="form-group mb-4">
                        <label for="name" class="form-label">Nama Drop Point</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name', $dropPoint->name) }}" placeholder="Contoh: Kantor Cabang Surabaya" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="address" class="form-label">Alamat Lengkap</label>
                        <textarea name="address" id="address" rows="3" class="form-control @error('address') is-invalid @enderror" 
                                  placeholder="Jl. Raya Utama No. 123..." required>{{ old('address', $dropPoint->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" 
                                       value="{{ old('phone', $dropPoint->phone) }}" placeholder="021-xxxxxxx">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="is_active" class="form-label">Status</label>
                                <select name="is_active" id="is_active" class="form-control">
                                    <option value="1" {{ $dropPoint->is_active ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ !$dropPoint->is_active ? 'selected' : '' }}>Non-aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="nav-section-title mb-3 mt-2">Koordinat Lokasi (Opsional)</div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                       value="{{ old('latitude', $dropPoint->latitude) }}" placeholder="-6.xxxxxx">
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                       value="{{ old('longitude', $dropPoint->longitude) }}" placeholder="106.xxxxxx">
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <form action="{{ route('admin.drop-points.destroy', $dropPoint) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus drop point ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </form>
                        <button type="submit" class="btn btn-primary px-5">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
