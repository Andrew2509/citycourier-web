@extends('layouts.admin')

@section('title', 'Tambah Drop Point')

@section('page-title', 'Tambah Drop Point')
@section('page-subtitle', 'Tambahkan lokasi kantor atau agen baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="glass-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-plus-circle"></i>
                    Informasi Drop Point
                </div>
                <a href="{{ route('admin.drop-points.index') }}" class="btn btn-ghost btn-sm">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.drop-points.store') }}" method="POST">
                    @csrf
                    
                    <div class="form-group mb-4">
                        <label for="name" class="form-label">Nama Drop Point</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                               value="{{ old('name') }}" placeholder="Contoh: Kantor Cabang Surabaya" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <label for="address" class="form-label">Alamat Lengkap</label>
                        <textarea name="address" id="address" rows="3" class="form-control @error('address') is-invalid @enderror" 
                                  placeholder="Jl. Raya Utama No. 123..." required>{{ old('address') }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="phone" class="form-label">Nomor Telepon</label>
                                <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" 
                                       value="{{ old('phone') }}" placeholder="021-xxxxxxx">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="schedule" class="form-label">Jam Operasional</label>
                                <input type="text" name="schedule" id="schedule" class="form-control @error('schedule') is-invalid @enderror" 
                                       value="{{ old('schedule', '08:00 - 21:00') }}" placeholder="08:00 - 21:00">
                                @error('schedule')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-4">
                                <label for="rating" class="form-label">Rating (0-5)</label>
                                <input type="number" name="rating" id="rating" class="form-control @error('rating') is-invalid @enderror" 
                                       value="{{ old('rating', '5.0') }}" step="0.1" min="0" max="5">
                                @error('rating')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label for="is_active" class="form-label">Status</label>
                        <select name="is_active" id="is_active" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Non-aktif</option>
                        </select>
                    </div>

                    <div class="nav-section-title mb-3 mt-2">Koordinat Lokasi (Opsional)</div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                       value="{{ old('latitude') }}" placeholder="-6.xxxxxx">
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-4">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                       value="{{ old('longitude') }}" placeholder="106.xxxxxx">
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button type="reset" class="btn btn-ghost">Reset</button>
                        <button type="submit" class="btn btn-primary px-5">Simpan Drop Point</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
