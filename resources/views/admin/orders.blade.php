@extends('layouts.admin')

@section('title', 'Manajemen Pesanan')

@section('content')
<div class="space-y-5">
    {{-- Header Halaman --}}
    <div class="page-header">
        <div>
            <h1 class="page-title">Manajemen Pesanan</h1>
            <p class="page-subtitle">Pantau seluruh order masuk, penugasan kurir, dan riwayat pengiriman.</p>
        </div>
        <div class="page-actions">
            <span class="badge badge-gray text-xs">
                Total: <strong class="text-slate-900 ml-1">{{ $orders->total() }}</strong> pesanan
            </span>
        </div>
    </div>

    {{-- Filter & Pencarian --}}
    <div class="card p-3 sm:p-4">
        <form method="GET" action="{{ route('admin.orders') }}" class="flex flex-col lg:flex-row lg:items-center gap-3">
            {{-- Tabs Filter Status --}}
            <div class="filter-tabs">
                @php
                    $curStatus = request('status', '');
                @endphp
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => ''])) }}"
                   class="filter-tab {{ $curStatus === '' ? 'is-active' : '' }}">
                    <span>Semua</span>
                </a>
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => 'pending'])) }}"
                   class="filter-tab {{ $curStatus === 'pending' ? 'is-active' : '' }}">
                    <span>Menunggu Kurir</span>
                </a>
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => 'assigned'])) }}"
                   class="filter-tab {{ $curStatus === 'assigned' ? 'is-active' : '' }}">
                    <span>Ditugaskan</span>
                </a>
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => 'picked_up'])) }}"
                   class="filter-tab {{ $curStatus === 'picked_up' ? 'is-active' : '' }}">
                    <span>Diambil</span>
                </a>
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => 'completed'])) }}"
                   class="filter-tab {{ $curStatus === 'completed' ? 'is-active' : '' }}">
                    <span>Selesai</span>
                </a>
                <a href="{{ route('admin.orders', array_merge(request()->except('status', 'page'), ['status' => 'cancelled'])) }}"
                   class="filter-tab {{ $curStatus === 'cancelled' ? 'is-active' : '' }}">
                    <span>Dibatalkan</span>
                </a>
            </div>

            {{-- Kolom Cari --}}
            <div class="lg:ml-auto flex items-center gap-2">
                <div class="search-box w-full sm:w-72">
                    <span class="material-symbols-outlined">search</span>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Cari resi, nama, hp..."
                           class="form-input">
                </div>
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <button type="submit" class="btn btn-secondary">
                    Cari
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.orders') }}" class="btn btn-ghost" title="Reset filter">
                        <span class="material-symbols-outlined">restart_alt</span>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Tabel Pesanan --}}
    <div class="card overflow-hidden">
        @if($orders->count() > 0)
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nomor Resi / ID</th>
                        <th>Waktu Pesan</th>
                        <th>Pengirim &amp; Penerima</th>
                        <th>Rute Alamat</th>
                        <th>Kurir</th>
                        <th>Status</th>
                        <th>Tarif</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    @php
                        $status = strtolower($order->status ?? 'pending');
                        $badgeClass = match($status) {
                            'pending'              => 'badge-amber',
                            'assigned', 'matching' => 'badge-blue',
                            'picked_up', 'in_transit' => 'badge-indigo',
                            'completed', 'delivered' => 'badge-green',
                            'cancelled', 'rejected'  => 'badge-red',
                            default                => 'badge-gray',
                        };

                        $statusLabel = match($status) {
                            'pending'    => 'Menunggu Kurir',
                            'assigned'   => 'Kurir Ditugaskan',
                            'matching'   => 'Mencari Kurir',
                            'picked_up'  => 'Barang Diambil',
                            'in_transit' => 'Dalam Perjalanan',
                            'completed'  => 'Selesai',
                            'delivered'  => 'Terkirim',
                            'cancelled'  => 'Dibatalkan',
                            'rejected'   => 'Ditolak',
                            default      => ucfirst($status),
                        };

                        $courierName = $order->courier?->name
                            ?? $order->shipment?->courier?->name
                            ?? null;

                        $resi = $order->tracking_number ?? $order->order_number ?? ('ORD#' . $order->id);
                    @endphp
                    <tr>
                        {{-- Resi & ID --}}
                        <td class="whitespace-nowrap">
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('admin.orders.detail', $order->id) }}"
                                   class="font-mono text-[13px] font-semibold text-brand-700 hover:text-brand-800 hover:underline">
                                    {{ $resi }}
                                </a>
                                @if($order->tracking_number)
                                <button type="button"
                                        onclick="copyResi('{{ $order->tracking_number }}')"
                                        class="p-1 text-slate-400 hover:text-slate-700 rounded transition-colors"
                                        title="Salin Resi">
                                    <span class="material-symbols-outlined text-[15px]">content_copy</span>
                                </button>
                                @endif
                            </div>
                            <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                ID: #{{ $order->id }}
                            </div>
                        </td>

                        {{-- Waktu --}}
                        <td class="whitespace-nowrap text-xs text-slate-500">
                            <div>{{ $order->created_at ? $order->created_at->format('d M Y') : '-' }}</div>
                            <div class="text-slate-400">{{ $order->created_at ? $order->created_at->format('H:i') : '' }} WIB</div>
                        </td>

                        {{-- Pengirim & Penerima --}}
                        <td class="max-w-[200px]">
                            <div class="truncate text-xs">
                                <span class="text-slate-400 font-medium">Dari:</span>
                                <strong class="text-slate-800">{{ $order->sender_name ?? $order->customer_name ?? '-' }}</strong>
                            </div>
                            <div class="truncate text-xs mt-0.5">
                                <span class="text-slate-400 font-medium">Ke:</span>
                                <strong class="text-slate-800">{{ $order->receiver_name ?? '-' }}</strong>
                            </div>
                        </td>

                        {{-- Rute Alamat --}}
                        <td class="max-w-xs">
                            <div class="truncate text-xs text-slate-600" title="{{ $order->pickup_address }}">
                                <span class="text-slate-400 font-medium">P:</span> {{ $order->pickup_address ?? '-' }}
                            </div>
                            <div class="truncate text-xs text-slate-600 mt-0.5" title="{{ $order->delivery_address }}">
                                <span class="text-slate-400 font-medium">D:</span> {{ $order->delivery_address ?? '-' }}
                            </div>
                        </td>

                        {{-- Kurir --}}
                        <td class="whitespace-nowrap">
                            @if($courierName)
                            <div class="flex items-center gap-1.5 text-xs text-slate-800 font-medium">
                                <span class="material-symbols-outlined text-[16px] text-emerald-600">two_wheeler</span>
                                <span>{{ $courierName }}</span>
                            </div>
                            @else
                            <span class="text-xs text-slate-400 italic">Menunggu kurir</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="whitespace-nowrap">
                            <span class="badge {{ $badgeClass }}">
                                <span class="badge-dot"></span>
                                {{ $statusLabel }}
                            </span>
                        </td>

                        {{-- Tarif --}}
                        <td class="whitespace-nowrap font-mono text-xs font-semibold text-slate-900">
                            Rp {{ number_format($order->price ?? 0, 0, ',', '.') }}
                        </td>

                        {{-- Aksi --}}
                        <td class="text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1">
                                @if($order->tracking_number)
                                <a href="{{ route('tracking.show', $order->tracking_number) }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="btn-icon"
                                   title="Buka Live Tracking Web">
                                    <span class="material-symbols-outlined text-[18px]">map</span>
                                </a>
                                @endif

                                <a href="{{ route('admin.orders.detail', $order->id) }}"
                                   class="btn btn-sm btn-secondary"
                                   title="Lihat Detail Pesanan">
                                    Detail
                                </a>

                                @canPerm('orders.delete')
                                <form action="{{ route('admin.orders.destroy', $order->id) }}"
                                      method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus pesanan {{ $order->tracking_number ?? ('#' . $order->id) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn-icon btn-icon-danger"
                                            title="Hapus Pesanan">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                                @endcanPerm
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="card-footer">
            <div>
                Menampilkan <strong>{{ $orders->firstItem() ?? 0 }}</strong> - <strong>{{ $orders->lastItem() ?? 0 }}</strong> dari <strong>{{ $orders->total() }}</strong> pesanan
            </div>
            <div>
                {{ $orders->withQueryString()->links() }}
            </div>
        </div>
        @else
        <div class="empty-state">
            <span class="material-symbols-outlined">inventory_2</span>
            <div class="empty-title">Tidak ada pesanan ditemukan</div>
            <div class="empty-text">
                @if(request('search'))
                    Tidak ditemukan pesanan dengan kata kunci "{{ request('search') }}".
                @else
                    Belum ada data pesanan yang sesuai dengan filter yang dipilih.
                @endif
            </div>
            @if(request('search') || request('status'))
                <a href="{{ route('admin.orders') }}" class="btn btn-sm btn-secondary mt-3">
                    Reset Filter Pencarian
                </a>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- Toast Resi Clipboard --}}
<div id="copyToast" class="fixed bottom-5 right-5 z-50 hidden items-center gap-2 rounded-md bg-navy-900 px-3.5 py-2.5 text-xs font-medium text-white shadow-xl transition-all">
    <span class="material-symbols-outlined text-brand-400 text-[18px]">check_circle</span>
    <span id="toastMessage">Nomor resi berhasil disalin!</span>
</div>

<script>
function copyResi(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(function() {
        showToast('Nomor resi ' + text + ' berhasil disalin!');
    }).catch(function() {
        showToast('Gagal menyalin nomor resi.');
    });
}

function showToast(msg) {
    const toast = document.getElementById('copyToast');
    const toastMsg = document.getElementById('toastMessage');
    if (!toast || !toastMsg) return;
    toastMsg.textContent = msg;
    toast.classList.remove('hidden');
    toast.classList.add('flex');
    setTimeout(function() {
        toast.classList.add('hidden');
        toast.classList.remove('flex');
    }, 2500);
}
</script>
@endsection
