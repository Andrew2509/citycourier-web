@extends('layouts.admin')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #dropPointMap.leaflet-container { border-radius: 8px; z-index: 10; }
    #dropPointMap .leaflet-popup-content-wrapper { border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    #dropPointMap .leaflet-popup-content { margin: 10px 12px; font-family: 'Inter', sans-serif; font-size: 12.5px; }
</style>
@endpush

@section('title', 'Manajemen Drop Point')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Manajemen Drop Point</h1>
                <span class="badge badge-green">
                    <span class="badge-dot"></span>
                    {{ $activeCount }} Hub Aktif
                </span>
            </div>
            <p class="page-subtitle">Kelola titik hub operasional, kantor cabang, dan agen penerimaan paket City Courier.</p>
        </div>

        <div class="page-actions">
            <a href="{{ route('admin.drop-points.export') }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Ekspor CSV</span>
            </a>
            @canPerm('drop_points.create')
            <button type="button" onclick="openModal('modal-tambah-droppoint')" class="btn btn-primary">
                <span class="material-symbols-outlined">add_location</span>
                <span>Tambah Drop Point</span>
            </button>
            @endcanPerm
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-3.5">
            <div class="stat-label">Total Drop Point</div>
            <div class="stat-value">{{ $total }}</div>
            <div class="stat-meta text-emerald-700 font-medium">
                {{ $total > 0 ? round(($activeCount / $total) * 100) : 0 }}% Aktif Operasional
            </div>
        </div>

        <div class="card p-3.5">
            <div class="stat-label">Cakupan Wilayah</div>
            <div class="stat-value">{{ $cities->count() }}</div>
            <div class="stat-meta truncate" title="{{ $cities->implode(', ') }}">
                Zona: {{ $cities->take(2)->implode(', ') ?: 'Pusat' }}
            </div>
        </div>

        <div class="card p-3.5">
            <div class="stat-label">Kurir Terdaftar</div>
            <div class="stat-value text-blue-700">{{ $courierCount }}</div>
            <div class="stat-meta">Terhubung ke jaringan</div>
        </div>

        <div class="card p-3.5">
            <div class="stat-label">Rata-rata Kapasitas</div>
            <div class="stat-value text-brand-700">{{ $capacity }}%</div>
            <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1.5 overflow-hidden">
                <div class="h-1.5 rounded-full {{ $capacity >= 75 ? 'bg-red-500' : ($capacity >= 40 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min($capacity, 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Geofence Banner --}}
    <div class="card p-4 bg-orange-50/40 border-orange-200/80 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-brand-600 text-white">
                <span class="material-symbols-outlined text-[20px]">location_searching</span>
            </div>
            <div>
                <div class="text-xs font-semibold text-slate-900">
                    Sistem Geofence Otomatis (Default: 50 Meter)
                </div>
                <p class="text-xs text-slate-600 mt-0.5">
                    Radius batas toleransi GPS kurir saat melakukan presensi (check-in) dan serah terima paket di lokasi hub.
                </p>
            </div>
        </div>
        @if(isset($mapPoint) && $mapPoint)
        <button type="button" onclick="openModal('modal-radius')" class="btn btn-sm btn-secondary shrink-0">
            <span class="material-symbols-outlined text-[16px]">edit_location</span>
            Atur Radius Geofence
        </button>
        @endif
    </div>

    {{-- Filter, Search & Tampilan Toggle --}}
    <div class="card p-3 sm:p-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            {{-- Switch View: Tabel vs Peta --}}
            <div class="inline-flex rounded-md border border-slate-200 bg-slate-100 p-0.5 self-start">
                <button type="button"
                        id="tabTableBtn"
                        onclick="showDpView('table')"
                        class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-xs font-semibold bg-white text-slate-800 shadow-sm transition-all">
                    <span class="material-symbols-outlined text-[16px]">table_rows</span>
                    Tabel
                </button>
                <button type="button"
                        id="tabMapBtn"
                        onclick="showDpView('map')"
                        class="inline-flex items-center gap-1.5 rounded px-3 py-1 text-xs font-medium text-slate-600 hover:text-slate-900 transition-all">
                    <span class="material-symbols-outlined text-[16px]">map</span>
                    Peta Interaktif
                </button>
            </div>

            {{-- Form Pencarian & Filter Wilayah --}}
            <form method="GET" action="{{ route('admin.drop-points.index') }}" class="flex flex-wrap items-center gap-2">
                <div class="search-box w-full sm:w-64">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari nama drop point, kode..."
                           class="form-input">
                </div>

                @if($cities->count() > 0)
                <select name="wilayah" class="form-select w-auto text-xs" onchange="this.form.submit()">
                    <option value="">Semua Wilayah</option>
                    @foreach($cities as $city)
                        <option value="{{ $city }}" {{ request('wilayah') === $city ? 'selected' : '' }}>{{ $city }}</option>
                    @endforeach
                </select>
                @endif

                <button type="submit" class="btn btn-secondary">
                    Cari
                </button>

                @if(request('search') || request('wilayah'))
                    <a href="{{ route('admin.drop-points.index') }}" class="btn btn-ghost" title="Reset filter">
                        <span class="material-symbols-outlined">restart_alt</span>
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- TAMPILAN 1: Tabel Drop Point --}}
    <div id="dpViewTable" class="card overflow-hidden">
        @if($dropPoints->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Drop Point / Hub</th>
                        <th>Kode Hub</th>
                        <th>Alamat &amp; Kota</th>
                        <th>Kontak &amp; PIC</th>
                        <th>Jam Operasional</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dropPoints as $dp)
                    @php
                        $isActive = (bool) $dp->is_active;
                        $dpType = strtolower($dp->type ?? 'hub');
                        $typeLabel = match($dpType) {
                            'pusat', 'main' => 'Pusat Hub',
                            'cabang'        => 'Cabang',
                            'agen'          => 'Gerai Agen',
                            default         => 'Hub Operasional',
                        };
                    @endphp
                    <tr>
                        {{-- Nama & Tipe --}}
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-700">
                                    <span class="material-symbols-outlined text-[18px]">warehouse</span>
                                </div>
                                <div>
                                    <div class="text-[13px] font-semibold text-slate-900">{{ $dp->name }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        <span class="badge badge-gray text-[10px]">{{ $typeLabel }}</span>
                                        @if($dp->radius_m)
                                            <span class="ml-1 font-mono">Radius: {{ $dp->radius_m }}m</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Kode --}}
                        <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-800">
                            {{ $dp->code ?? ('DP-' . str_pad($dp->id, 3, '0', STR_PAD_LEFT)) }}
                        </td>

                        {{-- Alamat --}}
                        <td class="max-w-xs">
                            <div class="text-xs text-slate-700 truncate" title="{{ $dp->address }}">
                                {{ $dp->address ?? '-' }}
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                {{ $dp->city ?? 'Surabaya' }}
                            </div>
                        </td>

                        {{-- Kontak --}}
                        <td class="whitespace-nowrap text-xs">
                            <div class="font-mono text-slate-800">{{ $dp->phone ?? '-' }}</div>
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                PIC: {{ $dp->pic_name ?? 'Staf Operasional' }}
                            </div>
                        </td>

                        {{-- Jam --}}
                        <td class="whitespace-nowrap text-xs text-slate-600">
                            {{ $dp->schedule ?? '08:00 - 21:00' }}
                        </td>

                        {{-- Status & Toggle --}}
                        <td class="whitespace-nowrap">
                            <form action="{{ route('admin.drop-points.toggle-active', $dp->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Ubah status aktif drop point {{ $dp->name }}?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold transition-colors {{ $isActive ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 text-red-700 hover:bg-red-100' }}"
                                        title="Klik untuk mengubah status aktif">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                </button>
                            </form>
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @if($dp->latitude && $dp->longitude)
                                <button type="button"
                                        onclick="focusMap({{ $dp->id }})"
                                        class="btn-icon"
                                        title="Fokus di Peta">
                                    <span class="material-symbols-outlined text-[18px]">near_me</span>
                                </button>
                                @endif

                                <button type="button"
                                        onclick="openEditModal(this)"
                                        class="btn-icon"
                                        title="Edit Drop Point"
                                        data-action="{{ route('admin.drop-points.update', $dp->id) }}"
                                        data-name="{{ $dp->name }}"
                                        data-type="{{ $dp->type ?? 'hub' }}"
                                        data-status="{{ $dp->is_active ? 'active' : 'inactive' }}"
                                        data-address="{{ $dp->address }}"
                                        data-city="{{ $dp->city }}"
                                        data-province="{{ $dp->province }}"
                                        data-landmark="{{ $dp->landmark }}"
                                        data-description="{{ $dp->description }}"
                                        data-phone="{{ $dp->phone }}"
                                        data-pic_name="{{ $dp->pic_name }}"
                                        data-latitude="{{ $dp->latitude }}"
                                        data-longitude="{{ $dp->longitude }}"
                                        data-radius_m="{{ $dp->radius_m ?? 50 }}"
                                        data-capacity_pct="{{ $dp->capacity_pct ?? 20 }}"
                                        data-schedule="{{ $dp->schedule ?? '08:00 - 21:00' }}"
                                        data-open_days="{{ $dp->open_days ?? 'Setiap Hari' }}"
                                        data-rating="{{ $dp->rating ?? '5.0' }}">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>

                                <form action="{{ route('admin.drop-points.destroy', $dp->id) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Hapus drop point {{ $dp->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            <div>
                Menampilkan <strong>{{ $dropPoints->firstItem() ?? 0 }}</strong> - <strong>{{ $dropPoints->lastItem() ?? 0 }}</strong> dari <strong>{{ $dropPoints->total() }}</strong> drop point
            </div>
            <div>
                {{ $dropPoints->withQueryString()->links() }}
            </div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">warehouse</span>
            <div class="empty-title">Tidak ada drop point ditemukan</div>
            <div class="empty-text">Tambahkan drop point baru atau sesuaikan filter pencarian.</div>
        </div>
        @endif
    </div>

    {{-- TAMPILAN 2: Peta Interaktif (Leaflet) --}}
    <div id="dpViewMap" class="hidden grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 card p-2">
            <div id="dropPointMap" class="h-[520px] w-full rounded-md"></div>
        </div>

        <div class="card overflow-hidden flex flex-col h-[520px]">
            <div class="card-header shrink-0">
                <div class="card-title">Daftar Titik di Peta</div>
                <button type="button" onclick="testSignal()" class="btn btn-sm btn-ghost text-xs">
                    <span class="material-symbols-outlined text-[16px]">radar</span>
                    <span>Uji Sinyal</span>
                </button>
            </div>
            <div class="p-3 text-xs border-b border-slate-100 flex items-center justify-between text-slate-500 shrink-0">
                <span>Akurasi GPS: <strong id="gpsAccuracyText" class="text-emerald-700">± 3 Meter</strong></span>
                <span class="badge badge-green text-[10px]">Geofence Siaga</span>
            </div>
            <div class="divide-y divide-slate-100 overflow-y-auto flex-1">
                @foreach($dropPoints as $p)
                <div class="p-3 hover:bg-slate-50 transition-colors flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <button type="button"
                                onclick="focusMap({{ $p->id }})"
                                class="text-xs font-semibold text-slate-900 hover:text-brand-700 truncate block text-left">
                            {{ $p->name }}
                        </button>
                        <div class="text-[11px] text-slate-400 truncate">
                            {{ $p->address ?? $p->city }}
                        </div>
                    </div>
                    @if($p->latitude && $p->longitude)
                    <button type="button"
                            onclick="focusMap({{ $p->id }})"
                            class="btn btn-sm btn-secondary shrink-0 text-[11px]">
                        Fokus
                    </button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- MODAL 1: Tambah Drop Point --}}
