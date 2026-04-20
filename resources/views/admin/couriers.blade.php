@extends('layouts.admin')

@section('title', $viewTitle ?? 'Manajemen Kurir')
@section('page-title', $viewTitle ?? 'Manajemen Kurir')
@section('page-subtitle', $viewSubtitle ?? 'Kelola data dan verifikasi kurir')

@section('content')
    {{-- Filter & Search --}}
    <div class="glass-card" style="margin-bottom: 20px;">
        <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div class="filter-tabs">
                <a href="{{ route('admin.couriers') }}"
                   class="filter-tab {{ !request('filter') ? 'active' : '' }}">
                    Semua
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}"
                   class="filter-tab {{ request('filter') === 'verified' ? 'active' : '' }}">
                    <i class="fas fa-check-circle"></i> Terverifikasi
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}"
                   class="filter-tab {{ request('filter') === 'unverified' ? 'active' : '' }}">
                    <i class="fas fa-clock"></i> Belum Verifikasi
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'active']) }}"
                   class="filter-tab {{ request('filter') === 'active' ? 'active' : '' }}">
                    <i class="fas fa-signal"></i> Aktif
                </a>
            </div>

            <form method="GET" action="{{ route('admin.couriers') }}" class="search-bar">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" class="search-input"
                           placeholder="Cari nama, email, telepon..."
                           value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    {{-- Courier Table --}}
    <div class="glass-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-motorcycle"></i>
                {{ $viewTitle ?? 'Daftar Kurir' }}
            </div>
            <span style="font-size: 13px; color: var(--text-muted);">
                Total: {{ $couriers->total() }} kurir
            </span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kurir</th>
                        <th>Telepon</th>
                        <th>Kendaraan</th>
                        <th>Plat</th>
                        <th>Verifikasi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($couriers as $courier)
                        <tr>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        {{ strtoupper(substr($courier->user->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="user-name">{{ $courier->user->name ?? '-' }}</div>
                                        <div class="user-email">{{ $courier->user->email ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $courier->phone ?? '-' }}</td>
                             <td>
                                <span class="badge badge-{{ $courier->vehicle_type }}">
                                    @if($courier->vehicle_type === 'motor')
                                        <i class="fas fa-motorcycle"></i>
                                    @elseif($courier->vehicle_type === 'pickup')
                                        <i class="fas fa-truck-pickup"></i>
                                    @elseif($courier->vehicle_type === 'box')
                                        <i class="fas fa-truck-ramp-box"></i>
                                    @elseif($courier->vehicle_type === 'truck')
                                        <i class="fas fa-truck"></i>
                                    @elseif($courier->vehicle_type === 'mobil')
                                        <i class="fas fa-car"></i>
                                    @else
                                        <i class="fas fa-bicycle"></i>
                                    @endif
                                    {{ ucfirst($courier->vehicle_type === 'mobil' ? 'Mobil' : ($courier->vehicle_type === 'pickup' ? 'Mobil Pickup' : ($courier->vehicle_type === 'box' ? 'Mobil Box' : ucfirst($courier->vehicle_type)))) }}
                                </span>
                            </td>
                            <td style="font-weight:600;">{{ $courier->vehicle_plate ?? '-' }}</td>
                            <td>
                                @if($courier->is_verified)
                                    <span class="badge badge-verified">
                                        <i class="fas fa-check-circle"></i> Terverifikasi
                                    </span>
                                @else
                                    <span class="badge badge-unverified">
                                        <i class="fas fa-clock"></i> Belum
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($courier->is_active)
                                    <span class="badge badge-active">
                                        <i class="fas fa-circle" style="font-size:6px;"></i> Aktif
                                    </span>
                                @else
                                    <span class="badge badge-inactive">
                                        <i class="fas fa-circle" style="font-size:6px;"></i> Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td>
                                 <div class="action-btns">
                                    <button type="button" class="btn btn-ghost btn-sm btn-detail" 
                                            title="Detail & Dokumen"
                                            data-courier="{{ json_encode([
                                                'name' => $courier->user->name ?? '-',
                                                'email' => $courier->user->email ?? '-',
                                                'nik' => $courier->nik ?? '-',
                                                'phone' => $courier->phone ?? '-',
                                                'address' => $courier->address ?? '-',
                                                'city' => $courier->city ?? '-',
                                                'vehicle_type' => $courier->vehicle_type,
                                                'vehicle_brand' => $courier->vehicle_brand ?? '-',
                                                'vehicle_year' => $courier->vehicle_year ?? '-',
                                                'vehicle_plate' => $courier->vehicle_plate ?? '-',
                                                'photo' => $courier->photo ? asset('storage/' . $courier->photo) : null,
                                                'id_card_photo' => $courier->id_card_photo ? asset('storage/' . $courier->id_card_photo) : null,
                                                'driving_license_photo' => $courier->driving_license_photo ? asset('storage/' . $courier->driving_license_photo) : null,
                                                'skck_photo' => $courier->skck_photo ? asset('storage/' . $courier->skck_photo) : null,
                                                'is_verified' => $courier->is_verified,
                                                'verify_url' => route('admin.couriers.verify', $courier)
                                            ]) }}">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <form method="POST" action="{{ route('admin.couriers.verify', $courier) }}"
                                          data-confirm="Yakin ingin mengubah status verifikasi kurir ini?">
                                        @csrf
                                        @method('PATCH')
                                        @if($courier->is_verified)
                                            <button type="submit" class="btn btn-danger btn-sm" title="Cabut Verifikasi">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-success btn-sm" title="Verifikasi Langsung">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        @endif
                                    </form>

                                    <form method="POST" action="{{ route('admin.couriers.toggle-active', $courier) }}"
                                          data-confirm="Yakin ingin mengubah status aktif kurir ini?">
                                        @csrf
                                        @method('PATCH')
                                        @if($courier->is_active)
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Nonaktifkan">
                                                <i class="fas fa-toggle-on" style="color: var(--accent-success);"></i>
                                            </button>
                                        @else
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Aktifkan">
                                                <i class="fas fa-toggle-off"></i>
                                            </button>
                                        @endif
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-motorcycle"></i>
                                    <h3>Belum ada kurir</h3>
                                    <p>Kurir yang mendaftar dari aplikasi akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($couriers->hasPages())
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Menampilkan {{ $couriers->firstItem() }}-{{ $couriers->lastItem() }} dari {{ $couriers->total() }}
                </div>
                <div class="pagination-links">
                    {{ $couriers->withQueryString()->links('pagination.custom') }}
                </div>
            </div>
        @endif
    </div>
    </div>

    {{-- Detail Modal --}}
    <div id="courierModal" class="modal">
        <div class="modal-content glass-card">
            <div class="modal-header">
                <h3><i class="fas fa-id-card"></i> Detail Dokumen Kurir</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-section">
                        <h4>Data Pribadi</h4>
                        <div class="info-item">
                            <label>Nama Lengkap</label>
                            <span id="det-name">-</span>
                        </div>
                        <div class="info-item">
                            <label>NIK (ID Card Num)</label>
                            <span id="det-nik">-</span>
                        </div>
                        <div class="info-item">
                            <label>WhatsApp</label>
                            <span id="det-phone">-</span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span id="det-email">-</span>
                        </div>
                        <div class="info-item">
                            <label>Alamat</label>
                            <span id="det-address">-</span>
                        </div>
                        <div class="info-item">
                            <label>Kota</label>
                            <span id="det-city">-</span>
                        </div>
                    </div>
                    <div class="detail-section">
                        <h4>Kendaran & Profil</h4>
                        <div class="info-item">
                            <label>Jenis Kendaraan</label>
                            <span id="det-vehicle">-</span>
                        </div>
                        <div class="info-item">
                            <label>Merek & Tipe</label>
                            <span id="det-brand">-</span>
                        </div>
                        <div class="info-item">
                            <label>Tahun</label>
                            <span id="det-year">-</span>
                        </div>
                        <div class="info-item">
                            <label>Nomor Plat</label>
                            <span id="det-plate">-</span>
                        </div>
                        <div class="info-item" style="margin-top: 10px;">
                            <label>Pas Foto 4x6</label>
                            <div class="doc-preview" id="preview-photo">
                                <span class="no-img">Belum diunggah</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="document-section">
                    <h4>Dokumen Pendukung</h4>
                    <div class="doc-grid">
                        <div class="doc-item">
                            <label>Foto KTP</label>
                            <div class="doc-preview" id="preview-ktp">
                                <span class="no-img">Belum diunggah</span>
                            </div>
                        </div>
                        <div class="doc-item">
                            <label>Foto SIM</label>
                            <div class="doc-preview" id="preview-sim">
                                <span class="no-img">Belum diunggah</span>
                            </div>
                        </div>
                        <div class="doc-item">
                            <label>SKCK</label>
                            <div class="doc-preview" id="preview-skck">
                                <span class="no-img">Belum diunggah</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form id="modal-verify-form" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <button type="submit" id="modal-verify-btn" class="btn btn-success">
                        <i class="fas fa-check"></i> Verifikasi Kurir
                    </button>
                </form>
                <button class="btn btn-ghost close-modal">Tutup</button>
            </div>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }
        .modal-content {
            margin: 50px auto;
            max-width: 900px;
            width: 90%;
            padding: 0;
            animation: modalSlide 0.3s ease-out;
        }
        @keyframes modalSlide {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 { margin: 0; font-size: 1.25rem; color: var(--accent-primary); }
        .close-modal {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .close-modal:hover { color: var(--text-light); }
        .modal-body { padding: 25px; }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        .detail-section h4, .document-section h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--text-light);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-left: 3px solid var(--accent-primary);
            padding-left: 10px;
        }
        .info-item { margin-bottom: 12px; }
        .info-item label {
            display: block;
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .info-item span { color: var(--text-light); font-weight: 500; font-size: 0.95rem; }
        .doc-preview {
            width: 100%;
            height: 150px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .doc-preview:hover { border-color: var(--accent-primary); background: rgba(255,255,255,0.08); }
        .doc-preview img { width: 100%; height: 100%; object-fit: cover; }
        .doc-preview .no-img { font-size: 12px; color: var(--text-muted); }
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* New Badges */
        .badge-pickup { background: rgba(52, 152, 219, 0.15); color: #3498db; }
        .badge-box { background: rgba(155, 89, 182, 0.15); color: #9b59b2; }
        .badge-truck { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('courierModal');
            const detailBtns = document.querySelectorAll('.btn-detail');
            const closeBtns = document.querySelectorAll('.close-modal');

            detailBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const data = JSON.parse(this.dataset.courier);
                    
                    // Fill text data
                    document.getElementById('det-name').textContent = data.name;
                    document.getElementById('det-nik').textContent = data.nik;
                    document.getElementById('det-phone').textContent = data.phone;
                    document.getElementById('det-email').textContent = data.email;
                    document.getElementById('det-address').textContent = data.address;
                    document.getElementById('det-city').textContent = data.city;
                    document.getElementById('det-vehicle').textContent = data.vehicle_type.toUpperCase();
                    document.getElementById('det-brand').textContent = data.vehicle_brand;
                    document.getElementById('det-year').textContent = data.vehicle_year;
                    document.getElementById('det-plate').textContent = data.vehicle_plate;

                    // Fill images
                    setupPreview('preview-photo', data.photo);
                    setupPreview('preview-ktp', data.id_card_photo);
                    setupPreview('preview-sim', data.driving_license_photo);
                    setupPreview('preview-skck', data.skck_photo);

                    // Setup button
                    const verifyForm = document.getElementById('modal-verify-form');
                    const verifyBtn = document.getElementById('modal-verify-btn');
                    
                    verifyForm.action = data.verify_url;
                    if (data.is_verified) {
                        verifyBtn.innerHTML = '<i class="fas fa-times"></i> Cabut Verifikasi';
                        verifyBtn.className = 'btn btn-danger';
                    } else {
                        verifyBtn.innerHTML = '<i class="fas fa-check"></i> Verifikasi Kurir';
                        verifyBtn.className = 'btn btn-success';
                    }

                    modal.style.display = 'block';
                });
            });

            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => modal.style.display = 'none');
            });

            window.addEventListener('click', (e) => {
                if (e.target == modal) modal.style.display = 'none';
            });

            function setupPreview(id, url) {
                const container = document.getElementById(id);
                if (url) {
                    container.innerHTML = `<img src="${url}" alt="Preview" onclick="window.open('${url}', '_blank')">`;
                } else {
                    container.innerHTML = '<span class="no-img">Belum diunggah</span>';
                }
            }
        });
    </script>
@endsection
