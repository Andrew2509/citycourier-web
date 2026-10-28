@extends('layouts.admin')

@section('title', 'Manajemen & Verifikasi Kurir')

@section('content')
@php
    $all = $couriers->getCollection();
    $verified = $all->where('is_verified', true)->values();
    $pending  = $all->where('is_verified', false)->values();

    $fleetTypes = $all->groupBy(fn ($c) => strtolower(trim($c->vehicle_type ?? 'lainnya')))->map->count();
    $fleetTypeLabels = [
        'motor' => 'Sepeda Motor',
        'mobil' => 'Mobil',
        'sepeda' => 'Sepeda',
    ];

    $courierData = [];
    foreach ($all as $c) {
        $phoneRaw = preg_replace('/[^0-9]/', '', $c->phone ?? '');
        $wa = (str_starts_with($phoneRaw, '0')) ? '62' . substr($phoneRaw, 1)
            : ((str_starts_with($phoneRaw, '62')) ? $phoneRaw : '62' . $phoneRaw);
        $nik = $c->nik ? substr($c->nik, 0, 4) . '**********' . substr($c->nik, -4) : 'Belum dilengkapi';
        $vehicleLabel = $fleetTypeLabels[strtolower($c->vehicle_type ?? '')] ?? ucfirst($c->vehicle_type ?? 'Kendaraan');
        $vehicleIcon = match (strtolower($c->vehicle_type ?? '')) {
            'motor' => 'two_wheeler',
            'mobil' => 'directions_car',
            'sepeda' => 'pedal_bike',
            default => 'commute',
        };

        $courierData[$c->id] = [
            'name' => $c->user?->name ?? 'Kurir',
            'email' => $c->user?->email ?? '-',
            'phone' => $c->phone ?? '-',
            'wa' => $wa,
            'idLabel' => 'CC-KRR-' . ($c->created_at?->format('Y') ?? date('Y')) . '-' . str_pad($c->id, 3, '0', STR_PAD_LEFT),
            'nik' => $nik,
            'nikRaw' => $c->nik ?? '',
            'address' => $c->address ?? '',
            'city' => $c->city ?? '',
            'joined' => $c->created_at?->format('d M Y') ?? '-',
            'vehicleType' => $c->vehicle_type ?? '',
            'vehicleBrand' => $c->vehicle_brand ?? '',
            'vehicleYear' => $c->vehicle_year ?? '',
            'vehicleLabel' => $vehicleLabel,
            'vehicleIcon' => $vehicleIcon,
            'plate' => $c->vehicle_plate ?? '-',
            'verified' => (bool) $c->is_verified,
            'active' => (bool) $c->is_active,
            'hasKtp' => (bool) $c->id_card_photo,
            'hasSim' => (bool) $c->driving_license_photo,
            'hasVehicle' => (bool) ($c->skck_photo || $c->photo),
            'photoUrl' => $c->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->photo) : null,
            'ktpUrl' => $c->id_card_photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->id_card_photo) : null,
            'simUrl' => $c->driving_license_photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->driving_license_photo) : null,
            'skckUrl' => $c->skck_photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($c->skck_photo) : null,
            'recapCount' => $c->orders ? $c->orders->count() : 0,
            'recapTotal' => $c->orders ? $c->orders->sum('price') : 0,
        ];
    }
@endphp