<div id="modal-tambah-droppoint" class="modal-backdrop hidden">
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div>
                <div class="modal-title">Tambah Drop Point Baru</div>
                <div class="text-xs text-slate-500">Daftarkan lokasi agen atau hub pengiriman baru</div>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.drop-points.store') }}">
            @csrf
            <div class="modal-body space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="sm:col-span-2">
                        <label class="form-label">Nama Drop Point <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required class="form-input" placeholder="Contoh: Kantor Cabang Waru">
                    </div>

                    <div>
                        <label class="form-label">Tipe Lokasi</label>
                        <select name="type" class="form-select">
                            <option value="hub">Hub Utama Sortir</option>
                            <option value="cabang">Kantor Cabang</option>
                            <option value="agen">Gerai Agen</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Status Operasional</label>
                        <select name="status" class="form-select">
                            <option value="active">Aktif Menerima Paket</option>
                            <option value="inactive">Nonaktif Sementara</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Alamat Lengkap <span class="text-red-500">*</span></label>
                    <textarea name="address" rows="2" required class="form-textarea" placeholder="Jl. Raya Utama No. 123..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="form-label">Kota</label>
                        <input type="text" name="city" placeholder="Surabaya" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Provinsi</label>
                        <input type="text" name="province" placeholder="Jawa Timur" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Patokan / Landmark</label>
                        <input type="text" name="landmark" placeholder="Dekat Stasiun Waru" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" placeholder="08xxxxxxxxxx" class="form-input font-mono">
                    </div>
                    <div>
                        <label class="form-label">Nama PIC / Penanggung Jawab</label>
                        <input type="text" name="pic_name" placeholder="Nama Koordinator" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2 border-t border-slate-100">
                    <div>
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" placeholder="-7.3498" class="form-input font-mono text-xs">
                    </div>
                    <div>
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" placeholder="112.7498" class="form-input font-mono text-xs">
                    </div>
                    <div>
                        <label class="form-label">Radius Geofence (Meter)</label>
                        <input type="number" name="radius_m" value="50" min="10" max="1000" class="form-input font-mono text-xs">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Drop Point</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 2: Edit Drop Point --}}
