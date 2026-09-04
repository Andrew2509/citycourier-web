@extends('layouts.admin')

@section('title', $viewTitle ?? 'Manajemen Kurir')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $viewTitle ?? 'Manajemen Kurir' }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ $viewSubtitle ?? 'Kelola data dan verifikasi kurir' }}</p>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="bg-white rounded-2xl p-4 border border-surface-border">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <!-- Filter Tabs -->
            <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-xl">
                <a href="{{ route('admin.couriers') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ !request('filter') ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Semua
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'verified']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ request('filter') === 'verified' ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Terverifikasi
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'unverified']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ request('filter') === 'unverified' ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Belum Verifikasi
                </a>
                <a href="{{ route('admin.couriers', ['filter' => 'active']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ request('filter') === 'active' ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    Aktif
                </a>
            </div>

            <!-- Search -->
            <form method="GET" action="{{ route('admin.couriers') }}" class="flex items-center gap-2">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 material-symbols-outlined text-slate-400 text-[18px]">search</span>
                    <input type="text" name="search" class="bg-slate-50 border border-slate-200 text-slate-700 pl-10 pr-4 py-2 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary w-64" placeholder="Cari nama, email, telepon..." value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    <!-- Courier Table -->
    <div class="bg-white rounded-2xl border border-surface-border overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">group</span>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-800">{{ $viewTitle ?? 'Daftar Kurir' }}</h4>
                    <p class="text-xs text-slate-400">Total: {{ $couriers->total() }} kurir</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kurir</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Telepon</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kendaraan</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Plat</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Verifikasi</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($couriers as $courier)
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-3.5 px-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white font-bold text-sm shadow-sm">
                                    {{ strtoupper(substr($courier->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-slate-700">{{ $courier->user->name ?? '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ $courier->user->email ?? '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3.5 px-5 text-sm text-slate-600">{{ $courier->phone ?? '-' }}</td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                {{ match($courier->vehicle_type) {
                                    'motor' => 'bg-info/10 text-info',
                                    'mobil' => 'bg-purple-100 text-purple-600',
                                    'pickup', 'box', 'truck' => 'bg-warning/10 text-warning',
                                    default => 'bg-slate-100 text-slate-500'
                                } }}">
                                <span class="material-symbols-outlined text-[14px]">
                                    {{ match($courier->vehicle_type) {
                                        'motor' => 'two_wheeler',
                                        'mobil' => 'directions_car',
                                        'pickup', 'box', 'truck' => 'local_shipping',
                                        default => 'pedal_bike'
                                    } }}
                                </span>
                                {{ match($courier->vehicle_type) {
                                    'motor' => 'Motor',
                                    'mobil' => 'Mobil',
                                    'pickup' => 'Pickup',
                                    'box' => 'Box',
                                    'truck' => 'Truck',
                                    default => ucfirst($courier->vehicle_type)
                                } }}
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-sm font-mono font-bold text-slate-700">{{ $courier->vehicle_plate ?? '-' }}</td>
                        <td class="py-3.5 px-5">
                            @if($courier->is_verified)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-success/10 text-success">
                                    <span class="material-symbols-outlined text-[12px]">verified</span>
                                    Terverifikasi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-warning/10 text-warning">
                                    <span class="material-symbols-outlined text-[12px]">pending</span>
                                    Belum
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-5">
                            @if($courier->is_active)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-success/10 text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Nonaktif
                                </span>
                            @endif
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <!-- Detail Button -->
                                <button type="button" class="p-2 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-all btn-detail"
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
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>

                                <!-- Verify Button -->
                                <form method="POST" action="{{ route('admin.couriers.verify', $courier) }}" class="inline" data-confirm="Yakin ingin mengubah status verifikasi kurir ini?">
                                    @csrf
                                    @method('PATCH')
                                    @if($courier->is_verified)
                                        <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-error hover:bg-error/5 transition-all" title="Cabut Verifikasi">
                                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                                        </button>
                                    @else
                                        <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-success hover:bg-success/5 transition-all" title="Verifikasi">
                                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                        </button>
                                    @endif
                                </form>

                                <!-- Toggle Active -->
                                <form method="POST" action="{{ route('admin.couriers.toggle-active', $courier) }}" class="inline" data-confirm="Yakin ingin mengubah status aktif kurir ini?">
                                    @csrf
                                    @method('PATCH')
                                    @if($courier->is_active)
                                        <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-warning hover:bg-warning/5 transition-all" title="Nonaktifkan">
                                            <span class="material-symbols-outlined text-[18px]">toggle_on</span>
                                        </button>
                                    @else
                                        <button type="submit" class="p-2 rounded-lg text-slate-400 hover:text-success hover:bg-success/5 transition-all" title="Aktifkan">
                                            <span class="material-symbols-outlined text-[18px]">toggle_off</span>
                                        </button>
                                    @endif
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">group</span>
                                <h3 class="text-base font-semibold text-slate-600">Belum ada kurir</h3>
                                <p class="text-sm text-slate-400 mt-1">Kurir yang mendaftar dari aplikasi akan muncul di sini.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($couriers->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-sm text-slate-400">Menampilkan {{ $couriers->firstItem() }}-{{ $couriers->lastItem() }} dari {{ $couriers->total() }}</span>
            <div class="flex items-center gap-1.5">
                {{ $couriers->withQueryString()->links('pagination.custom') }}
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Detail Modal -->
<div id="courierModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl" style="animation: modalSlide 0.3s ease-out;">
            @keyframes modalSlide {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }

            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-xl">badge</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Detail Dokumen Kurir</h3>
                </div>
                <button class="close-modal p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-all">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="px-6 py-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Data Pribadi -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-1 h-4 rounded-full bg-primary"></span>
                            Data Pribadi
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Nama Lengkap</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-name">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">NIK</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-nik">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">WhatsApp</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-phone">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Email</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-email">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Alamat</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-address">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Kota</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-city">-</p>
                            </div>
                        </div>
                    </div>

                    <!-- Kendaraan & Profil -->
                    <div>
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="w-1 h-4 rounded-full bg-info"></span>
                            Kendaraan & Profil
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Jenis Kendaraan</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-vehicle">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Merek & Tipe</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-brand">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Tahun</label>
                                <p class="text-sm font-semibold text-slate-700" id="det-year">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Nomor Plat</label>
                                <p class="text-sm font-mono font-bold text-primary" id="det-plate">-</p>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium text-slate-400 uppercase">Pas Foto 4x6</label>
                                <div class="mt-2 w-full h-32 rounded-xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden" id="preview-photo">
                                    <span class="text-xs text-slate-400">Belum diunggah</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dokumen Pendukung -->
                <div>
                    <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <span class="w-1 h-4 rounded-full bg-success"></span>
                        Dokumen Pendukung
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="text-[10px] font-medium text-slate-400 uppercase">Foto KTP</label>
                            <div class="mt-2 w-full h-32 rounded-xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden" id="preview-ktp">
                                <span class="text-xs text-slate-400">Belum diunggah</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-medium text-slate-400 uppercase">Foto SIM</label>
                            <div class="mt-2 w-full h-32 rounded-xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden" id="preview-sim">
                                <span class="text-xs text-slate-400">Belum diunggah</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-medium text-slate-400 uppercase">SKCK</label>
                            <div class="mt-2 w-full h-32 rounded-xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden" id="preview-skck">
                                <span class="text-xs text-slate-400">Belum diunggah</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <form id="modal-verify-form" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <button type="submit" id="modal-verify-btn" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-success to-green-400 text-white hover:shadow-lg hover:shadow-success/20 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Verifikasi Kurir
                    </button>
                </form>
                <button class="close-modal px-5 py-2.5 rounded-xl text-sm font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">Tutup</button>
            </div>
        </div>
    </div>
</div>

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
                verifyBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">cancel</span> Cabut Verifikasi';
                verifyBtn.className = 'px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-error to-red-400 text-white hover:shadow-lg hover:shadow-error/20 transition-all flex items-center gap-2';
            } else {
                verifyBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span> Verifikasi Kurir';
                verifyBtn.className = 'px-5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-success to-green-400 text-white hover:shadow-lg hover:shadow-success/20 transition-all flex items-center gap-2';
            }

            modal.classList.remove('hidden');
        });
    });

    closeBtns.forEach(btn => {
        btn.addEventListener('click', () => modal.classList.add('hidden'));
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.add('hidden');
    });

    function setupPreview(id, url) {
        const container = document.getElementById(id);
        if (url) {
            container.innerHTML = `<img src="${url}" alt="Preview" class="w-full h-full object-cover cursor-pointer" onclick="window.open('${url}', '_blank')">`;
        } else {
            container.innerHTML = '<span class="text-xs text-slate-400">Belum diunggah</span>';
        }
    }
});
</script>
@endsection