<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="page-title">Manajemen &amp; Verifikasi Kurir</h1>
                @if(count($pending) > 0)
                <span class="badge badge-amber">
                    <span class="badge-dot"></span>
                    {{ count($pending) }} Menunggu Persetujuan
                </span>
                @endif
            </div>
            <p class="page-subtitle">Kelola armada kurir, validasi kelengkapan dokumen KTP/SIM/SKCK, dan pantau status keaktifan.</p>
        </div>

        <div class="page-actions">
            <button type="button" onclick="shareRegistrationLink()" class="btn btn-secondary" title="Salin tautan pendaftaran kurir baru">
                <span class="material-symbols-outlined">share</span>
                <span>Bagikan Link Daftar</span>
            </button>
            <a href="{{ route('admin.couriers.export') }}" class="btn btn-secondary">
                <span class="material-symbols-outlined">download</span>
                <span>Ekspor CSV</span>
            </a>
            <button type="button" onclick="openAddCourierModal()" class="btn btn-primary">
                <span class="material-symbols-outlined">person_add</span>
                <span>Tambah Kurir</span>
            </button>
        </div>
    </div>

    {{-- Ringkasan Armada Kurir --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="card p-3.5">
            <div class="stat-label">Total Armada</div>
            <div class="stat-value">{{ $all->count() }}</div>
            <div class="stat-meta">Semua kurir terdaftar</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Menunggu Verifikasi</div>
            <div class="stat-value text-amber-600">{{ count($pending) }}</div>
            <div class="stat-meta">Perlu review berkas</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Terverifikasi Aktif</div>
            <div class="stat-value text-emerald-600">{{ $all->where('is_verified', true)->where('is_active', true)->count() }}</div>
            <div class="stat-meta">Siap menerima order</div>
        </div>
        <div class="card p-3.5">
            <div class="stat-label">Nonaktif / Suspend</div>
            <div class="stat-value text-slate-500">{{ $all->where('is_active', false)->count() }}</div>
            <div class="stat-meta">Akses dinonaktifkan</div>
        </div>
    </div>

    {{-- Bagian 1: Kurir Menunggu Verifikasi --}}
    <div id="pendingSection" class="card overflow-hidden {{ count($pending) === 0 ? 'hidden' : '' }}">
        <div class="card-header bg-amber-50/50 border-amber-200/80">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-700">how_to_reg</span>
                <div>
                    <div class="card-title text-amber-900">Kurir Menunggu Verifikasi ({{ count($pending) }})</div>
                    <div class="card-subtitle text-amber-700">Periksa dokumen kelengkapan sebelum menyetujui akun kurir</div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-ghost text-amber-800" onclick="location.reload()">
                <span class="material-symbols-outlined text-[16px]">refresh</span>
                Refresh
            </button>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($pending as $courier)
            @php
                $data = $courierData[$courier->id] ?? null;
            @endphp
            <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-amber-50/20 transition-colors">
                <div class="flex items-start gap-3">
                    <div class="avatar bg-amber-100 text-amber-800 font-bold">
                        {{ strtoupper(substr($courier->user?->name ?? 'K', 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ $courier->user?->name ?? 'Kurir' }}</span>
                            <span class="badge badge-amber text-[11px]">Pending Review</span>
                        </div>
                        <div class="text-xs text-slate-500 flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 font-mono">
                            <span>{{ $courier->phone ?? '-' }}</span>
                            <span>&bull;</span>
                            <span>{{ $courier->user?->email ?? '-' }}</span>
                            <span>&bull;</span>
                            <span>Plat: <strong>{{ $courier->vehicle_plate ?? '-' }}</strong> ({{ ucfirst($courier->vehicle_type ?? 'Motor') }})</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="text-[11px] font-medium text-slate-500">Berkas Terunggah:</span>
                            <span class="badge {{ $courier->id_card_photo ? 'badge-green' : 'badge-gray' }} text-[10px]">
                                KTP: {{ $courier->id_card_photo ? 'Ada' : 'Belum' }}
                            </span>
                            <span class="badge {{ $courier->driving_license_photo ? 'badge-green' : 'badge-gray' }} text-[10px]">
                                SIM: {{ $courier->driving_license_photo ? 'Ada' : 'Belum' }}
                            </span>
                            <span class="badge {{ $courier->skck_photo ? 'badge-green' : 'badge-gray' }} text-[10px]">
                                SKCK: {{ $courier->skck_photo ? 'Ada' : 'Belum' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button"
                            onclick="openDriverModal({{ $courier->id }})"
                            class="btn btn-sm btn-secondary">
                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                        <span>Lihat Berkas</span>
                    </button>

                    <form action="{{ route('admin.couriers.verify', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirmSteer()">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-success">
                            <span class="material-symbols-outlined text-[16px]">check_circle</span>
                            <span>Verifikasi Sekarang</span>
                        </button>
                    </form>

                    <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Tolak pendaftaran kurir ini?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-sm btn-ghost text-red-600 hover:bg-red-50" title="Tolak Pendaftaran">
                            <span class="material-symbols-outlined text-[16px]">block</span>
                            <span>Tolak</span>
                        </button>
                    </form>
                </div>
            </div>
            @empty
            <div class="empty-state py-8">
                <span class="material-symbols-outlined text-slate-300">verified</span>
                <div class="empty-title">Semua pendaftaran kurir sudah diverifikasi</div>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Bagian 2: Daftar Kurir Utama --}}
    <div id="verifiedSection" class="space-y-4">
        {{-- Filter & Search Bar --}}
        <div class="card p-3 sm:p-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                {{-- Tabs Filter Client-side --}}
                <div id="kurirFilterTabs" class="filter-tabs">
                    <button type="button" class="tab-btn filter-tab is-active" onclick="setActiveTab('all', this)">
                        <span>Semua Armada</span>
                        <span class="filter-count">{{ $all->count() }}</span>
                    </button>
                    <button type="button" class="tab-btn filter-tab" onclick="setActiveTab('pending', this)">
                        <span>Menunggu</span>
                        @if(count($pending) > 0)
                            <span class="filter-count bg-amber-200 text-amber-900">{{ count($pending) }}</span>
                        @else
                            <span class="filter-count">0</span>
                        @endif
                    </button>
                    <button type="button" class="tab-btn filter-tab" onclick="setActiveTab('verified', this)">
                        <span>Terverifikasi</span>
                        <span class="filter-count">{{ count($verified) }}</span>
                    </button>
                    <button type="button" class="tab-btn filter-tab" onclick="setActiveTab('inactive', this)">
                        <span>Nonaktif</span>
                        <span class="filter-count">{{ $all->where('is_active', false)->count() }}</span>
                    </button>
                </div>

                {{-- Search Box --}}
                <div class="search-box w-full sm:w-72">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           id="searchInput"
                           onkeyup="filterKurirTable()"
                           placeholder="Cari kurir, plat nomor, nomor HP..."
                           class="form-input">
                </div>
            </div>
        </div>

        {{-- Tabel Kurir --}}
        <div class="card overflow-hidden">
            <div class="table-wrap">
                <table class="data-table" id="courierTable">
                    <thead>
                        <tr>
                            <th>Kurir &amp; Kontak</th>
                            <th>Kendaraan &amp; Plat</th>
                            <th>Status Verifikasi</th>
                            <th>Status Akun</th>
                            <th>Drop Point</th>
                            <th>Performa</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($all as $courier)
                        @php
                            $d = $courierData[$courier->id] ?? null;
                            $isVerified = (bool) $courier->is_verified;
                            $isActive = (bool) $courier->is_active;
                            $rowStatus = !$isVerified ? 'pending' : ($isActive ? 'verified' : 'inactive');
                        @endphp
                        <tr data-status="{{ $rowStatus }}" data-name="{{ strtolower($courier->user?->name ?? '') }}" data-phone="{{ $courier->phone }}" data-plate="{{ strtolower($courier->vehicle_plate ?? '') }}">
                            {{-- Info Kurir --}}
                            <td>
                                <div class="flex items-center gap-2.5">
                                    <div class="avatar bg-slate-100 text-slate-700 font-bold shrink-0">
                                        @if($courier->photo)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($courier->photo) }}"
                                                 alt="{{ $courier->user?->name }}"
                                                 class="h-full w-full object-cover rounded-full">
                                        @else
                                            {{ strtoupper(substr($courier->user?->name ?? 'K', 0, 1)) }}
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <button type="button"
                                                onclick="openDriverModal({{ $courier->id }})"
                                                class="text-left font-semibold text-slate-900 hover:text-brand-700 text-[13px] block truncate">
                                            {{ $courier->user?->name ?? 'Kurir' }}
                                        </button>
                                        <div class="text-[11px] text-slate-400 font-mono mt-0.5 flex items-center gap-1.5">
                                            <span>{{ $courier->phone ?? '-' }}</span>
                                            <span>&bull;</span>
                                            <span class="truncate">{{ $courier->user?->email ?? '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Kendaraan & Plat --}}
                            <td class="whitespace-nowrap">
                                <div class="flex items-center gap-1.5 text-xs font-medium text-slate-800">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">
                                        {{ match(strtolower($courier->vehicle_type ?? 'motor')) { 'mobil' => 'directions_car', default => 'two_wheeler' } }}
                                    </span>
                                    <span>{{ ucfirst($courier->vehicle_type ?? 'Motor') }}</span>
                                </div>
                                <div class="text-[11px] text-slate-600 font-mono font-semibold mt-0.5">
                                    {{ $courier->vehicle_plate ?? 'Tanpa Plat' }}
                                </div>
                            </td>

                            {{-- Verifikasi Dokumen --}}
                            <td class="whitespace-nowrap">
                                @if($isVerified)
                                    <span class="badge badge-green">
                                        <span class="badge-dot"></span>
                                        Terverifikasi
                                    </span>
                                @else
                                    <span class="badge badge-amber">
                                        <span class="badge-dot"></span>
                                        Menunggu Review
                                    </span>
                                @endif
                            </td>

                            {{-- Keaktifan Akun & Toggle --}}
                            <td class="whitespace-nowrap">
                                <form action="{{ route('admin.couriers.toggle-active', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Ubah status keaktifan kurir {{ $courier->user?->name }}?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold transition-colors {{ $isActive ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 text-red-700 hover:bg-red-100' }}"
                                            title="Klik untuk mengubah status aktif/nonaktif">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                        {{ $isActive ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </form>
                            </td>

                            {{-- Drop Point --}}
                            <td class="whitespace-nowrap text-xs text-slate-600">
                                {{ $courier->dropPoint?->name ?? 'Pusat / Operasional' }}
                            </td>

                            {{-- Performa Pesanan --}}
                            <td class="whitespace-nowrap font-mono text-xs text-slate-700">
                                <strong>{{ $courier->orders ? $courier->orders->count() : 0 }}</strong> order
                            </td>

                            {{-- Aksi --}}
                            <td class="text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button"
                                            onclick="openDriverModal({{ $courier->id }})"
                                            class="btn-icon"
                                            title="Lihat Detail Profil & Dokumen">
                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                    </button>

                                    <button type="button"
                                            onclick="openEditModal({{ $courier->id }})"
                                            class="btn-icon"
                                            title="Edit Data Kurir">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>

                                    @if(!$isVerified)
                                    <form action="{{ route('admin.couriers.verify', $courier->id) }}" method="POST" class="inline-block" onsubmit="return confirmSteer()">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success" title="Verifikasi Kurir">
                                            <span class="material-symbols-outlined text-[16px]">verified</span>
                                            Verifikasi
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-slate-400">
                                <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">two_wheeler</span>
                                <div class="text-sm font-medium text-slate-600">Belum ada kurir terdaftar</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                <div>
                    Menampilkan <strong>{{ $couriers->firstItem() ?? 0 }}</strong> - <strong>{{ $couriers->lastItem() ?? 0 }}</strong> dari <strong>{{ $couriers->total() }}</strong> kurir
                </div>
                <div>
                    {{ $couriers->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal 1: Tambah Kurir Baru --}}
<div id="addCourierModal" class="modal-backdrop hidden">
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div>
                <div class="modal-title">Tambah Kurir Baru</div>
                <div class="text-xs text-slate-500">Daftarkan akun pengemudi baru ke armada City Courier</div>
            </div>
            <button type="button" class="btn-icon" onclick="closeAddCourierModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form action="{{ route('admin.couriers.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label" for="addName">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="addName" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label" for="addEmail">Email Akun <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="addEmail" required class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label" for="addPhone">Nomor WhatsApp / HP <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" id="addPhone" placeholder="08xxxxxxxxxx" required class="form-input font-mono">
                    </div>
                    <div>
                        <label class="form-label" for="addPassword">Password Login <span class="text-red-500">*</span></label>
                        <input type="password" name="password" id="addPassword" required class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label" for="addNik">Nomor Induk Kependudukan (NIK)</label>
                        <input type="text" name="nik" id="addNik" placeholder="16 digit NIK" class="form-input font-mono">
                    </div>
                    <div>
                        <label class="form-label" for="addCity">Kota Operasional</label>
                        <input type="text" name="city" id="addCity" placeholder="Contoh: Surabaya, Sidoarjo" class="form-input">
                    </div>
                </div>

                <div>
                    <label class="form-label" for="addAddress">Alamat Domisili</label>
                    <textarea name="address" id="addAddress" rows="2" class="form-textarea" placeholder="Alamat lengkap kurir..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="form-label">Jenis Kendaraan</label>
                        <select name="vehicle_type" class="form-select">
                            <option value="motor">Sepeda Motor</option>
                            <option value="mobil">Mobil</option>
                            <option value="sepeda">Sepeda</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Merk / Tipe Kendaraan</label>
                        <input type="text" name="vehicle_brand" placeholder="Contoh: Honda Vario" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Plat Nomor Kendaraan</label>
                        <input type="text" name="vehicle_plate" placeholder="Contoh: L 1234 AB" class="form-input font-mono">
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-3">
                    <div class="text-xs font-semibold text-slate-800 mb-2">Upload Berkas Identitas (Opsional)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <label class="form-label text-[12px]">Foto Profil Kurir</label>
                            <input type="file" name="photo" accept="image/*" class="form-input py-1 text-xs">
                        </div>
                        <div>
                            <label class="form-label text-[12px]">Foto KTP</label>
                            <input type="file" name="id_card_photo" accept="image/*" class="form-input py-1 text-xs">
                        </div>
                        <div>
                            <label class="form-label text-[12px]">Foto SIM Aktif</label>
                            <input type="file" name="driving_license_photo" accept="image/*" class="form-input py-1 text-xs">
                        </div>
                        <div>
                            <label class="form-label text-[12px]">Foto SKCK / Surat Keterangan</label>
                            <input type="file" name="skck_photo" accept="image/*" class="form-input py-1 text-xs">
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="activate" id="addActivate" value="1" checked class="form-check">
                    <label for="addActivate" class="text-xs font-medium text-slate-700">Langsung aktifkan akun kurir</label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddCourierModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan &amp; Daftarkan Kurir</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 2: Edit Data Kurir --}}
<div id="editCourierModal" class="modal-backdrop hidden">
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div>
                <div class="modal-title" id="editIdentityLabel">Edit Kurir</div>
                <div class="text-xs text-slate-500 font-mono" id="editIdLabel">ID Kurir: -</div>
            </div>
            <button type="button" class="btn-icon" onclick="closeEditModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="editCourierForm" action="#" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-body space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                    <div class="avatar bg-brand-50 text-brand-700 text-sm font-bold" id="editAvatar">
                        K
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-slate-800">Perbarui Informasi Kurir</div>
                        <div class="text-[11px] text-slate-400">Pastikan data kontak dan identitas kendaraan valid</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="editName" required class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Email Akun <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="editEmail" required class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label">Nomor Telepon / WhatsApp <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" id="editPhone" required class="form-input font-mono">
                    </div>
                    <div>
                        <label class="form-label">NIK (KTP)</label>
                        <input type="text" name="nik" id="editNik" class="form-input font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="form-label">Kota</label>
                        <input type="text" name="city" id="editCity" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Alamat Lengkap</label>
                        <input type="text" name="address" id="editAddress" class="form-input">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="form-label">Tipe Kendaraan</label>
                        <select name="vehicle_type" id="editVehicleType" class="form-select">
                            <option value="motor">Sepeda Motor</option>
                            <option value="mobil">Mobil</option>
                            <option value="sepeda">Sepeda</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Merk Kendaraan</label>
                        <input type="text" name="vehicle_brand" id="editVehicleBrand" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Tahun Kendaraan</label>
                        <input type="text" name="vehicle_year" id="editVehicleYear" class="form-input font-mono">
                    </div>
                </div>

                <div>
                    <label class="form-label">Plat Nomor</label>
                    <input type="text" name="vehicle_plate" id="editPlate" class="form-input font-mono">
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" name="is_active" id="editIsActive" value="1" class="form-check">
                    <label for="editIsActive" class="text-xs font-medium text-slate-700">Akun kurir aktif menerima pesanan</label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal 3: Detail Kurir & Dokumen Viewer --}}
<div id="driverDetailModal" class="modal-backdrop hidden">
    <div class="modal-panel max-w-2xl">
        <div class="modal-header">
            <div class="flex items-center gap-3">
                <div class="avatar bg-brand-50 text-brand-700 font-bold overflow-hidden" id="modalDriverAvatarContainer">
                    <span id="modalDriverAvatarLetter">K</span>
                    <img id="modalDriverAvatarImg" src="" alt="" class="hidden h-full w-full object-cover">
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="modal-title" id="modalDriverName">Nama Kurir</span>
                        <span id="modalDriverBadge" class="badge badge-gray text-[11px]">Status</span>
                    </div>
                    <div class="text-xs text-slate-500 font-mono" id="modalDriverEmailPhone">- &bull; -</div>
                </div>
            </div>
            <button type="button" class="btn-icon" onclick="closeDriverModal()" aria-label="Tutup">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="modal-body space-y-4">
            {{-- Info Kendaraan & Identitas --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200 text-xs">
                <div>
                    <span class="text-slate-400 block text-[11px]">ID Kurir:</span>
                    <strong class="text-slate-800 font-mono" id="modalDriverId">-</strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px]">NIK KTP:</span>
                    <strong class="text-slate-800 font-mono" id="modalNik">-</strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px]">Kendaraan:</span>
                    <strong class="text-slate-800" id="modalVehicleType">-</strong>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px]">Plat Nomor:</span>
                    <strong class="text-slate-800 font-mono" id="modalVehiclePlate">-</strong>
                </div>
            </div>

            {{-- Dokumen Kurir (KTP, SIM, SKCK) --}}
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2.5">
                    Dokumen Kelengkapan
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {{-- KTP --}}
                    <div class="card p-3 text-center space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                            <span>KTP</span>
                            <span id="ktpBadge" class="badge badge-gray text-[10px]">Cek</span>
                        </div>
                        <div class="h-28 bg-slate-100 rounded border border-slate-200 overflow-hidden flex items-center justify-center">
                            <img id="ktpImg" src="" alt="KTP" class="hidden h-full w-full object-contain">
                            <span id="ktpEmpty" class="text-slate-400 text-xs">Tidak ada file</span>
                        </div>
                        <a id="ktpLink" href="#" target="_blank" class="btn btn-sm btn-secondary w-full text-xs">
                            Buka Foto Penuh
                        </a>
                    </div>

                    {{-- SIM --}}
                    <div class="card p-3 text-center space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                            <span>SIM</span>
                            <span id="simBadge" class="badge badge-gray text-[10px]">Cek</span>
                        </div>
                        <div class="h-28 bg-slate-100 rounded border border-slate-200 overflow-hidden flex items-center justify-center">
                            <img id="simImg" src="" alt="SIM" class="hidden h-full w-full object-contain">
                            <span id="simEmpty" class="text-slate-400 text-xs">Tidak ada file</span>
                        </div>
                        <a id="simLink" href="#" target="_blank" class="btn btn-sm btn-secondary w-full text-xs">
                            Buka Foto Penuh
                        </a>
                    </div>

                    {{-- SKCK --}}
                    <div class="card p-3 text-center space-y-2">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                            <span>SKCK / Surat</span>
                            <span id="skckBadge" class="badge badge-gray text-[10px]">Cek</span>
                        </div>
                        <div class="h-28 bg-slate-100 rounded border border-slate-200 overflow-hidden flex items-center justify-center">
                            <img id="skckImg" src="" alt="SKCK" class="hidden h-full w-full object-contain">
                            <span id="skckEmpty" class="text-slate-400 text-xs">Tidak ada file</span>
                        </div>
                        <a id="skckLink" href="#" target="_blank" class="btn btn-sm btn-secondary w-full text-xs">
                            Buka Foto Penuh
                        </a>
                    </div>
                </div>
            </div>

            {{-- Hubungi Kurir --}}
            <div class="flex items-center justify-between pt-2">
                <span class="text-xs text-slate-500" id="modalJoined">Bergabung: -</span>
                <a id="modalWaLink" href="#" target="_blank" class="btn btn-sm btn-success">
                    <span class="material-symbols-outlined text-[16px]">chat</span>
                    Chat WhatsApp
                </a>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDriverModal()">Tutup</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.__couriers = @json($courierData);

    let __initialTab = @json(request('filter'));
    if (__initialTab === 'unverified') {
        document.addEventListener('DOMContentLoaded', function () {
            const tab = document.querySelector('#kurirFilterTabs .tab-btn:nth-child(2)');
            if (tab) setActiveTab('pending', tab);
        });
    }

    function setActiveTab(status, btn) {
        document.querySelectorAll('#kurirFilterTabs .tab-btn').forEach(b => {
            b.classList.remove('is-active');
        });
        if (btn) btn.classList.add('is-active');

        const rows = document.querySelectorAll('#courierTable tbody tr[data-status]');
        rows.forEach(row => {
            const s = row.getAttribute('data-status');
            if (status === 'all') {
                row.style.display = '';
            } else if (status === 'pending') {
                row.style.display = (s === 'pending') ? '' : 'none';
            } else if (status === 'verified') {
                row.style.display = (s === 'verified') ? '' : 'none';
            } else if (status === 'inactive') {
                row.style.display = (s === 'inactive') ? '' : 'none';
            }
        });
    }

    function filterKurirTable() {
        const query = (document.getElementById('searchInput').value || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#courierTable tbody tr[data-status]');

        rows.forEach(row => {
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const phone = (row.getAttribute('data-phone') || '').toLowerCase();
            const plate = (row.getAttribute('data-plate') || '').toLowerCase();

            if (!query || name.includes(query) || phone.includes(query) || plate.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function openDriverModal(id) {
        const c = window.__couriers[id];
        if (!c) return;

        const modal = document.getElementById('driverDetailModal');
        document.getElementById('modalDriverName').textContent = c.name;
        document.getElementById('modalDriverEmailPhone').textContent = c.email + ' • ' + c.phone;
        document.getElementById('modalDriverId').textContent = c.idLabel;
        document.getElementById('modalNik').textContent = c.nik;
        document.getElementById('modalVehicleType').textContent = c.vehicleLabel;
        document.getElementById('modalVehiclePlate').textContent = c.plate;
        document.getElementById('modalJoined').textContent = 'Bergabung: ' + c.joined;

        const letter = document.getElementById('modalDriverAvatarLetter');
        const avatarImg = document.getElementById('modalDriverAvatarImg');
        if (c.photoUrl) {
            letter.classList.add('hidden');
            avatarImg.src = c.photoUrl;
            avatarImg.classList.remove('hidden');
        } else {
            letter.textContent = c.name.charAt(0).toUpperCase();
            letter.classList.remove('hidden');
            avatarImg.classList.add('hidden');
        }

        const badge = document.getElementById('modalDriverBadge');
        if (c.verified) {
            badge.className = 'badge badge-green text-[11px]';
            badge.textContent = 'Terverifikasi';
        } else {
            badge.className = 'badge badge-amber text-[11px]';
            badge.textContent = 'Menunggu Review';
        }

        const waLink = document.getElementById('modalWaLink');
        waLink.href = 'https://wa.me/' + c.wa;

        setDocImage('ktpImg', 'ktpEmpty', 'ktpLink', 'ktpBadge', c.ktpUrl);
        setDocImage('simImg', 'simEmpty', 'simLink', 'simBadge', c.simUrl);
        setDocImage('skckImg', 'skckEmpty', 'skckLink', 'skckBadge', c.skckUrl);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function setDocImage(imgId, emptyId, linkId, badgeId, url) {
        const img = document.getElementById(imgId);
        const empty = document.getElementById(emptyId);
        const link = document.getElementById(linkId);
        const badge = document.getElementById(badgeId);

        if (url) {
            img.src = url;
            img.classList.remove('hidden');
            empty.classList.add('hidden');
            link.href = url;
            link.classList.remove('pointer-events-none');
            badge.textContent = 'Valid';
            badge.className = 'badge badge-green text-[10px]';
        } else {
            img.classList.add('hidden');
            empty.classList.remove('hidden');
            link.href = '#';
            link.classList.add('pointer-events-none');
            badge.textContent = 'Belum Ada';
            badge.className = 'badge badge-gray text-[10px]';
        }
    }

    function closeDriverModal() {
        const modal = document.getElementById('driverDetailModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openAddCourierModal() {
        closeDriverModal();
        closeEditModal();
        const modal = document.getElementById('addCourierModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAddCourierModal() {
        const modal = document.getElementById('addCourierModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openEditModal(id) {
        const c = window.__couriers[id];
        if (!c) return;

        closeDriverModal();
        closeAddCourierModal();

        const form = document.getElementById('editCourierForm');
        form.action = `{{ url('admin/couriers') }}/${id}`;

        document.getElementById('editAvatar').textContent = c.name.charAt(0).toUpperCase();
        document.getElementById('editIdentityLabel').textContent = c.name;
        document.getElementById('editIdLabel').textContent = 'ID Kurir: ' + c.idLabel;
        document.getElementById('editName').value = c.name;
        document.getElementById('editEmail').value = c.email === '-' ? '' : c.email;
        document.getElementById('editPhone').value = c.phone === '-' ? '' : c.phone;
        document.getElementById('editNik').value = c.nikRaw || '';
        document.getElementById('editCity').value = c.city || '';
        document.getElementById('editAddress').value = c.address || '';
        document.getElementById('editVehicleType').value = c.vehicleType || 'motor';
        document.getElementById('editVehicleBrand').value = c.vehicleBrand || '';
        document.getElementById('editVehicleYear').value = c.vehicleYear || '';
        document.getElementById('editPlate').value = c.plate === '-' ? '' : c.plate;
        document.getElementById('editIsActive').checked = c.active;

        const modal = document.getElementById('editCourierModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditModal() {
        const modal = document.getElementById('editCourierModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function confirmSteer() {
        return confirm('Verifikasi berkas kurir ini sekarang? Kurir akan langsung aktif menerima pesanan.');
    }

    function shareRegistrationLink() {
        const url = `{{ url('/register') }}`;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function() {
                alert('Link pendaftaran kurir disalin ke clipboard:\n' + url);
            });
        } else {
            alert('Link pendaftaran kurir:\n' + url);
        }
    }
</script>
@endpush
@endsection