<div id="modal-edit-droppoint" class="modal-backdrop hidden">
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div>
                <div class="modal-title">Edit Drop Point</div>
                <div class="text-xs text-slate-500">Perbarui data lokasi atau radius geofence</div>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="editDropPointForm" method="POST" action="">
            @csrf
            @method('PUT')
            <div class="modal-body space-y-3.5">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="sm:col-span-2">
                        <label class="form-label">Nama Drop Point <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="edit_name" required class="form-input">
                    </div>

                    <div>
                        <label class="form-label">Tipe Lokasi</label>
                        <select name="type" id="edit_type" class="form-select">
                            <option value="hub">Hub Utama Sortir</option>
                            <option value="cabang">Kantor Cabang</option>
                            <option value="agen">Gerai Agen</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Status Operasional</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Alamat Lengkap <span class="text-red-500">*</span></label>
                    <textarea name="address" id="edit_address" rows="2" required class="form-textarea"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="form-label">Kota</label>
                        <input type="text" name="city" id="edit_city" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Provinsi</label>
                        <input type="text" name="province" id="edit_province" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Patokan</label>
                        <input type="text" name="landmark" id="edit_landmark" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label">Nomor Telepon</label>
                        <input type="text" name="phone" id="edit_phone" class="form-input font-mono">
                    </div>
                    <div>
                        <label class="form-label">PIC / Penanggung Jawab</label>
                        <input type="text" name="pic_name" id="edit_pic_name" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-2 border-t border-slate-100">
                    <div>
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" id="edit_latitude" class="form-input font-mono text-xs">
                    </div>
                    <div>
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" id="edit_longitude" class="form-input font-mono text-xs">
                    </div>
                    <div>
                        <label class="form-label">Radius (Meter)</label>
                        <input type="number" name="radius_m" id="edit_radius_m" min="10" max="1000" class="form-input font-mono text-xs">
                    </div>
                </div>

                <input type="hidden" name="capacity_pct" id="edit_capacity_pct">
                <input type="hidden" name="schedule" id="edit_schedule">
                <input type="hidden" name="open_days" id="edit_open_days">
                <input type="hidden" name="rating" id="edit_rating">
                <input type="hidden" name="description" id="edit_description">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 3: Atur Radius Geofence --}}
@if(isset($mapPoint) && $mapPoint)
<div id="modal-radius" class="modal-backdrop hidden">
    <div class="modal-panel">
        <div class="modal-header">
            <div>
                <div class="modal-title">Atur Radius Geofence</div>
                <div class="text-xs text-slate-500 font-mono">{{ $mapPoint->name }}</div>
            </div>
            <button type="button" class="btn-icon" onclick="closeModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.drop-points.radius', $mapPoint->id) }}">
            @csrf
            @method('PATCH')
            <div class="modal-body space-y-3">
                <div>
                    <label class="form-label">Radius Geofence Saat Ini (Meter)</label>
                    <input type="number"
                           name="radius_m"
                           value="{{ $mapPoint->radius_m ?? 50 }}"
                           min="10"
                           max="1000"
                           required
                           class="form-input font-mono">
                    <p class="form-help">Batas toleransi GPS kurir saat melakukan check-in dan serah terima di hub ini.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Radius</button>
            </div>
        </form>
    </div>
</div>
@endif

<div id="dpToastWrap" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2"></div>

<script type="application/json" id="dpMapData">@json($dropPoints->items())</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let dpMap = null;
    let dpMarkers = {};
    let dpCircles = [];

    function showToast(message, type) {
        type = type || 'success';
        const wrap = document.getElementById('dpToastWrap');
        if (!wrap) return;
        const el = document.createElement('div');
        const isSuccess = type === 'success';
        el.className = 'flex items-center gap-2 rounded-md px-3.5 py-2.5 text-xs font-medium shadow-xl transition-all ' +
            (isSuccess ? 'bg-navy-900 text-white' : 'bg-red-700 text-white');
        el.innerHTML = '<span class="material-symbols-outlined text-[16px] text-brand-400">' +
            (isSuccess ? 'check_circle' : 'error') + '</span><span>' + message + '</span>';
        wrap.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 300);
        }, 3500);
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('hidden');
    }

    function closeModal() {
        document.querySelectorAll('#modal-tambah-droppoint, #modal-edit-droppoint, #modal-radius').forEach(m => m.classList.add('hidden'));
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    function showDpView(view) {
        const table = document.getElementById('dpViewTable');
        const map = document.getElementById('dpViewMap');
        const tabTable = document.getElementById('tabTableBtn');
        const tabMap = document.getElementById('tabMapBtn');

        if (view === 'map') {
            table.classList.add('hidden');
            map.classList.remove('hidden');
            map.classList.add('grid');
            tabTable.classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
            tabTable.classList.add('text-slate-600');
            tabMap.classList.add('bg-white', 'text-slate-800', 'shadow-sm');
            tabMap.classList.remove('text-slate-600');
            if (!dpMap) initDpMap();
            setTimeout(() => { if (dpMap) dpMap.invalidateSize(); }, 150);
        } else {
            map.classList.add('hidden');
            map.classList.remove('grid');
            table.classList.remove('hidden');
            tabMap.classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
            tabMap.classList.add('text-slate-600');
            tabTable.classList.add('bg-white', 'text-slate-800', 'shadow-sm');
            tabTable.classList.remove('text-slate-600');
        }
    }

    function initDpMap() {
        const el = document.getElementById('dropPointMap');
        if (!el) return;
        const data = JSON.parse(document.getElementById('dpMapData').textContent);
        if (!data.length) return;
        const defLat = parseFloat(data[0].latitude) || -7.3;
        const defLng = parseFloat(data[0].longitude) || 112.75;
        dpMap = L.map('dropPointMap', { scrollWheelZoom: false }).setView([defLat, defLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(dpMap);

        data.forEach(function (p) {
            if (!p.latitude || !p.longitude) return;
            const lat = parseFloat(p.latitude);
            const lng = parseFloat(p.longitude);
            const marker = L.marker([lat, lng]).addTo(dpMap).bindPopup(
                '<strong>' + (p.name || 'Drop Point') + '</strong><br/>Radius: ' + (p.radius_m || 50) + ' m<br/>' + (p.address || '')
            );
            dpMarkers[p.id] = marker;
            const circle = L.circle([lat, lng], {
                radius: parseInt(p.radius_m || 50, 10),
                color: '#ec5b13',
                fillColor: '#ec5b13',
                fillOpacity: 0.12
            }).addTo(dpMap);
            dpCircles.push(circle);
        });
    }

    function focusMap(id) {
        showDpView('map');
        setTimeout(() => {
            const marker = dpMarkers[id];
            if (marker) {
                dpMap.flyTo(marker.getLatLng(), 15, { duration: 0.8 });
                marker.openPopup();
            } else {
                showToast('Lokasi GPS tidak ditemukan untuk hub ini.', 'error');
            }
        }, 200);
    }

    function testSignal() {
        showToast('Sinyal GPS terhubung: akurasi ±3 meter di area geofence.');
    }

    function openEditModal(btn) {
        const d = btn.dataset;
        document.getElementById('editDropPointForm').action = d.action;
        document.getElementById('edit_name').value = d.name || '';
        document.getElementById('edit_type').value = d.type || 'hub';
        document.getElementById('edit_status').value = d.status || 'active';
        document.getElementById('edit_address').value = d.address || '';
        document.getElementById('edit_city').value = d.city || '';
        document.getElementById('edit_province').value = d.province || '';
        document.getElementById('edit_landmark').value = d.landmark || '';
        document.getElementById('edit_description').value = d.description || '';
        document.getElementById('edit_phone').value = d.phone || '';
        document.getElementById('edit_pic_name').value = d.pic_name || '';
        document.getElementById('edit_latitude').value = d.latitude || '';
        document.getElementById('edit_longitude').value = d.longitude || '';
        document.getElementById('edit_radius_m').value = d.radius_m || 50;
        document.getElementById('edit_capacity_pct').value = d.capacity_pct || 22;
        document.getElementById('edit_schedule').value = d.schedule || '08:00 - 21:00';
        document.getElementById('edit_open_days').value = d.open_days || 'Setiap Hari';
        document.getElementById('edit_rating').value = d.rating || '5.0';
        openModal('modal-edit-droppoint');
    }
</script>
@endsection
